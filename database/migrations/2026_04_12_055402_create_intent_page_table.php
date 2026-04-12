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
        Schema::create('intent_page', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('intent_id');
            $table->ulid('page_id');
            $table->decimal('relevance', 3);
            $table->timestamps();

            $table->unique(['intent_id', 'page_id']);
            $table->index(['page_id', 'relevance']);
            $table->index(['intent_id', 'relevance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intent_page');
    }
};
