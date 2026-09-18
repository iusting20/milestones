<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_items', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('title');
            $table->string('url')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['date', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_items');
    }
};
