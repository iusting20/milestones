<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\ReadingSession;
use App\Support\Clock;
use App\Support\WeekLabel;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class ReadBookStatsModal extends Component
{
    /** ISO week used for the "this week" total; empty means the current week. */
    public ?string $week = '';

    /** Book or article opened in the stats detail modal. */
    public ?int $viewingBookId = null;

    /** Calendar month shown in the modal heatmap, "Y-m". */
    public string $heatmapMonth = '';

    public function weekKey(): string
    {
        $normalized = ReadStats::normalizedWeekKey($this->week ?? '');
        $current = Clock::currentWeek();

        if ($normalized === null || $normalized > $current) {
            return $current;
        }

        return $normalized;
    }

    public function isCurrentWeek(): bool
    {
        return $this->weekKey() === Clock::currentWeek();
    }

    public function weekStart(): Carbon
    {
        $key = $this->weekKey();
        [$year, $number] = [(int) substr($key, 0, 4), (int) substr($key, -2)];

        return Clock::now()->setISODate($year, $number)->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    #[On('open-book-stats')]
    public function openBook(int $id, ?string $week = null): void
    {
        if (! Book::query()->whereKey($id)->exists()) {
            return;
        }

        if ($week !== null) {
            $this->week = $week;
        }

        $this->viewingBookId = $id;
        $this->heatmapMonth = $this->weekStart()->format('Y-m');
    }

    public function closeBook(): void
    {
        $this->viewingBookId = null;
        $this->heatmapMonth = '';
    }

    public function previousHeatmapMonth(): void
    {
        if ($this->viewingBookId === null) {
            return;
        }

        $this->heatmapMonth = $this->heatmapMonthStart()->copy()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextHeatmapMonth(): void
    {
        if ($this->viewingBookId === null || $this->isCurrentHeatmapMonth()) {
            return;
        }

        $next = $this->heatmapMonthStart()->copy()->addMonthNoOverflow()->format('Y-m');
        $current = Clock::now()->format('Y-m');
        $this->heatmapMonth = $next >= $current ? $current : $next;
    }

    public function isCurrentHeatmapMonth(): bool
    {
        return $this->heatmapMonthStart()->format('Y-m') === Clock::now()->format('Y-m');
    }

    public function heatmapMonthStart(): Carbon
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $this->heatmapMonth, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return Clock::now()->setDate($year, $month, 1)->startOfDay();
            }
        }

        return $this->weekStart()->startOfMonth()->startOfDay();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function viewingBook(?Carbon $now = null): ?array
    {
        if ($this->viewingBookId === null) {
            return null;
        }

        $book = Book::query()->with('earliestSession')->whereKey($this->viewingBookId)->first();
        if ($book === null) {
            return null;
        }

        $tz = Clock::timezone();
        $now = ($now ?? Clock::now())->copy()->timezone($tz);
        $weekStart = $this->weekStart();
        $weekEnd = $weekStart->copy()->addDays(7);
        $sessions = ReadingSession::query()
            ->where('book_id', $book->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        $allMinutes = self::overlapMinutesForSessions($sessions, Carbon::parse('1970-01-01', $tz)->startOfDay(), $now->copy(), $now, $tz);
        $weekMinutes = self::overlapMinutesForSessions($sessions, $weekStart, $weekEnd, $now, $tz);
        $byDay = self::minutesByLocalDay($sessions, $now, $tz);

        $startDate = $book->startDate();
        $latest = $sessions->sortByDesc(fn (ReadingSession $s) => $s->started_at?->timestamp ?? 0)->first();
        $lastAt = null;
        $lastRunning = false;
        if ($latest) {
            $lastRunning = $latest->isRunning();
            $lastAt = $lastRunning
                ? $latest->started_at->copy()->timezone($tz)
                : ($latest->finished_at ?? $latest->started_at)->copy()->timezone($tz);
            if ($lastAt->gt($now)) {
                $lastAt = $now->copy();
            }
        }

        $monthStart = $this->heatmapMonthStart()->copy()->timezone($tz)->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);
        $weekMonday = $weekStart->toDateString();
        $weekSunday = $weekStart->copy()->addDays(6)->toDateString();
        $today = $now->toDateString();
        $maxDay = 0;
        for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
            $maxDay = max($maxDay, $byDay[$d->toDateString()] ?? 0);
        }

        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $ymd = $cursor->toDateString();
                $inMonth = $cursor->month === $monthStart->month;
                $minutes = $inMonth ? ($byDay[$ymd] ?? 0) : 0;
                $week[] = [
                    'ymd' => $ymd,
                    'day' => (int) $cursor->format('j'),
                    'inMonth' => $inMonth,
                    'isToday' => $ymd === $today,
                    'inWeek' => $ymd >= $weekMonday && $ymd <= $weekSunday,
                    'minutes' => $minutes,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $color = ReadStats::TYPE_COLORS[$book->isArticle() ? 'articles' : (($book->type && isset(ReadStats::TYPE_COLORS[$book->type])) ? $book->type : '—')];
        $glyph = $book->isFinished() ? ['startedToday' => false] : self::startedGlyph($book, $weekStart, $now);

        $sessionRows = [];
        foreach ($sessions as $session) {
            $start = $session->started_at->copy()->timezone($tz);
            $running = $session->isRunning();
            $end = $running ? $now->copy() : $session->finished_at?->copy()->timezone($tz);
            if ($end && $end->gt($now)) {
                $end = $now->copy();
            }
            $minutes = 0;
            if ($end && $end->gt($start)) {
                $minutes = intdiv($start->diffInSeconds($end), 60);
            }
            $sessionRows[] = [
                'id' => $session->id,
                'started' => $start->format('D j M Y, H:i'),
                'finished' => ($running || ! $end) ? null : $end->format('H:i'),
                'nextDay' => $end !== null && ! $running && $end->toDateString() !== $start->toDateString(),
                'running' => $running,
                'minutes' => $minutes,
            ];
        }

        return [
            'id' => $book->id,
            'title' => $book->title,
            'kind' => $book->kind,
            'color' => $color,
            'finished' => $book->isFinished(),
            'startedOn' => $startDate ? Carbon::parse($startDate, $tz)->format('j M Y') : null,
            'startedAgo' => $startDate ? self::daysAgoLabel($startDate, $now) : null,
            'isArticle' => $book->isArticle(),
            'type' => $book->type ?: null,
            'lastReadOn' => $lastAt?->format('D j M Y, H:i'),
            'lastReadAgo' => $lastAt ? Read::relativeDay($lastAt) : null,
            'lastReadRunning' => $lastRunning,
            'totalMinutes' => $allMinutes,
            'weekMinutes' => $weekMinutes,
            'weekDays' => self::weekDaysInRange($sessions, $weekStart, $weekEnd, $tz),
            'weekLabel' => $this->isCurrentWeek() ? 'Read this week' : WeekLabel::range($weekStart),
            'sessions' => $sessionRows,
            'heatmap' => [
                'label' => $monthStart->format('M Y'),
                'month' => $monthStart->format('Y-m'),
                'isCurrentMonth' => $this->isCurrentHeatmapMonth(),
                'maxDay' => max(1, $maxDay),
                'color' => $color,
                'weeks' => $weeks,
            ],
            ...$glyph,
        ];
    }

    public static function daysAgoLabel(string $ymd, ?Carbon $now = null): string
    {
        $tz = Clock::timezone();
        $days = (int) Carbon::parse($ymd, $tz)->startOfDay()
            ->diffInDays(($now ?? Clock::now())->copy()->timezone($tz)->startOfDay(), false);

        if ($days <= 0) {
            return 'today';
        }
        if ($days === 1) {
            return 'yesterday';
        }

        return $days.' days ago';
    }

    /**
     * @param  iterable<int, ReadingSession>  $sessions
     * @return array<string, int> Y-m-d => minutes
     */
    public static function minutesByLocalDay(iterable $sessions, Carbon $now, string $tz): array
    {
        $byDay = [];
        foreach (self::clippedSegments($sessions, null, null, $now, $tz) as [$from, $to]) {
            $cursor = $from->copy()->startOfDay();
            while ($cursor->lt($to)) {
                $dayEnd = $cursor->copy()->addDay();
                $segStart = $from->gt($cursor) ? $from : $cursor;
                $segEnd = $to->lt($dayEnd) ? $to : $dayEnd;
                if ($segEnd->gt($segStart)) {
                    $ymd = $cursor->toDateString();
                    $byDay[$ymd] = ($byDay[$ymd] ?? 0) + intdiv($segStart->diffInSeconds($segEnd), 60);
                }
                $cursor = $dayEnd;
            }
        }

        return $byDay;
    }

    /**
     * @param  iterable<int, ReadingSession>  $sessions
     */
    public static function overlapMinutesForSessions(iterable $sessions, Carbon $from, Carbon $to, Carbon $now, string $tz): int
    {
        $total = 0;
        foreach (self::clippedSegments($sessions, $from, $to, $now, $tz) as [$start, $end]) {
            $total += $start->diffInSeconds($end);
        }

        return intdiv($total, 60);
    }

    /**
     * @param  iterable<int, ReadingSession>  $sessions
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private static function clippedSegments(iterable $sessions, ?Carbon $from, ?Carbon $to, Carbon $now, string $tz): array
    {
        $now = $now->copy()->timezone($tz);
        $from = $from?->copy()->timezone($tz);
        $to = $to?->copy()->timezone($tz);
        $segments = [];

        foreach ($sessions as $session) {
            if ($session->started_at === null) {
                continue;
            }
            $start = $session->started_at->copy()->timezone($tz);
            $end = ($session->finished_at ?? $now)->copy()->timezone($tz);
            if ($end->gt($now)) {
                $end = $now->copy();
            }
            if ($from !== null && $start->lt($from)) {
                $start = $from->copy();
            }
            if ($to !== null && $end->gt($to)) {
                $end = $to->copy();
            }
            if ($end->gt($start)) {
                $segments[] = [$start, $end];
            }
        }

        return $segments;
    }

    /**
     * @param  iterable<int, ReadingSession>  $sessions
     */
    private static function weekDaysInRange(iterable $sessions, Carbon $weekStart, Carbon $weekEnd, string $tz): string
    {
        return collect($sessions)
            ->filter(function (ReadingSession $session) use ($weekStart, $weekEnd, $tz) {
                if ($session->started_at === null) {
                    return false;
                }
                $local = $session->started_at->copy()->timezone($tz);

                return $local->gte($weekStart) && $local->lt($weekEnd);
            })
            ->map(fn (ReadingSession $session) => $session->started_at->copy()->timezone($tz))
            ->sortBy(fn (Carbon $day) => $day->dayOfWeekIso)
            ->map(fn (Carbon $day) => $day->format('D'))
            ->unique()
            ->implode(', ');
    }

    public static function hexAlpha(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 'rgba(255,255,255,'.round($alpha, 3).')';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return 'rgba('.$r.', '.$g.', '.$b.', '.round(max(0, min(1, $alpha)), 3).')';
    }

    private static function startedGlyph(?Book $book, Carbon $weekStart, Carbon $now): array
    {
        if ($book === null || $book->isFinished() || ! $book->isStartedInWeek($weekStart)) {
            return ['startedToday' => false];
        }

        $today = $now->copy()->timezone(Clock::timezone())->toDateString();
        $label = $book->startDate() === $today ? 'Started today' : 'Started this week';

        return ['startedToday' => true, 'startedLabel' => $label];
    }

    public function render()
    {
        return view('livewire.read-book-stats-modal', [
            'viewingBook' => $this->viewingBook(),
        ]);
    }
}
