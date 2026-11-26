<?php

namespace App\Livewire;

use App\Enums\WeekBoardStatus;
use App\Models\Book;
use App\Models\ReadingSession;
use App\Models\RoadmapItem;
use App\Models\TaskLog;
use App\Models\TodoItem;
use App\Models\WeekBoardItem;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Stats')]
class ActivityStats extends Component
{
    /** month = the twelve months of $year. year = one row per calendar year. */
    #[Url(as: 'period', history: true, except: 'month')]
    public string $period = 'month';

    #[Url(as: 'year', history: true, except: '')]
    public string $year = '';

    public function mount(): void
    {
        $this->constrain();
    }

    public function updatedPeriod(): void
    {
        $this->constrain();
    }

    public function updatedYear(): void
    {
        $this->constrain();
    }

    public function showMonths(): void
    {
        $this->period = 'month';
        $this->constrain();
    }

    public function showYears(): void
    {
        $this->period = 'year';
        $this->year = '';
    }

    public function previousYear(): void
    {
        $this->year = (string) (((int) $this->yearKey()) - 1);
    }

    public function nextYear(): void
    {
        $next = ((int) $this->yearKey()) + 1;

        if ($next > (int) Clock::now()->year) {
            return;
        }

        $this->year = (string) $next;
    }

    public function yearKey(): string
    {
        $year = (int) $this->year;
        $current = (int) Clock::now()->year;

        if ($year < 2000 || $year > $current) {
            return (string) $current;
        }

        return (string) $year;
    }

    public function isCurrentYear(): bool
    {
        return (int) $this->yearKey() === (int) Clock::now()->year;
    }

    private function constrain(): void
    {
        if (! in_array($this->period, ['month', 'year'], true)) {
            $this->period = 'month';
        }

        $this->year = $this->period === 'month' && $this->yearKey() === (string) ((int) Clock::now()->year)
            ? ''
            : $this->yearKey();

        if ($this->period === 'year') {
            $this->year = '';
        }
    }

    /**
     * @return array{period: string, year: int, rows: list<array{key: string, label: string, books: int, readMinutes: int, readDuration: string, milestones: int, loggedMinutes: int, loggedDuration: string, timelines: int, weekBoard: int}>}
     */
    public function breakdown(): array
    {
        $tz = Clock::timezone();
        $now = Clock::now();
        $counts = $this->emptyCounts();

        $this->tallyBooks($counts, $tz);
        $this->tallyReading($counts, $tz);
        $this->tallyMilestones($counts);
        $this->tallyLogged($counts, $tz);
        $this->tallyTimelines($counts);
        $this->tallyWeekBoard($counts);

        $period = $this->period === 'year' ? 'year' : 'month';
        $year = (int) $this->yearKey();
        $rows = [];

        if ($period === 'month') {
            for ($month = 1; $month <= 12; $month++) {
                if ($year === (int) $now->year && $month > (int) $now->month) {
                    break;
                }

                $key = sprintf('%04d-%02d', $year, $month);
                $rows[] = $this->row($key, Carbon::create($year, $month, 1)->format('M'), $counts[$key] ?? $this->zero());
            }
        } else {
            $years = collect(array_keys($counts))
                ->map(fn (string $key): int => (int) substr($key, 0, 4))
                ->push((int) $now->year)
                ->unique()
                ->sort()
                ->values();

            foreach ($years as $y) {
                $sum = $this->zero();
                foreach ($counts as $key => $bucket) {
                    if (str_starts_with($key, $y.'-')) {
                        foreach ($sum as $field => $value) {
                            $sum[$field] += $bucket[$field];
                        }
                    }
                }
                $rows[] = $this->row((string) $y, (string) $y, $sum);
            }
        }

        return ['period' => $period, 'year' => $year, 'rows' => $rows];
    }

    /**
     * @param  array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>  $counts
     */
    private function tallyBooks(array &$counts, string $tz): void
    {
        Book::query()
            ->where('kind', Book::KIND_BOOK)
            ->where('status', Book::STATUS_FINISHED)
            ->with(['readingSessions' => fn ($query) => $query->whereNotNull('finished_at')->orderByDesc('finished_at')])
            ->get()
            ->each(function (Book $book) use (&$counts, $tz): void {
                $finished = $book->readingSessions->first()?->finished_at ?? $book->updated_at;
                $this->bump($counts, $finished->copy()->timezone($tz)->format('Y-m'), 'books');
            });
    }

