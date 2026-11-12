<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            // Scheduled finish day (optional). Shown on the Timeline with an end marker.
            $table->date('finish_on')->nullable()->after('started_on');
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->dropColumn('finish_on');
        });
    }
};
