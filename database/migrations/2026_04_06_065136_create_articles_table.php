<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE TABLE articles (
            id char(26) NOT NULL,
            space varchar(255) NOT NULL,
            primary key(space, id)
        ) PARTITION BY HASH (space)");

        Schema::table('articles', function (Blueprint $table) {
            $table->string('temporal')->nullable();

            $table->unsignedTinyInteger('stage')
                ->default(\App\Enums\ArticleStage::BRIEF->value);
            $table->unsignedTinyInteger('stage_status')
                ->default(\App\Enums\ArticleStageStatus::PENDING->value);
            $table->jsonb('stage_data')->nullable();

            $table->text('prompt')->nullable();

            $table->string('title')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body_markdown')->nullable();
            $table->ulid('thumbnail_file_id')->nullable();

            $table->timestamps();
            $table->index(['stage', 'stage_status', 'updated_at'], 'stage_index');
            $table->index(['temporal', 'updated_at'], 'temporal_index');

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);

            $table
                ->vector('vector', config('vectordb.drivers.pgvector.default_dimension'))
                ->nullable();
            $table->boolean('is_embeddable')->default(false);
            $table->boolean('is_embedded')->default(false);
            $table->index(['is_embeddable', 'is_embedded', 'updated_at']);

            $table->unsignedInteger('intents_count')->default(0);
            $table->dateTime('intent_resolved_at')->nullable();
            $table->index(['is_embedded', 'intent_resolved_at', 'updated_at'], 'intent_resolved_at_index');
        });

        for ($i = 0; $i < 128; $i++) {
            $pName = "articles_p{$i}";

            // Create the partition table
            DB::statement("CREATE TABLE {$pName} PARTITION OF articles FOR VALUES WITH (MODULUS 128, REMAINDER {$i})");

            // Add vector index
            DB::statement("CREATE INDEX {$pName}_vector_hnsw_idx ON {$pName} USING hnsw (vector vector_cosine_ops);");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
