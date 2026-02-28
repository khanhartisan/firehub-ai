<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('source_id');

            $table->ulid('canonical_entity_id')->nullable();
            $table->unsignedBigInteger('canonical_number')->default(0);

            $table->unsignedTinyInteger('type')->default(\App\Enums\EntityType::UNCLASSIFIED->value);
            $table->unsignedTinyInteger('scraping_status')->default(\App\Enums\ScrapingStatus::PENDING->value);

            $table->text('url');
            $table->char('url_hash', 40); // use sha1

            $table->string('description', 1024)->nullable();

            // For entity type = page
            $table->string('page_type')->nullable();
            $table->string('content_type')->nullable();
            $table->string('temporal')->nullable();

            $table->unsignedInteger('snapshots_count')->default(0);

            $table->timestamps();
            $table->dateTime('source_published_at')->nullable();
            $table->dateTime('source_updated_at')->nullable();
            $table->dateTime('fetched_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->json('policy_result')->nullable();
            $table->dateTime('next_scrape_at')->nullable();

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);

            // Indexes
            $table->index(['scraping_status', 'next_scrape_at']);
            $table->index(['source_id', 'type', 'source_published_at'], 'source_index');
            $table->index(['source_id', 'next_scrape_at']);
            $table->index(['url_hash', 'source_id']);
            $table->index(['canonical_entity_id', 'canonical_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
