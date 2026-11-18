<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-job cover letter (optional / not needed) and a conversation thread of sent/received messages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->text('cover_letter')->nullable();
            $table->boolean('cover_letter_not_needed')->default(false);
            $table->timestamp('cover_letter_updated_at')->nullable();
        });

        Schema::create('job_application_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('direction', 16); // sent | received
            $table->timestamp('occurred_at');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['job_application_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_messages');

        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['cover_letter', 'cover_letter_not_needed', 'cover_letter_updated_at']);
        });
    }
};
