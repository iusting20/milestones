<?php

use App\Support\JobApplicationWriter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Split applications into software / ai categories, backfilled from role titles. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('category', 20)->default('software');
            $table->index('category');
        });

        foreach (DB::table('job_applications')->orderBy('id')->get(['id', 'role']) as $row) {
            $category = JobApplicationWriter::inferCategory($row->role);
            if ($category !== JobApplicationWriter::DEFAULT_CATEGORY) {
                DB::table('job_applications')->where('id', $row->id)->update(['category' => $category]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
