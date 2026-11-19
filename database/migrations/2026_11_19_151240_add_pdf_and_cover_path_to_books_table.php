<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (! Schema::hasColumn('books', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('started_on');
            }

            if (! Schema::hasColumn('books', 'cover_path')) {
                $table->string('cover_path')->nullable()->after('pdf_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['cover_path', 'pdf_path'],
                static fn (string $column): bool => Schema::hasColumn('books', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
