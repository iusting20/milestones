<?php

namespace App\Console\Commands;

use App\Models\Link;
use App\Support\LeadingEmoji;
use Illuminate\Console\Command;

class BackfillLinkIconsFromTitles extends Command
{
    protected $signature = 'links:backfill-icons-from-titles';

    protected $description = 'Move leading title emojis into the icon field for links that have no icon yet';

    public function handle(): int
    {
        $updated = 0;
        $rows = [];

        Link::query()
            ->where(function ($query) {
                $query->whereNull('icon')->orWhere('icon', '');
            })
            ->where('is_delimiter', false)
            ->orderBy('id')
            ->each(function (Link $link) use (&$updated, &$rows) {
                $split = LeadingEmoji::split($link->title);

                if ($split === null || $split['title'] === '') {
                    return;
                }

                $beforeIcon = $link->icon;
                $beforeTitle = $link->title;

                $link->update([
                    'icon' => mb_substr($split['emoji'], 0, 16),
                    'title' => $split['title'],
                ]);

                $updated++;
                $rows[] = [
                    'id' => $link->id,
                    'before_icon' => $beforeIcon,
                    'before_title' => $beforeTitle,
                    'after_icon' => $link->icon,
                    'after_title' => $link->title,
                ];

                $this->line(sprintf(
                    '#%d: [%s] %s  ->  [%s] %s',
                    $link->id,
                    $beforeIcon ?? '',
                    $beforeTitle,
                    $link->icon,
                    $link->title,
                ));
            });

        $this->info("Updated {$updated} link(s).");

        // JSON summary for agent reporting
        $this->newLine();
        $this->line(json_encode(['updated' => $updated, 'changes' => $rows], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
