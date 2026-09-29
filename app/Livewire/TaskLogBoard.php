<?php

namespace App\Livewire;

use App\Models\TaskLog;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Log')]
class TaskLogBoard extends Component
{
    /**
     * @var list<string>
     */
    private const PIE_COLORS = [
        '#58a6ff',
        '#ff9800',
        '#e91e63',
        '#9c27b0',
        '#3f51b5',
        '#00bcd4',
        '#ffc107',
        '#ff5722',
        '#2196f3',
        '#f06292',
        '#7e57c2',
        '#5c6bc0',
        '#29b6f6',
        '#26c6da',
        '#ffa726',
        '#ef5350',
        '#ab47bc',
        '#42a5f5',
        '#ec407a',
        '#8d6e63',
        '#78909c',
        '#ff7043',
        '#7986cb',
        '#039be5',
    ];

    #[Url(as: 'week', history: true)]
    public string $week = '';

    public bool $showAddModal = false;

    public string $logDate = '';

    public string $todoItemId = '';

    public string $startedAt = '';

    public string $finishedAt = '';

    public function mount(): void
    {
        if ($this->week === '' || ! $this->parseWeek($this->week)) {
            $this->week = now()->format('o-\WW');
        }
    }

    public function previousWeek(): void
    {
        $this->week = $this->weekStart()->copy()->subWeek()->format('o-\WW');
    }

    public function nextWeek(): void
    {
        $this->week = $this->weekStart()->copy()->addWeek()->format('o-\WW');
    }

    public function goToToday(): void
    {
        $this->week = now()->format('o-\WW');
    }

    public function openAddModal(): void
    {
        $this->resetValidation();
        $this->logDate = $this->defaultLogDate();
        $this->todoItemId = '';
        $this->startedAt = '';
        $this->finishedAt = '';
        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
        $this->logDate = '';
        $this->todoItemId = '';
        $this->startedAt = '';
        $this->finishedAt = '';
        $this->resetValidation();
    }

    public function selectLogDate(string $date): void
    {
        if (! $this->selectableLogDates()->contains($date)) {
            return;
        }

        $this->logDate = $date;
        $this->todoItemId = '';
        $this->resetValidation();
    }

    public function addLog(): void
    {
        $this->startedAt = $this->normalizeTime($this->startedAt);
        $this->finishedAt = $this->normalizeTime($this->finishedAt);

        $selectableDates = $this->selectableLogDates()->all();

        $this->validate([
            'logDate' => ['required', 'date', Rule::in($selectableDates)],
            'todoItemId' => [
                'required',
                'integer',
                Rule::exists('todo_items', 'id')->where(function ($query): void {
                    $query->where('is_separator', false)
                        ->whereDate('date', $this->logDate);
                }),
            ],
            'startedAt' => ['required', 'date_format:H:i'],
            'finishedAt' => ['required', 'date_format:H:i'],
        ]);

        $item = TodoItem::query()->findOrFail((int) $this->todoItemId);

        $started = Carbon::createFromFormat('Y-m-d H:i', $this->logDate.' '.$this->startedAt, 'Europe/Bucharest');
        $finished = Carbon::createFromFormat('Y-m-d H:i', $this->logDate.' '.$this->finishedAt, 'Europe/Bucharest');

        if ($finished->lte($started)) {
            $this->addError('finishedAt', 'Finish time must be after start time.');

            return;
        }

        TaskLog::query()->create([
            'todo_item_id' => $item->id,
            'title' => $item->title,
            'started_at' => $started->clone()->utc(),
            'finished_at' => $finished->clone()->utc(),
        ]);

        $this->closeAddModal();
    }

    public function weekStart(): Carbon
    {
        return $this->parseWeek($this->week) ?? now()->startOfWeek(Carbon::MONDAY);
    }

    public function weekNumber(): int
    {
        return (int) $this->weekStart()->isoWeek();
    }

