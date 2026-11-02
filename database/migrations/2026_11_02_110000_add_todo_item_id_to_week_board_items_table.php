<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('week_board_items', function (Blueprint $table) {
            $table->foreignId('todo_item_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('week_board_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('todo_item_id');
        });
    }
};
