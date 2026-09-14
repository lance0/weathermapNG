<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add parent_map_id to wmng_maps for nested maps / drill-down hierarchy.
     * Also allows a wmng_node to point at a sub-map instead of a device
     * (node->meta['sub_map_id']), but the column itself lives on maps so a
     * parent's breadcrumb can be resolved in a single query.
     */
    public function up()
    {
        if (Schema::hasTable('wmng_maps') && !Schema::hasColumn('wmng_maps', 'parent_map_id')) {
            Schema::table('wmng_maps', function (Blueprint $t) {
                $t->unsignedBigInteger('parent_map_id')->nullable()->after('description');
                $t->index('parent_map_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('wmng_maps') && Schema::hasColumn('wmng_maps', 'parent_map_id')) {
            Schema::table('wmng_maps', function (Blueprint $t) {
                // Order matters on some engines; drop the index first.
                try {
                    $t->dropIndex(['parent_map_id']);
                } catch (\Throwable $e) {
                    // SQLite and some MySQL versions name the index differently; dropping the column removes it anyway.
                }
                $t->dropColumn('parent_map_id');
            });
        }
    }
};
