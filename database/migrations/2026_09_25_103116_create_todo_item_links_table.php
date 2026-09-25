<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_item_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_item_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('url');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['todo_item_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_item_links');
    }
};
