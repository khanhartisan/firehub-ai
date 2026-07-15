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
        Schema::create('publishing_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('channel_id');
            $table->string('publishable_type');
            $table->unsignedTinyInteger('status')->default(\App\Enums\PublishingScheduleStatus::INACTIVE);
            $table->jsonb('context')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);

            $table->string('cron');
            $table->dateTime('next_execution_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publishing_schedules');
    }
};