    /**
     * @param  array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>  $counts
     */
    private function tallyReading(array &$counts, string $tz): void
    {
        ReadingSession::query()
            ->whereHas('book', fn ($query) => $query->where('kind', Book::KIND_BOOK))
            ->whereNotNull('finished_at')
            ->get()
            ->each(function (ReadingSession $session) use (&$counts, $tz): void {
                if ($session->started_at === null || $session->finished_at === null) {
                    return;
                }

                $minutes = max(0, (int) $session->started_at->diffInMinutes($session->finished_at));
                $this->bump($counts, $session->started_at->copy()->timezone($tz)->format('Y-m'), 'readMinutes', $minutes);
            });
    }

    /**
     * @param  array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>  $counts
     */
    private function tallyMilestones(array &$counts): void
    {
        TodoItem::query()
            ->where('is_completed', true)
            ->where('is_separator', false)
            ->whereNull('parent_id')
            ->get(['date'])
            ->each(function (TodoItem $item) use (&$counts): void {
                $this->bump($counts, $item->date->format('Y-m'), 'milestones');
            });
    }

    /**
     * @param  array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>  $counts
     */
    private function tallyLogged(array &$counts, string $tz): void
    {
        TaskLog::query()
            ->whereNotNull('finished_at')
            ->get(['started_at', 'finished_at'])
            ->each(function (TaskLog $log) use (&$counts, $tz): void {
                if ($log->started_at === null) {
                    return;
                }

                $minutes = max(0, (int) $log->started_at->diffInMinutes($log->finished_at));
                $this->bump($counts, $log->finished_at->copy()->timezone($tz)->format('Y-m'), 'loggedMinutes', $minutes);
            });
    }

    /**
     * @param  array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>  $counts
     */
    private function tallyTimelines(array &$counts): void
    {
        RoadmapItem::query()
            ->whereNotNull('completed_on')
            ->get(['completed_on'])
            ->each(function (RoadmapItem $item) use (&$counts): void {
                $this->bump($counts, $item->completed_on->format('Y-m'), 'timelines');
            });
    }

    /**
     * @param  array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>  $counts
     */
    private function tallyWeekBoard(array &$counts): void
    {
        WeekBoardItem::query()
            ->where('status', WeekBoardStatus::Done)
            ->get(['week'])
            ->each(function (WeekBoardItem $item) use (&$counts): void {
                $week = WeekBoardItem::normalizeWeek($item->week);
                if ($week === null) {
                    return;
                }

                $this->bump($counts, WeekBoardItem::weekMonday($week)->format('Y-m'), 'weekBoard');
            });
    }

    /**
     * @return array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>
     */
    private function emptyCounts(): array
    {
        return [];
    }

    /**
     * @return array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}
     */
    private function zero(): array
    {
        return [
            'books' => 0,
            'readMinutes' => 0,
            'milestones' => 0,
            'loggedMinutes' => 0,
            'timelines' => 0,
            'weekBoard' => 0,
        ];
    }

    /**
     * @param  array<string, array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}>  $counts
     */
    private function bump(array &$counts, string $key, string $field, int $by = 1): void
    {
        $counts[$key] ??= $this->zero();
        $counts[$key][$field] += $by;
    }

    /**
     * @param  array{books: int, readMinutes: int, milestones: int, loggedMinutes: int, timelines: int, weekBoard: int}  $bucket
     * @return array{key: string, label: string, books: int, readMinutes: int, readDuration: string, milestones: int, loggedMinutes: int, loggedDuration: string, timelines: int, weekBoard: int}
     */
    private function row(string $key, string $label, array $bucket): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'books' => $bucket['books'],
            'readMinutes' => $bucket['readMinutes'],
            'readDuration' => $this->formatDuration($bucket['readMinutes']),
            'milestones' => $bucket['milestones'],
            'loggedMinutes' => $bucket['loggedMinutes'],
            'loggedDuration' => $this->formatDuration($bucket['loggedMinutes']),
            'timelines' => $bucket['timelines'],
            'weekBoard' => $bucket['weekBoard'],
        ];
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return $rest.'m';
        }

        if ($rest === 0) {
            return $hours.'h';
        }

        return $hours.'h '.$rest.'m';
    }

    public function render(): View
    {
        return view('livewire.activity-stats', [
            'breakdown' => $this->breakdown(),
        ]);
    }
}