    public function render()
    {
        $start = $this->weekStart()->copy()->startOfDay();
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        $today = now()->timezone('Europe/Bucharest')->toDateString();

        /** @var Collection<int, TaskLog> $logs */
        $logs = TaskLog::query()
            ->whereBetween('finished_at', [$start, $end])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        $colorByTitle = $this->colorByTitle($logs);
        $maxMinutes = max(1, (int) $logs->max(fn (TaskLog $log): int => $this->durationMinutes($log)));

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dateKey = $day->toDateString();

            $dayLogs = $logs
                ->filter(fn (TaskLog $log): bool => $log->finished_at->timezone('Europe/Bucharest')->toDateString() === $dateKey)
                ->values()
                ->map(function (TaskLog $log) use ($maxMinutes, $colorByTitle): array {
                    $minutes = $this->durationMinutes($log);

                    return [
                        'id' => $log->id,
                        'title' => $log->title,
                        'started' => $log->started_at->timezone('Europe/Bucharest')->format('H:i'),
                        'finished' => $log->finished_at->timezone('Europe/Bucharest')->format('H:i'),
                        'minutes' => $minutes,
                        'duration' => $this->formatDuration($minutes),
                        'width' => round(($minutes / $maxMinutes) * 100, 1),
                        'color' => $colorByTitle[$log->title],
                    ];
                });

            $totalMinutes = (int) $dayLogs->sum('minutes');

            $days[] = [
                'date' => $dateKey,
                'label' => strtoupper($day->format('D')),
                'dayNumber' => $day->day,
                'isToday' => $dateKey === $today,
                'totalMinutes' => $totalMinutes,
                'totalDuration' => $this->formatDuration($totalMinutes),
                'logs' => $dayLogs,
                'pieSlices' => $this->pieSlices($dayLogs, $totalMinutes),
            ];
        }

