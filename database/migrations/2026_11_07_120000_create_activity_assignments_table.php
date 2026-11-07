<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('hub'); // partners | assets
            $table->string('title');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['hub', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_assignments');
    }
};
