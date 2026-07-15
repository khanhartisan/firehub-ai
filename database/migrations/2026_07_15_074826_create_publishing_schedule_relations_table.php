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
        Schema::create('publishing_schedule_relations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('publishing_schedule_id');
            $table->string('relation_type');
            $table->ulid('relation_id');
            $table->timestamps();

            $table->unique([
                'publishing_schedule_id',
                'relation_type',
                'relation_id',
            ], 'publishing_schedule_relations_unique');
            $table->index(['relation_type', 'relation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publishing_schedule_relations');
    }
};