        return view('livewire.task-log-board', [
            'days' => $days,
            'weekNumber' => $this->weekNumber(),
            'hasLogs' => $logs->isNotEmpty(),
            'stats' => $this->stats($logs, $colorByTitle),
            'selectableDays' => $this->selectableDays(),
            'tasks' => $this->tasksForLogDate(),
        ]);
    }

    /**
     * @return list<array{date: string, label: string}>
     */
    private function selectableDays(): array
    {
        return $this->selectableLogDates()
            ->map(fn (string $date): array => [
                'date' => $date,
                'label' => strtoupper(Carbon::parse($date)->format('D')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function selectableLogDates(): Collection
    {
        $today = now()->timezone('Europe/Bucharest')->toDateString();
        $start = $this->weekStart()->copy()->startOfDay();

        return collect(range(0, 6))
            ->map(fn (int $offset): string => $start->copy()->addDays($offset)->toDateString())
            ->filter(fn (string $date): bool => $date <= $today)
            ->values();
    }

    private function defaultLogDate(): string
    {
        $today = now()->timezone('Europe/Bucharest')->toDateString();
        $dates = $this->selectableLogDates();

        if ($dates->contains($today)) {
            return $today;
        }

        return $dates->last() ?? $today;
    }

    /**
     * @return Collection<int, TodoItem>
     */
    private function tasksForLogDate(): Collection
    {
        if ($this->logDate === '' || ! $this->selectableLogDates()->contains($this->logDate)) {
            return collect();
        }

        return TodoItem::query()
            ->with('parent')
            ->where('is_separator', false)
            ->whereDate('date', $this->logDate)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, TaskLog>  $logs
     * @return array<string, string>
     */
    private function colorByTitle(Collection $logs): array
    {
        $titles = $logs
            ->unique('title')
            ->values()
            ->pluck('title');

        $colors = [];

        foreach ($titles as $index => $title) {
            $colors[$title] = $this->colorAt($index);
        }

        return $colors;
    }

    private function colorAt(int $index): string
    {
        if ($index < count(self::PIE_COLORS)) {
            return self::PIE_COLORS[$index];
        }

        $base = self::PIE_COLORS[$index % count(self::PIE_COLORS)];
        $cycle = intdiv($index, count(self::PIE_COLORS));

        return $this->shiftColor($base, $cycle);
    }

    private function shiftColor(string $hex, int $cycle): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $factor = 1 - min(0.45, $cycle * 0.18);

        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, (int) round($r * $factor))),
            max(0, min(255, (int) round($g * $factor))),
            max(0, min(255, (int) round($b * $factor))),
        );
    }

    /**
     * @param  Collection<int, TaskLog>  $logs
     * @param  array<string, string>  $colorByTitle
     * @return array{totalMinutes: int, totalDuration: string, sessionCount: int, itemCount: int, items: list<array{title: string, minutes: int, duration: string, share: float, width: float, color: string, sessions: list<array{id: int, day: string, date: string, started: string, finished: string, minutes: int, duration: string}>}>}
     */
    private function stats(Collection $logs, array $colorByTitle): array
    {
        $grouped = $logs->groupBy(fn (TaskLog $log): string => $log->title);

        $items = $grouped->map(function (Collection $sessions, string $title) use ($colorByTitle): array {
            $minutes = (int) $sessions->sum(fn (TaskLog $log): int => $this->durationMinutes($log));

            $rows = $sessions
                ->sortBy(fn (TaskLog $log) => $log->started_at->timestamp)
                ->values()
                ->map(function (TaskLog $log): array {
                    $started = $log->started_at->timezone('Europe/Bucharest');
                    $finished = $log->finished_at->timezone('Europe/Bucharest');
                    $sessionMinutes = $this->durationMinutes($log);

                    return [
                        'id' => $log->id,
                        'day' => strtoupper($finished->format('D')),
                        'date' => $finished->format('j M'),
                        'started' => $started->format('H:i'),
                        'finished' => $finished->format('H:i'),
                        'minutes' => $sessionMinutes,
                        'duration' => $this->formatDuration($sessionMinutes),
                    ];
                });

            return [
                'title' => $title,
                'minutes' => $minutes,
                'duration' => $this->formatDuration($minutes),
                'color' => $colorByTitle[$title],
                'sessions' => $rows->all(),
            ];
        })->sortByDesc('minutes')->values();

        $totalMinutes = (int) $items->sum('minutes');
        $maxMinutes = max(1, (int) $items->max('minutes'));

        $items = $items->map(function (array $item) use ($totalMinutes, $maxMinutes): array {
            $item['share'] = $totalMinutes > 0 ? round(($item['minutes'] / $totalMinutes) * 100, 1) : 0;
            $item['width'] = round(($item['minutes'] / $maxMinutes) * 100, 1);

            return $item;
        });

        return [
            'totalMinutes' => $totalMinutes,
            'totalDuration' => $this->formatDuration($totalMinutes),
            'sessionCount' => $logs->count(),
            'itemCount' => $items->count(),
            'items' => $items->all(),
        ];
    }

    /**
     * @param  Collection<int, array{id: int, title: string, minutes: int, duration: string, color: string}>  $dayLogs
     * @return list<array{id: int, title: string, duration: string, color: string, path: string|null, full: bool}>
     */
    private function pieSlices(Collection $dayLogs, int $totalMinutes): array
    {
        if ($dayLogs->isEmpty() || $totalMinutes <= 0) {
            return [];
        }

        if ($dayLogs->count() === 1) {
            $log = $dayLogs->first();

            return [[
                'id' => $log['id'],
                'title' => $log['title'],
                'duration' => $log['duration'],
                'color' => $log['color'],
                'path' => null,
                'full' => true,
            ]];
        }

        $slices = [];
        $angle = -90.0;

        foreach ($dayLogs as $log) {
            $sweep = ($log['minutes'] / $totalMinutes) * 360.0;
            $end = $angle + $sweep;

            $slices[] = [
                'id' => $log['id'],
                'title' => $log['title'],
                'duration' => $log['duration'],
                'color' => $log['color'],
                'path' => $this->slicePath(50, 50, 42, $angle, $end),
                'full' => false,
            ];

            $angle = $end;
        }

        return $slices;
    }

    private function slicePath(float $cx, float $cy, float $radius, float $startAngle, float $endAngle): string
    {
        $start = $this->polarToCartesian($cx, $cy, $radius, $endAngle);
        $end = $this->polarToCartesian($cx, $cy, $radius, $startAngle);
        $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;

        return "M {$cx} {$cy} L {$start['x']} {$start['y']} A {$radius} {$radius} 0 {$largeArc} 0 {$end['x']} {$end['y']} Z";
    }

    /**
     * @return array{x: float, y: float}
     */
    private function polarToCartesian(float $cx, float $cy, float $radius, float $angleInDegrees): array
    {
        $radians = deg2rad($angleInDegrees);

        return [
            'x' => round($cx + ($radius * cos($radians)), 3),
            'y' => round($cy + ($radius * sin($radians)), 3),
        ];
    }

    private function durationMinutes(TaskLog $log): int
    {
        return max(1, (int) $log->started_at->diffInMinutes($log->finished_at));
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return $remaining.'m';
        }

        if ($remaining === 0) {
            return $hours.'h';
        }

        return $hours.'h '.$remaining.'m';
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $matches) !== 1) {
            return $time;
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }

    private function parseWeek(string $week): ?Carbon
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $week, $m)) {
            return null;
        }

        try {
            return Carbon::now()
                ->setISODate((int) $m[1], (int) $m[2])
                ->startOfWeek(Carbon::MONDAY)
                ->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
