# Scraping Hub

An application that schedules and runs web scraping for configured **sources**, discovers linked pages, classifies and parses content with AI, stores **snapshots**, and re-scrapes based on a **policy engine**. It exposes an admin panel (Filament) for managing Verticals, Sources, Entities, Snapshots, Clients, and Users.

---

## Table of contents

- [Overview](#overview)
- [Concepts and data model](#concepts-and-data-model)
- [How scraping works](#how-scraping-works)
- [Entrypoints and scheduling](#entrypoints-and-scheduling)
- [Project structure](#project-structure)
- [Configuration and environment](#configuration-and-environment)
- [Setup and development](#setup-and-development)
- [Deployment](#deployment)
- [Usage and operations](#usage-and-operations)
- [Testing](#testing)

---

## Overview

- **Stack:** PHP 8.4+
- **Admin UI:** [Filament](https://filamentphp.com) 5 at `/admin`
- **Queue:** Queues (default: database); scheduler and scraping run as queued jobs
- **Storage:** Snapshots (raw HTML) stored on the default disk (local or S3 via `FILESYSTEM_DISK`)
- **AI/APIs:** OpenAI and OpenAI-compatible drivers for PageClassifier, PageParser, ScrapePolicyEngine, and FileVision

The app does not expose public HTTP APIs for scraping; all scraping is driven by the **scheduler** and the **admin** (e.g. creating sources/entities). The web route is a simple welcome view; the main behaviour lives in **console/scheduler** and **queue workers**.

---

## Concepts and data model

### Core entities

| Concept | Description |
|--------|-------------|
| **Vertical** | A category (e.g. "News", "Docs"). Has many Sources and Entities (via pivot). Used for grouping and entity counts. |
| **Source** | A website root: `base_url` (e.g. `https://example.com`). Has many **Entities**. Sources are attached to Verticals. |
| **Entity** | A single URL belonging to a Source. Has `url`, `url_hash` (sha1), `scraping_status`, `next_scrape_at`, optional classification fields (`type`, `page_type`, `content_type`, `temporal`), and relations to Snapshots, Tags, Verticals. |
| **Snapshot** | One captured version of an Entity: raw HTML path (`file_path` on default disk), version number, status, metrics (content_length, link_count, fetch_duration_ms, etc.), and optional `error_logs`. |
| **Tag** | Labels from the PageClassifier; many-to-many with Entity. |
| **Client / User** | Filament auth and optional client model for access control. |

### Scraping status (Entity)

- **PENDING** – Not yet queued; will be picked when `next_scrape_at <= now()` (or null).
- **QUEUED** – Job dispatched to the `scraping` queue.
- **FETCHING** – Job is running (HTTP fetch in progress).
- **SUCCESS** – Last run succeeded; `next_scrape_at` set by policy.
- **FAILED** – Non-recoverable or generic failure; retries with backoff.
- **TIMEOUT** – Connect/timeout failure.
- **BLOCKED** – e.g. HTTP 403/429; retries with backoff.

### Queues

- **`scheduler`** – `ScheduleScrapeDueJob`, `ScrapeSourcesJob`. Run **one** worker so only one scheduler loop runs at a time.
- **`scraping`** – `ScrapeEntityJob`. Scale workers as needed; queue size is capped via config to avoid unbounded growth.
- **`default`** – General application jobs.

---

## How scraping works

1. **Scheduler (every minute)**  
   - **ScheduleScrapeDueJob**  
     - Finds entities that are due (by status and `next_scrape_at`) and have attempts under the max.  
     - Dispatches up to a limit of `ScrapeEntityJob` to the `scraping` queue (respecting queue size cap), marks them QUEUED, then re-dispatches itself with a short delay.  
     - Uses a cache lock so only one execution runs at a time even with multiple workers.  
   - **ScrapeSourcesJob**  
     - For sources that have **no** entity currently due for scraping, ensures an entity exists for the source’s `base_url` and dispatches one `ScrapeEntityJob` (home-page scrape). Runs in chunks with a time limit.

2. **ScrapeEntityJob (per entity)**  
   - Marks entity as FETCHING, fetches URL via **Scraper** (Guzzle).  
   - On success:  
     - Cleans HTML (**HtmlCleaner**), runs **PageClassifier** and **PageParser** (AI).  
     - Stores raw HTML under default disk (`snapshots/{entity_id}/{ulid}.html`).  
     - Creates a **Snapshot** and updates the Entity (type, description, dates, tags, etc.).  
     - Runs **ScrapePolicyEngine** to get `next_scrape_at` and stores `policy_result`.  
     - Sets entity to SUCCESS and resets attempts.  
     - Discovers same-host links from the parser; creates new Entities for URLs not yet in DB and leaves them PENDING (scheduler will pick them up when due).  
   - On failure: creates a failure Snapshot, increments attempts, sets backoff or stops if max attempts reached.

3. **Persistence**  
   - Snapshots are stored on the configured default filesystem (`storage/app/private` for local disk).  
   - Entity counts per Vertical/Source/Tag (by type and status) are maintained via **EntityCount** and the **EntityCountListener** (from `khanhartisan/laravel-backbone`).

---

## Entrypoints and scheduling

- **Web:** `routes/web.php` – welcome page; no scraping endpoints.
- **Console / scheduler:** `routes/console.php`  
  - Registers two scheduled jobs (both every minute):  
    - `Schedule::job(new ScheduleScrapeDueJob(limit: 50))->everyMinute();`  
    - `Schedule::job(new ScrapeSourcesJob)->everyMinute();`  
  - For the scheduler to run, use:  
    - **Development:** `php artisan schedule:work` (or run the schedule from cron in production).  
  - Queue workers must be running for the jobs to execute:  
    - At least one worker on the **scheduler** queue (single worker recommended).  
    - One or more workers on the **scraping** queue.

So the “entrypoint” for the scraping pipeline is the **scheduler** defined in `routes/console.php`, which enqueues jobs; the actual work is done by queue workers.

---

## Project structure

```
app/
├── Console/Commands/          # Artisan commands (e.g. TestPageEntity)
├── Contracts/                 # Interfaces (Scraper, Classifier, Parser, ScrapePolicyEngine, FileVision, OpenAI)
├── Enums/                     # Queue, ScrapingStatus, EntityType, PageType, ContentType, Temporal
├── Facades/                   # Scraper, PageClassifier, PageParser, ScrapePolicyEngine, FileVision, OpenAI
├── Filament/                  # Admin panel: Resources (Verticals, Sources, Entities, Snapshots, Clients, Users), Widgets
├── Jobs/
│   ├── ScheduleScrapeDueJob.php   # Scheduler: enqueue due entities, re-dispatch self
│   ├── ScrapeSourcesJob.php       # Scheduler: ensure home-page entity per source, dispatch ScrapeEntityJob
│   └── ScrapeEntityJob.php        # Fetch URL, classify/parse, store snapshot, policy, discover links
├── ModelListeners/            # Entity: SetUrlHashListener, EntityCountListener (backbone package)
├── Models/                    # Entity, Source, Vertical, Snapshot, Tag, Client, User, pivots
├── Services/                  # Manager + driver implementations
│   ├── Scraper/               # Guzzle driver
│   ├── PageClassifier/        # OpenAI driver
│   ├── PageParser/            # OpenAI driver
│   ├── ScrapePolicyEngine/    # Dummy + OpenAI drivers
│   ├── FileVision/            # (optional) OpenAI driver
│   └── OpenAI/                # API client used by AI drivers
└── Utils/                     # HtmlCleaner

config/
├── queue.php                  # Queue connection, size limits, scrape attempts, ScrapeSourcesJob chunk/timeout
├── scraper.php                # Guzzle timeout, redirects, headers
├── openai.php                 # OpenAI + openai_compatible drivers
├── pageclassifier.php, pageparser.php, scrapepolicyengine.php, filevision.php
└── filesystems.php            # local / s3 for snapshots

database/migrations/           # entities, sources, snapshots, verticals, tags, entity_vertical, source_vertical, jobs, cache, etc.

routes/
├── web.php                    # Welcome route
└── console.php                # Schedule: ScheduleScrapeDueJob, ScrapeSourcesJob every minute
```

---

## Configuration and environment

Copy `.env.example` to `.env` and set at least:

- **App:** `APP_KEY`, `APP_URL`, `APP_SERVICE=scraping.hub`
- **Database:** `DB_*` (e.g. PostgreSQL)
- **Queue:** `QUEUE_CONNECTION=database` (or redis/sqs). Optional: `QUEUE_SCRAPING_MAX_SIZE`, `QUEUE_SCHEDULER_MAX_SIZE`, `SCRAPE_MAX_ATTEMPTS`, `SCRAPE_SOURCES_CHUNK_SIZE`, `SCRAPE_SOURCES_MAX_SECONDS`
- **Cache / session:** Typically `CACHE_STORE`, `SESSION_DRIVER` (e.g. database)
- **Filesystem:** `FILESYSTEM_DISK=local` (or `s3`). Snapshots go to the default disk (local root: `storage/app/private`).
- **Scraper:** Optional: `SCRAPER_TIMEOUT`, `SCRAPER_USER_AGENT`, etc. in `config/scraper.php`
- **OpenAI:** For AI features set `OPENAI_DRIVER`, `OPENAI_API_KEY`, `OPENAI_DEFAULT_MODEL`, etc.; for third-party OpenAI-style APIs use `OPENAI_COMPATIBLE_*` in `config/openai.php`
- **ScrapePolicyEngine:** `SCRAPE_POLICY_ENGINE_DRIVER=dummy` (default) or `openai`; dummy uses `SCRAPE_POLICY_ENGINE_DUMMY_INTERVAL_HOURS`

Relevant config keys:

- **config/queue.php:** `max_scrape_attempts`, `max_scraping_queue_size`, `max_scheduler_queue_size`, `scrape_sources_chunk_size`, `scrape_sources_max_seconds`
- **config/scraper.php:** default driver `guzzle`, timeouts and headers
- **config/openai.php:** drivers `openai`, `openai_compatible`
- **config/scrapepolicyengine.php:** drivers `dummy`, `openai`

---

## Setup and development

**Requirements:** PHP 8.4+, Composer, Node/npm (for Filament/Vite), PostgreSQL (or DB of choice).

```bash
composer install
cp .env.example .env
php artisan key:generate
# Set DB_* and other env vars
php artisan migrate
npm install && npm run build
```

**Create Filament admin user:**

```bash
php artisan make:filament-user
```

**Run the app (dev):**

- Web: `php artisan serve`
- Scheduler: `php artisan schedule:work`
- Queues: run at least one worker for `scheduler` and one or more for `scraping`:

```bash
php artisan queue:work database --queue=scheduler
php artisan queue:work database --queue=scraping
```

Or use the composer script (if defined):

```bash
composer dev
```

**Useful commands:**

- `php artisan app:render-page-entity` – Interactive: pick an entity and run PageClassifier, PageParser, or HtmlCleaner for debugging.

---

## Deployment

1. **Code and dependencies**  
   - Deploy app (e.g. git pull), run `composer install --no-dev`, `php artisan migrate --force`, `npm ci && npm run build` (or use built assets).

2. **Environment**  
   - Configure `.env` for production (DB, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, `OPENAI_*` / `SCRAPE_POLICY_ENGINE_DRIVER`, etc.).  
   - Ensure `APP_ENV=production`, `APP_DEBUG=false`, and a strong `APP_KEY`.

3. **Scheduler**  
   - Add cron: `* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1` (or use a process manager that runs `schedule:work`).

4. **Queue workers**  
   - Run at least **one** worker for the **scheduler** queue only (e.g. `php artisan queue:work database --queue=scheduler --tries=1`).  
   - Run one or more workers for the **scraping** queue (e.g. `php artisan queue:work database --queue=scraping --tries=2`).  
   - Use a process manager (Supervisor, systemd) and restart workers after deploy (e.g. `php artisan queue:restart`).

5. **Storage**  
   - If using S3 for snapshots, set `FILESYSTEM_DISK=s3` and AWS_* in `.env`.  
   - Run `php artisan storage:link` if you serve public storage assets.

6. **Filament**  
   - Create an admin user with `php artisan make:filament-user` and access `/admin` over HTTPS.

---

## Usage and operations

- **Admin panel:** Log in at `/admin`. Manage Verticals, Sources, Entities, Snapshots, Clients, Users. Dashboard widgets show entity stats (e.g. by status, type, over time).
- **Adding work:** Create a **Source** (base URL) and attach it to a Vertical. The scheduler will create the home-page entity if missing and enqueue it; from there, `ScrapeEntityJob` will discover same-host links and create new entities. You can also create or edit entities manually in Filament.
- **Monitoring:** Check queue sizes (`QUEUE_SCRAPING_MAX_SIZE`, `QUEUE_SCHEDULER_MAX_SIZE`), failed jobs (`php artisan queue:failed`), and logs. Snapshots are stored on the default disk; inspect entity/snapshot records in the admin or DB.
- **Policy:** With `SCRAPE_POLICY_ENGINE_DRIVER=dummy`, next scrape is after a fixed interval (e.g. 24h). With `openai`, the engine uses the entity/snapshot data to compute `next_scrape_at`.

---

## Testing

- **PHPUnit:** `composer test` or `php artisan test`.
- **Relevant tests:** e.g. `tests/Feature/Jobs/ScrapeEntityJobTest`, `tests/Unit/Services/...` (OpenAI, ScrapePolicyEngine, PageParser, PageClassifier, FileVision), `tests/Unit/Utils/HtmlCleanerTest`.

