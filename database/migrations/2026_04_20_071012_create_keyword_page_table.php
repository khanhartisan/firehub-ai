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
            $table->ulid('keyword_id');
            $table->ulid('page_id');
            $table->decimal('relevance', 3)->nullable();
            $table->timestamps();

            $table->unique(['keyword_id', 'page_id']);
            $table->index(['page_id', 'relevance']);
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
