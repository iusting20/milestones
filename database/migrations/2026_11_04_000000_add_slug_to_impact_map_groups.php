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
        Schema::table('impact_map_groups', function (Blueprint $table) {
            $table->string('slug', 64)->nullable()->unique();
        });

        foreach (DB::table('impact_map_groups')->whereNull('slug')->pluck('id') as $id) {
            DB::table('impact_map_groups')->where('id', $id)->update(['slug' => Str::random(32)]);
        }

        // Clients without a group row yet (keep their current alphabetical place after ordered groups).
        $known = DB::table('impact_map_groups')->pluck('client')->all();
        $max = (int) DB::table('impact_map_groups')->max('position');
        $missing = DB::table('impact_maps')->whereNotNull('client')->where('client', '!=', '')->distinct()->pluck('client')
            ->reject(fn ($c) => in_array($c, $known, true))->sortBy(fn ($c) => mb_strtolower($c));
        foreach ($missing as $client) {
            DB::table('impact_map_groups')->insert(['client' => $client, 'position' => ++$max, 'slug' => Str::random(32), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('impact_map_groups', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
