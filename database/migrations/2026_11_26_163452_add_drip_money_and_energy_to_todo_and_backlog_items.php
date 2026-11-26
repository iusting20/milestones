<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->string('drip_money', 8)->nullable()->after('drip_tag');
            $table->string('drip_energy', 8)->nullable()->after('drip_money');
        });

        Schema::table('backlog_items', function (Blueprint $table) {
            $table->string('drip_money', 8)->nullable()->after('drip_tag');
            $table->string('drip_energy', 8)->nullable()->after('drip_money');
        });

        $this->backfillAxes('todo_items');
        $this->backfillAxes('backlog_items');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->dropColumn(['drip_money', 'drip_energy']);
        });

        Schema::table('backlog_items', function (Blueprint $table) {
            $table->dropColumn(['drip_money', 'drip_energy']);
        });
    }

    private function backfillAxes(string $table): void
    {
        $map = [
            'D' => ['low', 'drains'],
            'R' => ['high', 'drains'],
            'I' => ['low', 'lights'],
            'P' => ['high', 'lights'],
        ];

        foreach ($map as $tag => [$money, $energy]) {
            DB::table($table)
                ->where('drip_tag', $tag)
                ->update([
                    'drip_money' => $money,
                    'drip_energy' => $energy,
                ]);
        }
    }
};
