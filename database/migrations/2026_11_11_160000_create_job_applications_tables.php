<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->date('date_added')->nullable();
            $table->string('status')->nullable();
            $table->date('date_applied')->nullable();
            $table->string('company')->nullable();
            $table->string('role')->nullable();
            $table->string('type')->nullable();
            $table->string('rate')->nullable();
            $table->string('ir35')->nullable();
            $table->string('location')->nullable();
            $table->string('source')->nullable();
            $table->text('link')->nullable();
            $table->string('contact')->nullable();
            $table->text('next_step')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sheet_row')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('job_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
        });

        Schema::create('job_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('synced_at');
            $table->string('source')->nullable();
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('status_changes')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_sync_runs');
        Schema::dropIfExists('job_application_events');
        Schema::dropIfExists('job_applications');
    }
};
