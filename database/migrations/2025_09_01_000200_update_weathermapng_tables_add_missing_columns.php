<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class {
    public function up()
    {
        // wmng_maps: add commonly missing columns when upgrading from early installs
        if (Schema::hasTable('wmng_maps')) {
            Schema::table('wmng_maps', function (Blueprint $t) {
                if (!Schema::hasColumn('wmng_maps', 'title')) {
                    $t->string('title')->nullable()->after('name');
                }
                if (!Schema::hasColumn('wmng_maps', 'description')) {
                    $t->text('description')->nullable()->after('title');
                }
                if (!Schema::hasColumn('wmng_maps', 'width')) {
                    $t->unsignedInteger('width')->default(800);
                }
                if (!Schema::hasColumn('wmng_maps', 'height')) {
                    $t->unsignedInteger('height')->default(600);
                }
                if (!Schema::hasColumn('wmng_maps', 'options')) {
                    $t->json('options')->nullable();
                }
            });
        }

        // wmng_nodes: ensure device_id/meta exist
        if (Schema::hasTable('wmng_nodes')) {
            Schema::table('wmng_nodes', function (Blueprint $t) {
                if (!Schema::hasColumn('wmng_nodes', 'device_id')) {
                    $t->unsignedBigInteger('device_id')->nullable();
                }
                if (!Schema::hasColumn('wmng_nodes', 'meta')) {
                    $t->json('meta')->nullable();
                }
            });
        }

        // wmng_links: ensure optional columns exist
        if (Schema::hasTable('wmng_links')) {
            Schema::table('wmng_links', function (Blueprint $t) {
                if (!Schema::hasColumn('wmng_links', 'port_id_a')) {
                    $t->unsignedBigInteger('port_id_a')->nullable();
                }
                if (!Schema::hasColumn('wmng_links', 'port_id_b')) {
                    $t->unsignedBigInteger('port_id_b')->nullable();
                }
                if (!Schema::hasColumn('wmng_links', 'bandwidth_bps')) {
                    $t->unsignedBigInteger('bandwidth_bps')->nullable();
                }
                if (!Schema::hasColumn('wmng_links', 'style')) {
                    $t->json('style')->nullable();
                }
            });
        }
    }

    public function down()
    {
        // No-op: conditionally added columns are safe to keep.
        // If needed, they can be dropped manually on downgrade.
    }
};

