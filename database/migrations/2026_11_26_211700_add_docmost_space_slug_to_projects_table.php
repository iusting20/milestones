<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private array $prefills = [
        'assets' => [
            'gi-jewellery' => 'GJ',
            'aulastory-cu-sam' => 'SBA',
            'la-barlog-cu-robert' => 'BRLG',
            'summariser' => 'SU',
            'neo-wars' => 'NW',
        ],
        'partners' => [
            'tudia' => 'tudia',
            'saints-nearby' => 'SN',
            'vibrant-health-edu' => 'VHE',
            'springs' => 'springs',
            'cosmic' => 'cosmic',
            'hacking-health-soraya' => 'HH',
        ],
    ];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('docmost_space_slug')->nullable()->after('track_comms');
        });

        foreach ($this->prefills as $hub => $slugs) {
            foreach ($slugs as $projectSlug => $spaceSlug) {
                DB::table('projects')
                    ->where('hub', $hub)
                    ->where('slug', $projectSlug)
                    ->update(['docmost_space_slug' => $spaceSlug]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('docmost_space_slug');
        });
    }
};
