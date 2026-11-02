<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week_board_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('planned');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_board_items');
    }
};
