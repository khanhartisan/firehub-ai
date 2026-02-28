<?php

use App\Models\Entity;
use App\Models\Source;
use App\Models\Tag;
use App\Models\Vertical;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single table for all vector spaces; "space" column stores the collection name.
     * Indexes for scale: B-tree on space, HNSW on embedding (cosine), GIN on metadata.
     * Dedicated HNSW partial index for the entity space (high-volume known space).
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('vector_records', function (Blueprint $table) {
            $table->string('space', 255);
            $table->string('id', 255);
            $table->vector('embedding', 1536);
            $table->jsonb('metadata')->default('{}');
            $table->primary(['space', 'id']);
        });

        // This index for dropping records
        DB::statement('CREATE INDEX vector_records_space_idx ON vector_records (space)');
        DB::statement('CREATE INDEX vector_records_embedding_hnsw_idx ON vector_records USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX vector_records_metadata_gin_idx ON vector_records USING gin (metadata jsonb_path_ops)');

        // Important!
        // We create indexes for known spaces
        // If the table grows, and we have a new space,
        // we will need to add a migration to create a new index for that space.
        $spaces = [
            new Entity()->getMorphClass(),
            new Source()->getMorphClass(),
            new Tag()->getMorphClass(),
            new Vertical()->getMorphClass(),
        ];
        foreach ($spaces as $space) {
            DB::statement(sprintf(
                "CREATE INDEX vector_records_embedding_hnsw_".$space."_idx ON vector_records USING hnsw (embedding vector_cosine_ops) WHERE space = %s",
                DB::getPdo()->quote($space)
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vector_records');
    }
};
