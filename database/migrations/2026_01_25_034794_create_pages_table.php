<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            Schema::connection($this->connection)->ensureVectorExtensionExists();
        }

        Schema::create('pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('source_id');

            $table->ulid('canonical_page_id')->nullable();
            $table->unsignedBigInteger('canonical_number')->default(0);

            $table->unsignedTinyInteger('type')->default(\App\Enums\ScrapableType::UNCLASSIFIED->value);
            $table->boolean('ignore_scraping_budget')->default(false);
            $table->unsignedTinyInteger('scraping_status')->default(\App\Enums\ScrapingStatus::PENDING->value);
            $table->string('scraping_stage')->nullable();

            $table->text('url');
            $table->char('url_hash', 40); // use sha1

            $table->string('title', 1024)->nullable();
            $table->string('description', 1024)->nullable();

            $table->unsignedInteger('version_index')->default(0);

            // For scrapable type = page
            $table->string('page_type')->nullable();
            $table->string('content_type')->nullable();
            $table->string('temporal')->nullable();

            $table->unsignedInteger('snapshots_count')->default(0);

            $table->timestamps();
            $table->dateTime('source_published_at')->nullable();
            $table->dateTime('source_updated_at')->nullable();
            $table->dateTime('scraped_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->json('policy_result')->nullable();
            $table->dateTime('next_scrape_at')->nullable();

            $table->longText('logs')->nullable();

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);

            // Indexes
            $table->index(['scraping_status', 'next_scrape_at']);
            $table->unique(['source_id', 'url_hash']);
            $table->index(['source_id', 'type', 'source_published_at'], 'source_index');
            $table->index(['source_id', 'next_scrape_at']);
            $table->index(['source_id', 'scraped_at']);
            $table->index(['url_hash', 'source_id']);
            $table->index(['canonical_page_id', 'canonical_number']);

            $table->vector('vector', config('vectordb.drivers.pgvector.default_dimension'))->nullable()->index();
            $table->boolean('is_embeddable')->default(false);
            $table->boolean('is_embedded')->default(false);
            $table->index(['is_embeddable', 'is_embedded', 'updated_at']);

            $table->unsignedInteger('intents_count')->default(0);
            $table->dateTime('intent_resolved_at')->nullable();
            $table->index(['is_embedded', 'intent_resolved_at', 'updated_at'], 'intent_resolved_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
