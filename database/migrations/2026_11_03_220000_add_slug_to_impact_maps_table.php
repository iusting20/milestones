<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impact_maps', function (Blueprint $table) {
            $table->string('slug', 64)->nullable()->after('id');
        });

        foreach (DB::table('impact_maps')->whereNull('slug')->pluck('id') as $id) {
            DB::table('impact_maps')->where('id', $id)->update(['slug' => Str::random(32)]);
        }

        Schema::table('impact_maps', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('impact_maps', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
