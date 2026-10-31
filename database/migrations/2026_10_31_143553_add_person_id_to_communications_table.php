<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreignId('person_id')->nullable()->after('project_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Person-only entries have no project to restore, so a rollback removes them.
        DB::table('communications')->whereNull('project_id')->delete();

        Schema::table('communications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::table('communications', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });
    }
};
