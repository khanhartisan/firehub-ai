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
        Schema::create('publications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('channel_id');
            $table->string('publishable_type');
            $table->ulid('publishable_id');

            $table->string('title')->nullable();
            $table->string('description')->nullable();

            $table->unsignedTinyInteger('status')
                ->default(\App\Enums\PublicationStatus::PENDING->value);
            $table->string('reference')->nullable();
            $table->jsonb('meta')->nullable();
            $table->longText('error_logs')->nullable();
            $table->timestamps();
            $table->dateTime('published_at')->nullable()->index();

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->index(['status', 'updated_at']);
            $table->unique(['channel_id', 'publishable_type', 'publishable_id'], 'publication_unique');
            $table->index(['publishable_type', 'publishable_id', 'id'], 'publication_publishable_index');
            $table->index(['channel_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
