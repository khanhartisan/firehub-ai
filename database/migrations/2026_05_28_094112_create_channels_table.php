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
            $table->ulid('platform_id');
            $table->ulid('client_id');
            $table->string('name');
            $table->jsonb('config')->nullable();
            $table->timestamps();

            $table->index(['platform_id', 'id']);
            $table->index(['client_id', 'id']);
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
