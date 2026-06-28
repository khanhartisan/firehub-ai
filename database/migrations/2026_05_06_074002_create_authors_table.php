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
        Schema::create('authors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('client_id')->index();
            $table->string('name')->nullable();
            $table->string('short_bio')->nullable();
            $table->text('bio')->nullable();
            $table->jsonb('context')->nullable();
            $table->unsignedInteger('articles_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
