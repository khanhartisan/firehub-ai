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
        Schema::create('hitl_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('hitl_platform_id');
            $table->string('status')->default(\App\Contracts\HitlGateway\TaskStatus::PENDING->value);
            $table->string('hitl_platform_reference')->nullable();
            $table->string('internal_reference')->nullable();
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->jsonb('data')->nullable();
            $table->jsonb('conclusion')->nullable();
            $table->timestamps();

            $table->unique(['hitl_platform_id', 'internal_reference']);
            $table->index(['hitl_platform_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hitl_tasks');
    }
};
