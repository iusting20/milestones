<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->string('link_title')->nullable()->after('title');
            $table->string('link_url')->nullable()->after('link_title');
            $table->foreignId('project_id')->nullable()->after('link_url')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['link_title', 'link_url']);
        });
    }
};
