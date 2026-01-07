<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMapVersionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('wmng_map_versions', function (Blueprint $table) {
            $table->id();
            $table->integer('map_id');
            $table->foreign('map_id')->references('id')->on('wmng_maps')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->text('config_snapshot')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at');
            $table->index(['map_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wmng_map_versions');
    }
}
