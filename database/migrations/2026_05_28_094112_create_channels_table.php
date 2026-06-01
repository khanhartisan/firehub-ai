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
        Schema::create('channels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('client_id');

            $table->ulid('platform_id');
            $table->string('reference')->nullable()->index();

            $table->string('name');
            $table->jsonb('config')->nullable();
            $table->unsignedTinyInteger('status')->default(\App\Enums\ChannelStatus::PENDING->value);

            $table->unsignedInteger('publications_count')->default(0);
            $table->timestamps();

            $table->longText('error_logs')->nullable();

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);

            $table->index(['platform_id', 'id']);
            $table->index(['client_id', 'id']);
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
