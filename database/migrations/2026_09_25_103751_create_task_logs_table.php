<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamp('started_at');
            $table->timestamp('finished_at');
            $table->timestamps();

            $table->index('finished_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_logs');
    }
};
