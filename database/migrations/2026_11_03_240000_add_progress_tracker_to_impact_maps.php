<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impact_maps', function (Blueprint $table) {
            $table->double('track_start')->nullable();
            $table->string('track_start_label', 100)->nullable();
            $table->double('track_target')->nullable();
            $table->string('track_target_label', 100)->nullable();
        });

        Schema::create('impact_map_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impact_map_id')->constrained()->cascadeOnDelete();
            $table->double('value');
            $table->date('date');
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['impact_map_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_map_checkins');
        Schema::table('impact_maps', function (Blueprint $table) {
            $table->dropColumn(['track_start', 'track_start_label', 'track_target', 'track_target_label']);
        });
    }
};
