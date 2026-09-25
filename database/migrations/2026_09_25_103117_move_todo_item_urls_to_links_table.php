<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $items = DB::table('todo_items')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->orderBy('id')
            ->get(['id', 'url', 'link_title']);

        foreach ($items as $item) {
            $title = trim((string) ($item->link_title ?? ''));

            DB::table('todo_item_links')->insert([
                'todo_item_id' => $item->id,
                'title' => $title !== '' ? $title : 'Link',
                'url' => $item->url,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('todo_items', function (Blueprint $table) {
            $table->dropColumn(['url', 'link_title']);
        });
    }

    public function down(): void
    {
        Schema::table('todo_items', function (Blueprint $table) {
            $table->string('url')->nullable()->after('title');
            $table->string('link_title')->nullable()->after('url');
        });

        $links = DB::table('todo_item_links')
            ->orderBy('todo_item_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $restored = [];

        foreach ($links as $link) {
            if (isset($restored[$link->todo_item_id])) {
                continue;
            }

            DB::table('todo_items')
                ->where('id', $link->todo_item_id)
                ->update([
                    'url' => $link->url,
                    'link_title' => $link->title,
                ]);

            $restored[$link->todo_item_id] = true;
        }

        DB::table('todo_item_links')->delete();
    }
};
