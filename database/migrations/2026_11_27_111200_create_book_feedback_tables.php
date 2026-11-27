<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('book_feedback_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['book_id', 'kind', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_feedback_entries');
        Schema::dropIfExists('book_feedback');
    }
};
