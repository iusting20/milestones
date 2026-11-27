<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_feedback_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['book_id', 'position']);
        });

        Schema::create('book_feedback_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('book_feedback_sections')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['book_id', 'section_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_feedback_ideas');
        Schema::dropIfExists('book_feedback_sections');
    }
};
