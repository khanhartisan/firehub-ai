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
        Schema::create('intent_keyword', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('intent_id');
            $table->ulid('keyword_id');
            $table->decimal('relevance', 3);
            $table->timestamps();

            $table->unique(['intent_id', 'keyword_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intent_keyword');
    }
};
