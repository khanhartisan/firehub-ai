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
        Schema::create('hitl_platforms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->boolean('is_active')->default(false);
            $table->string('driver')->nullable();
            $table->string('name')->unique();
            $table->jsonb('config')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->cascades();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hitl_platforms');
    }
};
