<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impact_maps', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('client');
        });

        // Client group order on the list page ('' = "No client / project").
        Schema::create('impact_map_groups', function (Blueprint $table) {
            $table->id();
            $table->string('client', 255)->unique();
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // Keep the current look: most recently updated first within each group.
        $i = [];
        foreach (DB::table('impact_maps')->orderByDesc('updated_at')->get(['id', 'client']) as $m) {
            $k = (string) $m->client;
            $i[$k] = ($i[$k] ?? -1) + 1;
            DB::table('impact_maps')->where('id', $m->id)->update(['position' => $i[$k]]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_map_groups');
        Schema::table('impact_maps', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
