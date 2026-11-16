<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('kind', 16)->default('book')->after('status');
            $table->index(['kind', 'status', 'position']);
        });

        DB::table('books')->where(function ($query) {
            $query->whereNull('kind')->orWhere('kind', '');
        })->update(['kind' => 'book']);
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['kind', 'status', 'position']);
            $table->dropColumn('kind');
        });
    }
};
