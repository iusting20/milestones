<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author')->nullable();
            $table->unsignedInteger('total_pages')->nullable();
            $table->string('status', 20)->default('reading');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['status', 'position']);
        });

        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('pages_read')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'started_at']);
            $table->index('finished_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_sessions');
        Schema::dropIfExists('books');
    }
};
