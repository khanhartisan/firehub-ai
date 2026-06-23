# Scraping Hub

A content operations platform that scrapes configured web sources, builds a semantic knowledge graph, synthesizes AI articles for editorial clients, and publishes to external CMS platforms. It exposes a Filament admin panel for operations and an MCP server for agent-driven workflows.

---

## Table of contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Concepts and data model](#concepts-and-data-model)
- [Pipelines](#pipelines)
- [Entrypoints and scheduling](#entrypoints-and-scheduling)
- [Queues](#queues)
- [MCP server](#mcp-server)
- [Project structure](#project-structure)
- [Configuration and environment](#configuration-and-environment)
- [Setup and development](#setup-and-development)
- [Deployment](#deployment)
- [Usage and operations](#usage-and-operations)
- [Testing](#testing)

---

## Overview

**Stack:** PHP 8.4+, Laravel 13, PostgreSQL with [pgvector](https://github.com/pgvector/pgvector)

| Surface | Purpose |
|---------|---------|
| **Filament admin** | `/admin` — manage scraping data, content graph, clients, articles |
| **MCP server** | `/mcp/app` — Sanctum-authenticated API for agents (clients, authors, articles, channels, FlyCMS) |
| **Scheduler + queues** | Background scraping, embedding, intent resolution, article building, publishing |
| **Artisan CLI** | Interactive onboarding (`assistance:make:*`), dev/debug commands, live service tests |

**Key packages:** Filament 5, `laravel/ai`, `laravel/mcp`, `laravel/sanctum`, `pgvector/pgvector`, `khanhartisan/laravel-backbone` (relation cascade), Guzzle, Imagick

**Storage:** Snapshots and files on the default disk (`local` or S3/MinIO via `FILESYSTEM_DISK`)

**AI / external APIs:** OpenAI and OpenAI-compatible drivers; SearchAPI.io and Perplexity for keyword research; FlyCMS for publishing

---

## Architecture

End-to-end flow:

```
Sources → Pages/Files → Snapshots
              ↓
         Embeddings (pgvector)
              ↓
    Intents ← Keywords ← Articles
              ↓
      BuildArticleJob (Synthesizer)
              ↓
    Publications → FlyCMS (via Channels)
```

**Major subsystems:**

| Subsystem | Role |
|-----------|------|
| **Scraping** | Fetch pages and files, classify/parse with AI, store snapshots, discover links |
| **Embeddings** | Vectorize Pages, Sources, Verticals, Intents, Articles, Files for similarity search |
| **Intent resolution** | Link Keywords, Pages, and Articles to shared Intent clusters |
| **Keyword research** | SERP and AI search via SearchAPI / Perplexity |
| **Article synthesis** | Multi-stage AI pipeline (IdeaForge → Research → Brief → Outline → Draft → Rectification → Illustration → Final → Tagging) |
| **Publishing** | Push ready articles to external platforms (FlyCMS) via Channels |
| **Platform Manager** | FlyCMS API driver (+ pseudo driver for local dev) |
| **MCP** | Agent-facing tools and guideline resources for content ops and CMS management |
| **Semantic onboarding** | Interactive CLI to build Client and Author context with AI |

---

## Concepts and data model

### Scraping

| Concept | Description |
|---------|-------------|
| **Vertical** | Hierarchical taxonomy (e.g. "News", "Docs"). Linked to Sources and Pages. |
| **Source** | Website root (`base_url`). Has scraping budgets, `schedule_scraping`, and many Pages. |
| **Page** | A scraped URL belonging to a Source. Has `url`, `url_hash`, `scraping_status`, `scraping_stage`, `next_scrape_at`, classification fields, and optional vector embedding. |
| **File** | Scraped/downloaded asset (images, etc.) with its own scrape pipeline. |
| **Snapshot** | One captured version of a Page: raw HTML path, version, status, metrics, optional `error_logs`. |
| **Tag** | Labels from PageClassifier; many-to-many with Pages. |

**Scraping status (Page / File):** PENDING → QUEUED → FETCHING → PROCESSING → SUCCESS (or FAILED / TIMEOUT / BLOCKED)

### Content graph

| Concept | Description |
|---------|-------------|
| **Intent** | Semantic topic cluster. Linked to Pages, Keywords, and Articles via pivots. |
| **Keyword** | Search term with research status and SERP data; linked to Intents and Pages. |
| **Client** | Editorial tenant (brand). Owns Articles, Authors, and Channels. |
| **Author** | Writing persona for a Client, with structured context (voice, style, expertise). |
| **Article** | AI-built content for a Client. Partitioned by `client_id`. Tracks `stage`, `stage_data`, `context`, and rendered `article` DOM. Status: UNREADY → READY → PUBLISHED (or FAILED / ERROR / REJECTED). |

### Distribution

| Concept | Description |
|---------|-------------|
| **Platform** | External publishing backend (e.g. FlyCMS). Holds config and meta. |
| **Channel** | A Client's publishing destination on a Platform. |
| **Publication** | Morph link from a publishable (Article) to a Channel. Status lifecycle: AWAITING → PENDING → PUBLISHING → PUBLISHED (or TIMEOUT / FAILED / ERROR). |

**Embeddable models** (`Page`, `Source`, `Vertical`, `Intent`, `Article`, `File`) each have `vector`, `is_embeddable`, and `is_embedded` columns managed by the embedding scheduler.

---

## Pipelines

### Page scraping (`ScrapePageJob` on `page_scraping`)

Stages: FETCHING → DATA_PREPARING → DATA_PARSING → ENRICHMENT → FILE_ENRICHMENT → VERTICAL_RESOLUTION → POLICY_EVALUATION → FINISHING → EXPANDING (link discovery creates new Pages on the same host).

On success: cleans HTML, runs PageClassifier and PageParser, stores raw HTML (`snapshots/{page_id}/{ulid}.html`), creates a Snapshot, updates the Page, runs ScrapePolicyEngine for `next_scrape_at`, and discovers same-host links.

### File scraping (`ScrapeFileJob` on `file_scraping`)

Similar staged pipeline for File records, including FileVision for image analysis.

### Embedding (`EmbeddingJob` on `default`)

`ScheduleEmbeddingJob` finds embeddable-but-not-embedded rows across all `EmbeddableModel` subclasses and dispatches `EmbeddingJob`, which calls `TextEmbedding` and stores vectors via pgvector.

### Intent resolution (`ResolveIntentJob` on `page_scraping`)

Batch-links Keywords, Articles, and Pages to Intents using vector similarity and `IntentResolver`. Waits for Intent embeddings before resolving.

### Article building (`BuildArticleJob` on `article_building`)

Nine stages, one stage worth of work per job execution, then self-dispatches:

IDEA → RESEARCH → BRIEF → OUTLINE → DRAFT → RECTIFICATION → ILLUSTRATION → FINAL → TAGGING

Uses the **Synthesizer** orchestrator and its subservices (IdeaForge, Researcher, BriefBuilder, OutlineBuilder, Writer, Editor, Critic, Tagger, Illustration). Marks the article READY at the end. Research stage dispatches `KeywordResearchJob` for SERP lookups.

### Publishing (`PublishingJob` on `publishing`)

`DispatchPublishingJob` picks Publications in PENDING or retriable statuses and dispatches `PublishingJob`, which calls FlyCMS to create/update posts with thumbnails and files.

### Maintenance

- `SetInitialScrapingTimeJob` — sets `next_scrape_at` for new Pages via ScrapePolicyEngine
- `ForceDeleteFiles` / `ForceDeleteSnapshots` — hard-delete cascade-deleted storage
- `CascadeDelete` / `CascadeRestore` — laravel-backbone relation cascade workers

---

## Entrypoints and scheduling

| Route file | Purpose |
|------------|---------|
| `routes/web.php` | Welcome page |
| `routes/ai.php` | MCP server at `/mcp/app` (Sanctum) |
| `routes/console.php` | Scheduler — all jobs below run **every minute** |

**Scheduled jobs:**

| Job | Function |
|-----|----------|
| `ScheduleScrapeDueJob` | Enqueue due Pages and retryable Files; self-redispatches with cache lock |
| `ScrapeSourcesJob` | For sources with `schedule_scraping` and no due pages, ensure home Page exists |
| `ScheduleEmbeddingJob` | Queue embedding jobs for unembedded models |
| `SetInitialScrapingTimeJob` | Set initial `next_scrape_at` for new pages |
| `ResolveIntentJob` | Batch intent resolution |
| `DispatchPublishingJob` | Enqueue pending publications |
| `CascadeDelete` / `CascadeRestore` | Relation cascade |
| `ForceDeleteFiles` / `ForceDeleteSnapshots` | Storage cleanup |

**Workers required:**

- At least one worker on **`scheduler`** (single worker recommended — jobs use cache locks and self-dispatch)
- Workers on **`page_scraping`**, **`file_scraping`**, **`article_building`**, **`keyword_researching`**, **`publishing`**, and **`default`** as needed

In development, `composer dev` runs `queue:listen` which processes all queues.

---

## Queues

| Queue | Jobs |
|-------|------|
| `scheduler` | ScheduleScrapeDueJob, ScrapeSourcesJob, ScheduleEmbeddingJob, DispatchPublishingJob, ScheduleKeywordResearchDueJob |
| `page_scraping` | ScrapePageJob, ResolveIntentJob |
| `file_scraping` | ScrapeFileJob |
| `article_building` | BuildArticleJob |
| `keyword_researching` | KeywordResearchJob |
| `publishing` | PublishingJob |
| `default` | EmbeddingJob, ForceDeleteFiles, ForceDeleteSnapshots |

Queue size caps are configured in `config/queue.php` (env: `QUEUE_DEFAULT_MAX_SIZE`, `QUEUE_SCRAPING_MAX_SIZE`, `QUEUE_SCHEDULER_MAX_SIZE`). Unconfigured queues default to 100.

---

## MCP server

**Endpoint:** `POST /mcp/app` with Sanctum Bearer token

**Generate a token:**

```bash
php artisan sanctum:token
```

**Resources (read first):**

- `app://overview` — domain model, workflows, access rules
- `platform-manager://flycms/overview` — FlyCMS provisioning and CMS guidelines
- FlyCMS guidelines for Websites, Pages, Menus, Files, Tags

**Tool groups (~50 tools):**

| Group | Tools |
|-------|-------|
| **Clients** | list, show, create, update, update_context |
| **Authors** | list, show, create, update, update_context |
| **Articles** | list, show, create, update_context, publish |
| **Channels** | list, show, create, update, get_config_schema |
| **Platforms** | list; create/update/update_config (super user only) |
| **FlyCMS** | Websites, Domains, Tags, Menus, Pages, Files, Meta, Themes — full CRUD |

Access is scoped to the authenticated user's Clients. Platform write operations require `User.is_super`.

The article build pipeline (`BuildArticleJob`) is not exposed as an MCP tool. Once an article is queued for building, poll `show_article` until `status` is `READY`.

---

## Project structure

```
app/
├── Console/Commands/          # assistance:make:*, sanctum:token, app:render-page, live-test:*
├── Contracts/                 # Interfaces for all services and platform managers
├── Enums/                     # Queue, ScrapingStatus, ScrapingStage, ArticleStage, ArticleStatus, etc.
├── Facades/                   # Service facades (Scraper, Synthesizer, IntentResolver, PlatformManager, …)
├── Filament/Resources/        # Admin: Verticals, Intents, Keywords, Tags, Sources, Pages, Snapshots,
│                              #   Files, Clients, Articles, Users
├── Jobs/                      # ScrapePageJob, ScrapeFileJob, BuildArticleJob, EmbeddingJob,
│                              #   ResolveIntentJob, PublishingJob, scheduler jobs, …
├── Mcp/                       # AppServer, tools, guideline resources
├── ModelListeners/            # Counters, cascade hooks, intent resolution triggers
├── Models/                    # Page, Source, Snapshot, File, Vertical, Tag, Intent, Keyword,
│                              #   Client, Author, Article, Platform, Channel, Publication, …
├── Services/
│   ├── Scraper/               # Guzzle HTTP fetch
│   ├── PageClassifier/        # AI page classification
│   ├── PageParser/            # AI structured content extraction
│   ├── FileVision/            # AI vision for image files
│   ├── VerticalResolver/      # Assign verticals to pages
│   ├── ScrapePolicyEngine/    # Rescrape scheduling
│   ├── IntentResolver/        # Intent inference and linking
│   ├── TextEmbedding/         # Laravel AI embedding drivers
│   ├── VectorDB/              # pgvector similarity search
│   ├── SearchEngine/          # SearchAPI, Perplexity
│   ├── FactChecker/           # Research-stage fact checking
│   ├── SemanticContextBuilder/# Interactive Client/Author onboarding
│   ├── Synthesizer/           # Article build orchestrator + subservices
│   ├── PlatformManager/       # FlyCMS driver + pseudo driver
│   └── OpenAI/                # Shared OpenAI client
└── Utils/                     # HtmlCleaner, UrlNormalizer, etc.

config/                        # scraper, pageclassifier, pageparser, filevision, scrapepolicyengine,
                               # verticalresolver, intentresolver, text_embedding, vectordb,
                               # search_engine, synthesizer, semantic_context_builder, flycms,
                               # factchecker, openai, ai, queue, filesystems

docker/pgsql/                  # pgvector init script
compose.yaml                   # Laravel Sail: app, pgvector PostgreSQL, Redis, MinIO
```

---

## Configuration and environment

Copy `.env.example` to `.env` and configure:

**App & database:**

- `APP_KEY`, `APP_URL`, `APP_SERVICE=scraping.hub`
- `DB_*` — PostgreSQL with pgvector (Sail provides `pgvector/pgvector:pg18`)

**Queue & cache:**

- `QUEUE_CONNECTION=database` (or `redis`)
- `QUEUE_DEFAULT_MAX_SIZE`, `QUEUE_SCRAPING_MAX_SIZE`, `QUEUE_SCHEDULER_MAX_SIZE`
- `SCRAPE_MAX_ATTEMPTS`, `ARTICLE_BUILD_MAX_ATTEMPTS`
- `SCRAPE_SOURCES_CHUNK_SIZE`, `SCRAPE_SOURCES_MAX_SECONDS`

**Filesystem:**

- `FILESYSTEM_DISK=local` (or `s3` / MinIO in Sail)

**AI service drivers** (each supports `openai` and `openai_compatible`):

- `FILEVISION_DRIVER`, `PAGECLASSIFIER_DRIVER`, `PAGEPARSER_DRIVER`
- `SCRAPE_POLICY_ENGINE_DRIVER`, `VERTICALRESOLVER_DRIVER`
- `SYNTHESIZER_DRIVER` (+ `SYNTHESIZER_OPENAI_COMPATIBLE_*`)
- `OPENAI_*`, `OPENAI_COMPATIBLE_*`

**Search & research:**

- `SEARCH_ENGINE_DRIVER`, `SEARCHAPI_*`, `PERPLEXITY_*`

**Embeddings & vectors:**

- `TEXT_EMBEDDING_*` in `config/text_embedding.php`
- `VECTORDB_*` in `config/vectordb.php` (dimension default: 1536)

**Publishing:**

- `FLYCMS_*` in `config/flycms.php`

**Onboarding:**

- `SEMANTIC_CONTEXT_BUILDER_*` in `config/semantic_context_builder.php`

---

## Setup and development

**Requirements:** PHP 8.4+ (with ext-imagick, ext-dom, ext-libxml), Composer, Node/npm, PostgreSQL with pgvector

### With Laravel Sail (recommended)

```bash
composer install
cp .env.example .env
# Set DB_HOST=scraping.hub.pgsql and other Sail defaults
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
./vendor/bin/sail artisan make:filament-user
```

Sail services (`compose.yaml`):

| Service | Image | Ports |
|---------|-------|-------|
| `scraping.hub` | PHP 8.5 app | 80, 5173 (Vite) |
| `scraping.hub.pgsql` | pgvector/pgvector:pg18 | 5432 |
| `scraping.hub.redis` | Redis Alpine | 6379 |
| `scraping.hub.minio` | MinIO | 9000, 8900 (console) |

### Without Sail

```bash
composer install
cp .env.example .env
php artisan key:generate
# Set DB_* and other env vars; ensure pgvector extension is enabled
php artisan migrate
npm install && npm run build
php artisan make:filament-user
```

### Run locally

**All-in-one dev** (web + queue + logs + Vite):

```bash
composer dev
```

**Or run separately:**

```bash
php artisan serve
php artisan schedule:work
php artisan queue:work database --queue=scheduler
php artisan queue:work database --queue=page_scraping,file_scraping,article_building,keyword_researching,publishing,default
npm run dev
```

### Useful commands

| Command | Purpose |
|---------|---------|
| `assistance:make:client` | Interactive AI-guided Client creation with context |
| `assistance:make:author` | Interactive Author persona builder |
| `sanctum:token` | Generate MCP API token |
| `app:render-page` | Dev: re-run PageClassifier, PageParser, or HtmlCleaner on a Page |
| `app:test-code` | Dev: intent resolver and embedding similarity test |
| `live-test:*` | Live integration tests for each AI service |

---

## Deployment

1. **Deploy code:** `composer install --no-dev`, `php artisan migrate --force`, `npm ci && npm run build`
2. **Environment:** production `.env` with DB, queue, filesystem, OpenAI, FlyCMS, and search API keys. Set `APP_ENV=production`, `APP_DEBUG=false`.
3. **Scheduler:** cron `* * * * * cd /path-to-app && php artisan schedule:run`
4. **Queue workers** (Supervisor/systemd):
   - One worker on `scheduler` (`--tries=1`)
   - Workers on `page_scraping`, `file_scraping`, `article_building`, `keyword_researching`, `publishing`, `default`
   - Restart after deploy: `php artisan queue:restart`
5. **Storage:** set `FILESYSTEM_DISK=s3` and AWS/MinIO credentials if using object storage
6. **Admin:** create user with `php artisan make:filament-user`, access `/admin` over HTTPS
7. **MCP:** generate tokens with `php artisan sanctum:token` for agent access to `/mcp/app`

---

## Usage and operations

### Admin panel (`/admin`)

| Navigation group | Resources |
|------------------|-----------|
| **Content** | Verticals, Intents (with Keywords/Pages/Articles relations), Keywords, Tags |
| **Remote** | Sources, Pages, Snapshots, Files |
| **Distribution** | Clients, Articles |
| **Administration** | Users |

Dashboard widgets show page stats by status, type, and over time.

### Adding scraping work

Create a **Source** (base URL) and attach it to a Vertical. The scheduler creates the home-page Page if missing and enqueues it. `ScrapePageJob` discovers same-host links and creates new Pages. You can also create or edit Pages manually.

### Producing articles

1. Create a **Client** (`assistance:make:client` or Filament/MCP)
2. Create **Authors** with context (`assistance:make:author`)
3. Create an **Article** (MCP or Filament)
4. Monitor article `stage` and `status` in Filament or via MCP `show_article` as `BuildArticleJob` progresses through its stages on the `article_building` queue
5. When `status` is READY, publish via MCP `publish_article` or create a Publication

### Publishing

1. Configure a **Platform** (FlyCMS) — super user via MCP
2. Create a **Channel** linking a Client to the Platform
3. Publish an article — creates a Publication; `DispatchPublishingJob` picks it up and runs `PublishingJob`

### Monitoring

- Queue sizes and failed jobs: `php artisan queue:failed`
- Logs: `php artisan pail` (dev) or application log channel
- Snapshots and files on the configured disk; records in admin or DB

---

## Testing

```bash
composer test
# runs: ./vendor/bin/sail artisan test
```

Or directly:

```bash
php artisan test
```

**Test coverage areas:**

- Jobs: `ScrapePageJob`, `BuildArticleJob`, `DispatchPublishingJob`, embedding, intent resolution
- Services: OpenAI, ScrapePolicyEngine, PageParser, PageClassifier, FileVision, Synthesizer subservices, VectorDB, SearchEngine, FactChecker
- MCP tools: client, author, article, channel, and FlyCMS tool tests
- Utils: HtmlCleaner, structured data helpers
