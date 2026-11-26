<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keep existing message times, and allow new thread entries to store a date without a time. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_application_messages', function (Blueprint $table) {
            $table->boolean('has_time')->default(true)->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_application_messages', function (Blueprint $table) {
            $table->dropColumn('has_time');
        });
    }
};
