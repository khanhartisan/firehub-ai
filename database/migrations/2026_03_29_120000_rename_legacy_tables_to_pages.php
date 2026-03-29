<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Upgrades databases created before the Entity → Page rename. Fresh installs
     * use the updated create_* migrations and skip this.
     */
    public function up(): void
    {
        if (! Schema::hasTable('entities')) {
            return;
        }

        Schema::rename('entities', 'pages');

        Schema::table('pages', function (Blueprint $table) {
            $table->renameColumn('canonical_entity_id', 'canonical_page_id');
        });

        if (Schema::hasTable('snapshots') && Schema::hasColumn('snapshots', 'entity_id')) {
            Schema::table('snapshots', function (Blueprint $table) {
                $table->renameColumn('entity_id', 'page_id');
            });
        }

        if (Schema::hasTable('entity_relations')) {
            Schema::rename('entity_relations', 'page_relations');
            Schema::table('page_relations', function (Blueprint $table) {
                $table->renameColumn('source_entity_id', 'source_page_id');
                $table->renameColumn('related_entity_id', 'related_page_id');
            });
        }

        if (Schema::hasTable('entity_vertical')) {
            Schema::rename('entity_vertical', 'page_vertical');
            Schema::table('page_vertical', function (Blueprint $table) {
                $table->renameColumn('entity_id', 'page_id');
            });
        }

        if (Schema::hasTable('entity_tag')) {
            Schema::rename('entity_tag', 'page_tag');
            Schema::table('page_tag', function (Blueprint $table) {
                $table->renameColumn('entity_id', 'page_id');
            });
        }

        if (Schema::hasTable('entity_counts')) {
            Schema::table('entity_counts', function (Blueprint $table) {
                $table->dropUnique('countable_entity_scraping_unique');
            });

            Schema::rename('entity_counts', 'page_counts');

            Schema::table('page_counts', function (Blueprint $table) {
                $table->renameColumn('entity_type', 'scrapable_type');
            });

            DB::table('page_counts')
                ->where('countable_type', 'entity')
                ->update(['countable_type' => 'page']);

            Schema::table('page_counts', function (Blueprint $table) {
                $table->unique(
                    ['countable_type', 'countable_id', 'scrapable_type', 'scraping_status'],
                    'countable_page_scraping_unique'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('pages') || Schema::hasTable('entities')) {
            return;
        }

        if (Schema::hasTable('page_counts')) {
            Schema::table('page_counts', function (Blueprint $table) {
                $table->dropUnique('countable_page_scraping_unique');
            });

            DB::table('page_counts')
                ->where('countable_type', 'page')
                ->update(['countable_type' => 'entity']);

            Schema::table('page_counts', function (Blueprint $table) {
                $table->renameColumn('scrapable_type', 'entity_type');
            });

            Schema::rename('page_counts', 'entity_counts');

            Schema::table('entity_counts', function (Blueprint $table) {
                $table->unique(
                    ['countable_type', 'countable_id', 'entity_type', 'scraping_status'],
                    'countable_entity_scraping_unique'
                );
            });
        }

        if (Schema::hasTable('page_tag')) {
            Schema::table('page_tag', function (Blueprint $table) {
                $table->renameColumn('page_id', 'entity_id');
            });
            Schema::rename('page_tag', 'entity_tag');
        }

        if (Schema::hasTable('page_vertical')) {
            Schema::table('page_vertical', function (Blueprint $table) {
                $table->renameColumn('page_id', 'entity_id');
            });
            Schema::rename('page_vertical', 'entity_vertical');
        }

        if (Schema::hasTable('page_relations')) {
            Schema::table('page_relations', function (Blueprint $table) {
                $table->renameColumn('source_page_id', 'source_entity_id');
                $table->renameColumn('related_page_id', 'related_entity_id');
            });
            Schema::rename('page_relations', 'entity_relations');
        }

        if (Schema::hasTable('snapshots') && Schema::hasColumn('snapshots', 'page_id')) {
            Schema::table('snapshots', function (Blueprint $table) {
                $table->renameColumn('page_id', 'entity_id');
            });
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->renameColumn('canonical_page_id', 'canonical_entity_id');
        });

        Schema::rename('pages', 'entities');
    }
};
