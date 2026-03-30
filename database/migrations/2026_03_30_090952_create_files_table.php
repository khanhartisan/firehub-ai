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
        Schema::create('files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedTinyInteger('scraping_status')
                ->default(\App\Enums\ScrapingStatus::PENDING->value);

            $table->string('description', 4096)->nullable();

            $table->string('url', 4096);
            $table->char('url_hash', 40)->unique();

            $table->string('path')->nullable()->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();

            $table->unsignedInteger('fetch_duration_ms')->nullable();
            $table->decimal('cost', 3, 2)->nullable();

            $table->text('error_logs')->nullable();

            $table->vector('vector', config('vectordb.drivers.pgvector.default_dimension'))->nullable()->index();
            $table->boolean('is_embeddable')->default(false);
            $table->boolean('is_embedded')->default(false);
            $table->index(['is_embeddable', 'is_embedded', 'updated_at']);

            $table->timestamps();
            $table->dateTime('scraped_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
