<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\ReadingSession;
use App\Support\Clock;
use App\Support\Onboarding;
use App\Support\ReechTimelog;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reading stats')]
class ReadStats extends Component
{
    public const TYPE_COLORS = [
        'A' => '#3fb950',
        'B' => '#ffe600',
        'C' => '#58a6ff',
        'articles' => '#c084fc',
        'content' => '#ff6d00',
        'engagement' => '#ff6d00',
        '—' => '#8b8b8b',
    ];

    public function mount(): void
    {
        Onboarding::complete('read.stats');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            '—' => 'No type',
            'articles' => 'Articles',
            'content' => 'Content',
            'engagement' => 'Engagement',
            default => 'Type '.$type,
        };
    }

    /**
     * Minutes read per local day (Mon..Sun of the current week) and per book type / articles.
     * Sessions spanning midnight are split across days; a live session counts up to now.
     * Article time is kept in the "articles" category so book type totals stay unchanged.
     *
     * @return array{days: array<int, array{label: string, date: string, minutes: array<string, int>}>, totals: array<string, int>}
     */
    public static function weekBreakdown(?Carbon $now = null): array
    {
        $tz = Clock::timezone();
        $now = ($now ?? Clock::now())->copy()->timezone($tz);
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(7);

        $types = array_keys(self::TYPE_COLORS);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $weekStart->copy()->addDays($i);
            $days[$i] = [
                'label' => $d->format('D'),
                'date' => $d->format('j M'),
                'minutes' => array_fill_keys($types, 0),
                'seconds' => array_fill_keys($types, 0),
                'bookSeconds' => array_fill_keys($types, []),
            ];
        }

        $sessions = ReadingSession::query()
            ->with('book:id,title,type,kind,status,started_on')
            ->where('started_at', '<', $weekEnd->copy()->utc())
            ->where(function ($q) use ($weekStart) {
                $q->whereNull('finished_at')->orWhere('finished_at', '>', $weekStart->copy()->utc());
            })
            ->get();

        $bookSeconds = [];
        $bookInfo = [];
        foreach ($sessions as $session) {
            $type = self::categoryFor($session->book);
            $start = $session->started_at->copy()->timezone($tz);
            $end = ($session->finished_at ?? $now)->copy()->timezone($tz);
            if ($end->gt($now)) {
                $end = $now->copy();
            }
            for ($i = 0; $i < 7; $i++) {
                $dayStart = $weekStart->copy()->addDays($i);
                $dayEnd = $dayStart->copy()->addDay();
                $from = $start->gt($dayStart) ? $start : $dayStart;
                $to = $end->lt($dayEnd) ? $end : $dayEnd;
                if ($to->gt($from)) {
                    $secs = $from->diffInSeconds($to);
                    $days[$i]['seconds'][$type] += $secs;
                    $days[$i]['bookSeconds'][$type][$session->book_id] = ($days[$i]['bookSeconds'][$type][$session->book_id] ?? 0) + $secs;
                    $bookSeconds[$session->book_id] = ($bookSeconds[$session->book_id] ?? 0) + $secs;
                    $bookInfo[$session->book_id] = $session->book;
                }
            }
        }

        self::addReechMinutes($days, $weekStart, $weekEnd, $now, $tz);

        $totals = array_fill_keys($types, 0);
        foreach ($days as $i => $day) {
            foreach ($types as $t) {
                $m = intdiv((int) $day['seconds'][$t], 60);
                $days[$i]['minutes'][$t] = $m;
                $totals[$t] += $m;
            }
            $days[$i]['books'] = [];
            foreach ($types as $t) {
                $list = [];
                foreach ($day['bookSeconds'][$t] as $bookId => $secs) {
                    $m = intdiv((int) $secs, 60);
                    if ($m > 0) {
                        $info = is_int($bookId) ? ($bookInfo[$bookId] ?? null) : null;
                        $list[] = [
                            'title' => $info?->title ?? (is_string($bookId) ? $bookId : 'Deleted book'),
                            'minutes' => $m,
                            'startedToday' => $info?->isStartedToday($now) ?? false,
                            'finished' => $info?->isFinished() ?? false,
                        ];
                    }
                }
                usort($list, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
                $days[$i]['books'][$t] = $list;
            }
            unset($days[$i]['seconds'], $days[$i]['bookSeconds']);
        }

        $items = [];
        foreach ($bookSeconds as $id => $secs) {
            $minutes = intdiv((int) $secs, 60);
            if ($minutes <= 0) {
                continue;
            }
            $isArticle = ($bookInfo[$id]?->kind === Book::KIND_ARTICLE);
            $info = $bookInfo[$id] ?? null;
            $items[] = [
                'id' => $id,
                'title' => $info?->title ?? ($isArticle ? 'Deleted article' : 'Deleted book'),
                'type' => $isArticle ? 'articles' : $info?->type,
                'kind' => $isArticle ? Book::KIND_ARTICLE : Book::KIND_BOOK,
                'minutes' => $minutes,
                'startedToday' => $info?->isStartedToday($now) ?? false,
                'finished' => $info?->isFinished() ?? false,
            ];
        }
        usort($items, fn ($a, $b) => $b['minutes'] <=> $a['minutes'] ?: strcasecmp($a['title'], $b['title']));

        $books = array_values(array_filter($items, fn ($item) => $item['kind'] !== Book::KIND_ARTICLE));
        $articles = array_values(array_filter($items, fn ($item) => $item['kind'] === Book::KIND_ARTICLE));

        return ['days' => $days, 'totals' => $totals, 'books' => $books, 'articles' => $articles];
    }

    /**
     * Finished Reech content and engagement sessions, split across local days like reading time.
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    private static function addReechMinutes(array &$days, Carbon $weekStart, Carbon $weekEnd, Carbon $now, string $tz): void
    {
        $byTitle = [];
        foreach ((array) config('reech_timelog.titles', []) as $type => $title) {
            if (isset(self::TYPE_COLORS[$type]) && is_string($title)) {
                $byTitle[$title] = $type;
            }
        }

        $reech = ReechTimelog::forDays($weekStart->toDateString(), $weekEnd->copy()->subDay()->toDateString());

        foreach ($reech['logs'] as $log) {
            $type = $byTitle[$log->title] ?? null;
            if ($type === null || $log->started_at === null || $log->finished_at === null) {
                continue;
            }

            $start = $log->started_at->copy()->timezone($tz);
            $end = $log->finished_at->copy()->timezone($tz);
            if ($end->gt($now)) {
                $end = $now->copy();
            }

            for ($i = 0; $i < 7; $i++) {
                $dayStart = $weekStart->copy()->addDays($i);
                $dayEnd = $dayStart->copy()->addDay();
                $from = $start->gt($dayStart) ? $start : $dayStart;
                $to = $end->lt($dayEnd) ? $end : $dayEnd;
                if ($to->gt($from)) {
                    $secs = $from->diffInSeconds($to);
                    $days[$i]['seconds'][$type] += $secs;
                    $articleTitle = $log->getAttribute('reech_article_title');
                    $label = is_string($articleTitle) && $articleTitle !== '' ? $articleTitle : $log->title;
                    $days[$i]['bookSeconds'][$type][$label] = ($days[$i]['bookSeconds'][$type][$label] ?? 0) + $secs;
                }
            }
        }
    }

    public static function categoryFor(?Book $book): string
    {
        if ($book?->kind === Book::KIND_ARTICLE) {
            return 'articles';
        }

        return $book?->type && isset(self::TYPE_COLORS[$book->type]) ? $book->type : '—';
    }

    public static function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m.'m';
        }

        return $m === 0 ? $h.'h' : $h.'h '.$m.'m';
    }

    public function render()
    {
        $data = self::weekBreakdown();
        $types = array_keys(self::TYPE_COLORS);

        // Hide empty series that are not book types: untyped reading, and Reech when it logged nothing.
        foreach (['—', 'content', 'engagement'] as $optional) {
            if ($data['totals'][$optional] === 0) {
                $types = array_values(array_diff($types, [$optional]));
            }
        }

        $maxDay = 0;
        foreach ($data['days'] as $day) {
            foreach ($types as $t) {
                $maxDay = max($maxDay, $day['minutes'][$t]);
            }
        }

        $weekTotal = array_sum(array_intersect_key($data['totals'], array_flip($types)));

        // conic-gradient stops for the pie
        $stops = [];
        $acc = 0.0;
        foreach ($types as $t) {
            if ($weekTotal === 0 || $data['totals'][$t] === 0) {
                continue;
            }
            $pct = $data['totals'][$t] / $weekTotal * 100;
            $stops[] = self::TYPE_COLORS[$t].' '.round($acc, 3).'% '.round($acc + $pct, 3).'%';
            $acc += $pct;
        }

        return view('livewire.read-stats', [
            'days' => $data['days'],
            'totals' => $data['totals'],
            'books' => $data['books'],
            'articles' => $data['articles'],
            'types' => $types,
            'colors' => self::TYPE_COLORS,
            'maxDay' => max(1, $maxDay),
            'weekTotal' => $weekTotal,
            'pieGradient' => $stops ? 'conic-gradient('.implode(', ', $stops).')' : null,
            'today' => Clock::now()->timezone(Clock::timezone())->format('D'),
        ]);
    }
}
