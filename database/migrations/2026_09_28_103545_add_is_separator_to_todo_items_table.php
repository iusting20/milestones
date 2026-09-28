<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->boolean('is_separator')->default(false)->after('is_completed');
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->dropColumn('is_separator');
            $table->string('title')->nullable(false)->change();
        });
    }
};
