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
        Schema::create('fileables', function (Blueprint $table) {
            $table->ulid();
            $table->string('fileable_type');
            $table->string('fileable_id');
            $table->string('file_id');
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['fileable_type', 'fileable_id', 'file_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fileables');
    }
};
