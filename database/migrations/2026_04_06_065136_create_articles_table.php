<?php

use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // We shard the "articles" table by the client_id column
        // because we want to be able to perform the vector queries
        // against a client_id with the best performance

        DB::statement('CREATE TABLE articles (
            id char(26) NOT NULL,
            client_id varchar(255) NOT NULL,
            primary key(client_id, id)
        ) PARTITION BY HASH (client_id)');

        Schema::table('articles', function (Blueprint $table) {
            $table->ulid('author_id')->nullable();
            $table->string('language')->nullable();
            $table->string('temporal')->nullable();

            $table->unsignedTinyInteger('status')->default(ArticleStatus::UNREADY);

            $table->unsignedTinyInteger('stage')
                ->default(ArticleStage::IDEA->value);
            $table->unsignedTinyInteger('stage_status')
                ->default(ArticleStageStatus::PENDING->value);
            $table->jsonb('stage_data')->nullable();

            $table->jsonb('context')->nullable();

            $table->string('title')->nullable();
            $table->text('excerpt')->nullable();
            $table->json('article')->nullable();
            $table->jsonb('illustration')->nullable();
            $table->ulid('thumbnail_file_id')->nullable();

            $table->timestamps();
            $table->dateTime('processing_at')->nullable();
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
            $table->index(['is_embedded', 'intent_resolved_at', 'updated_at'], 'is_embedded_intent_resolved_at_index');

            $table->index(['status', 'updated_at']);
            $table->index(['status', 'processing_at']);
            $table->index(['status', 'id']);
            $table->index(['author_id', 'id']);
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
