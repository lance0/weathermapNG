<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('wmng_maps')) {
            Schema::create('wmng_maps', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->string('title')->nullable();
                $t->text('description')->nullable();
                $t->unsignedInteger('width')->default(800);
                $t->unsignedInteger('height')->default(600);
                $t->json('options')->nullable(); // bg, thresholds, scale
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('wmng_nodes')) {
            Schema::create('wmng_nodes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('map_id')->constrained('wmng_maps')->onDelete('cascade');
                $t->string('label');
                $t->float('x');
                $t->float('y');
                $t->unsignedBigInteger('device_id')->nullable(); // LibreNMS device id
                $t->json('meta')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('wmng_links')) {
            Schema::create('wmng_links', function (Blueprint $t) {
                $t->id();
                $t->foreignId('map_id')->constrained('wmng_maps')->onDelete('cascade');
                $t->foreignId('src_node_id')->constrained('wmng_nodes');
                $t->foreignId('dst_node_id')->constrained('wmng_nodes');
                $t->unsignedBigInteger('port_id_a')->nullable();
                $t->unsignedBigInteger('port_id_b')->nullable();
                $t->unsignedBigInteger('bandwidth_bps')->nullable();
                $t->json('style')->nullable(); // stroke, width, label options
                $t->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('wmng_links');
        Schema::dropIfExists('wmng_nodes');
        Schema::dropIfExists('wmng_maps');
    }
};
