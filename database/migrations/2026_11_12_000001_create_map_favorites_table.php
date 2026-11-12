<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Per-user map favorites. One row per (user, map); the unique key makes
     * toggling idempotent so double-clicks cannot create duplicates.
     */
    public function up()
    {
        if (!Schema::hasTable('wmng_map_favorites')) {
            Schema::create('wmng_map_favorites', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->index('user_id');
                $t->unsignedBigInteger('map_id');
                $t->foreign('map_id')->references('id')->on('wmng_maps')->onDelete('cascade');
                $t->unique(['user_id', 'map_id']);
                $t->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('wmng_map_favorites');
    }
};
