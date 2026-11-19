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
        Schema::table('book_highlights', function (Blueprint $table) {
            if (! Schema::hasColumn('book_highlights', 'tags')) {
                $table->string('tags')->nullable()->after('color');
            }
            if (! Schema::hasColumn('book_highlights', 'last_reviewed_at')) {
                $table->timestamp('last_reviewed_at')->nullable()->after('tags');
            }
            if (! Schema::hasColumn('book_highlights', 'is_mastered')) {
                $table->boolean('is_mastered')->default(false)->after('last_reviewed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_highlights', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['is_mastered', 'last_reviewed_at', 'tags'],
                static fn (string $col): bool => Schema::hasColumn('book_highlights', $col),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
