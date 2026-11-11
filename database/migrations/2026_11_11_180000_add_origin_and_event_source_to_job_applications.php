<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The applications page is now the tracker itself: rows can be created directly
 * (API or the page form), so record where a row came from and who made each change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            // 'sheet' (imported from the old Google Sheet), 'api' or 'manual'.
            $table->string('origin', 20)->default('sheet');
        });

        Schema::table('job_application_events', function (Blueprint $table) {
            // 'sync' (sheet import), 'api' or 'manual'.
            $table->string('source', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_application_events', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
