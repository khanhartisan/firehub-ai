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
        Schema::create('page_vertical', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('page_id');
            $table->ulid('vertical_id');
            $table->timestamps();

            $table->unique(['page_id', 'vertical_id']);
            $table->index(['vertical_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_vertical');
    }
};
