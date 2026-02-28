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
        Schema::create('sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('base_url')->unique();
            $table->unsignedTinyInteger('authority_score')->default(0);
            $table->decimal('priority', 3, 2)->default(0.5);
            $table->timestamps();

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
