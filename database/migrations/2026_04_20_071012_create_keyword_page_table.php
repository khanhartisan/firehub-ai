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
        Schema::create('keyword_page', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('search_engine_driver');
            $table->ulid('keyword_id')->index();
            $table->ulid('page_id')->index();
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['search_engine_driver', 'keyword_id', 'page_id'], 'keyword_page_search_engine_driver_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keyword_page');
    }
};
