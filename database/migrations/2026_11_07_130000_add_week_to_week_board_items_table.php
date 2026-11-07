<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('week_board_items', function (Blueprint $table) {
            $table->string('week', 8)->nullable()->after('position');
            $table->index('week');
        });

        // Existing cards belong to the ISO week (display timezone) they were created in.
        DB::table('week_board_items')->orderBy('id')->get(['id', 'created_at'])->each(function ($row): void {
            $created = $row->created_at ? Carbon::parse($row->created_at, 'UTC') : Carbon::now();
            DB::table('week_board_items')->where('id', $row->id)->update([
                'week' => $created->timezone(config('app.display_timezone', 'Europe/Bucharest'))->format('o-\WW'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('week_board_items', function (Blueprint $table) {
            $table->dropIndex(['week']);
            $table->dropColumn('week');
        });
    }
};
