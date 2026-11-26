<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Docmost
{
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (self::$available === null) {
            self::spaces();
        }

        return self::$available === true;
    }

    /**
     * @return Collection<int, object{name: string, slug: string}>
     */
    public static function spaces(): Collection
    {
        $ttl = (int) config('docmost.cache_ttl', 300);

        try {
            /** @var list<object{name: string, slug: string}> $rows */
            $rows = Cache::remember('docmost.spaces', $ttl, function () {
                return DB::connection('docmost')
                    ->table('milestones_spaces')
                    ->orderByRaw('lower(name)')
                    ->get(['name', 'slug'])
                    ->map(fn ($row) => [
                        'name' => (string) $row->name,
                        'slug' => (string) $row->slug,
                    ])
                    ->all();
            });

            self::$available = true;

            return collect($rows)->map(fn (array $row) => (object) $row);
        } catch (Throwable $e) {
            self::$available = false;
            Log::warning('Docmost spaces could not be loaded.', [
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @return list<array{title: string, url: string, icon: ?string}>
     */
    public static function topLevelPages(string $spaceSlug): array
    {
        $spaceSlug = trim($spaceSlug);
        if ($spaceSlug === '') {
            return [];
        }

        $ttl = (int) config('docmost.cache_ttl', 300);

        try {
            /** @var list<array{title: string, url: string, icon: ?string}> $pages */
            $pages = Cache::remember('docmost.pages.'.$spaceSlug, $ttl, function () use ($spaceSlug) {
                $rows = DB::connection('docmost')
                    ->table('milestones_top_level_pages')
                    ->where('space_slug', $spaceSlug)
                    ->orderByRaw('position COLLATE "C"')
                    ->get(['title', 'slug_id', 'icon']);

                return $rows->map(fn ($row) => [
                    'title' => self::pageTitle((string) ($row->title ?? '')),
                    'url' => self::pageUrl($spaceSlug, (string) ($row->title ?? ''), (string) $row->slug_id),
                    'icon' => filled($row->icon ?? null) ? (string) $row->icon : null,
                ])->all();
            });

            self::$available = true;

            return $pages;
        } catch (Throwable $e) {
            self::$available = false;
            Log::warning('Docmost pages could not be loaded.', [
                'space' => $spaceSlug,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public static function spaceUrl(string $spaceSlug): string
    {
        return rtrim((string) config('docmost.url'), '/').'/s/'.$spaceSlug;
    }

    public static function spaceName(string $spaceSlug): ?string
    {
        $space = self::spaces()->firstWhere('slug', $spaceSlug);

        return $space->name ?? null;
    }

    public static function pageUrl(string $spaceSlug, string $title, string $slugId): string
    {
        return rtrim((string) config('docmost.url'), '/').'/s/'.$spaceSlug.'/p/'.self::pagePathSlug($title, $slugId);
    }

    public static function pageTitle(string $title): string
    {
        $title = trim($title);

        return $title !== '' ? $title : 'Untitled';
    }

    /**
     * Approximate Docmost/sindresorhus slugify. Navigation depends on the
     * trailing slugId; the title slug is only for readable URLs.
     */
    public static function pagePathSlug(string $title, string $slugId): string
    {
        $title = mb_substr($title, 0, 70);
        $title = str_replace(['♥', '🦄'], '', $title);
        $title = strtr($title, [
            '&' => ' and ',
            '@' => ' at ',
            '%' => ' percent ',
            '+' => ' plus ',
        ]);

        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'untitled';
        }

        return $slug.'-'.$slugId;
    }
}
