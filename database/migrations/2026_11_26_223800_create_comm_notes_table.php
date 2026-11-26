<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->date('note_date');
            $table->timestamps();

            $table->index(['project_id', 'note_date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_notes');
    }
};
