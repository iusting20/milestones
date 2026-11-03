<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_maps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('impact_map_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impact_map_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('impact_map_nodes')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('title', 255);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['impact_map_id', 'parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_map_nodes');
        Schema::dropIfExists('impact_maps');
    }
};
