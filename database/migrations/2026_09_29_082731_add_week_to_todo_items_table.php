<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->date('date')->nullable()->change();
            $table->string('week', 8)->nullable()->after('date');

            $table->index(['week', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->dropIndex(['week', 'position']);
            $table->dropColumn('week');
            $table->date('date')->nullable(false)->change();
        });
    }
};
