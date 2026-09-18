<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('hub'); // partners | assets
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['hub', 'slug']);
            $table->index(['hub', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
