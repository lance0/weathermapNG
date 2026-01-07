<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMapTemplatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('wmng_map_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('width')->default(800);
            $table->integer('height')->default(600);
            $table->json('config')->nullable();
            $table->string('icon')->default('fas fa-map');
            $table->string('category')->default('custom');
            $table->boolean('is_built_in')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wmng_map_templates');
    }
}
