<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('started_on');
            $table->date('completed_on')->nullable();
            $table->timestamps();

            $table->index(['started_on', 'completed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_items');
    }
};
