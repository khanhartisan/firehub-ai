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
        Schema::create('snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('page_id');
            $table->unsignedTinyInteger('scraping_status')
                ->default(\App\Enums\ScrapingStatus::PENDING->value);

            $table->string('file_path')->nullable()->index();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime_type')->nullable();
            $table->string('file_extension')->nullable();

            $table->unsignedInteger('version')->default(0);

            // Base metrics for policy calculation
            $table->unsignedInteger('content_length')->nullable();
            $table->unsignedInteger('structured_data_count')->default(0);
            $table->unsignedInteger('media_count')->default(0);
            $table->unsignedInteger('links_count')->default(0);
            $table->decimal('content_change_percentage', 5, 2)->nullable();

            // Cost metrics for cost_factor calculation
            $table->unsignedInteger('fetch_duration_ms')->nullable();
            $table->decimal('cost', 3, 2)->nullable();

            // Exception/error details for debugging
            $table->text('error_logs')->nullable();

            $table->timestamps();

            $table->index(['page_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
