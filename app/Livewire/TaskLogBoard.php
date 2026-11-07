<?php

namespace App\Livewire;

use App\Models\ActivityAssignment;
use App\Models\Project;
use App\Models\TaskLog;
use App\Models\TodoItem;
use App\Models\WeekBoardItem;
use App\Support\Clock;
use App\Support\IdleTimeline;
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

    public ?int $editingLogId = null;

    public ?int $editingTodoItemId = null;

    public string $editingField = '';

    public string $editingContext = '';

    public string $editTime = '';

    public string $editStartedAt = '';

    public string $editFinishedAt = '';

    /** Activity chart modal: hub ('partners' | 'assets') and the clicked row name (or "Others"). */
    public ?string $activityHub = null;

    public ?string $activityRow = null;

    public function mount(): void
    {
        if ($this->week === '' || ! $this->parseWeek($this->week)) {
            $this->week = Clock::currentWeek();
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
        $this->week = Clock::currentWeek();
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

    public function startEditingTime(int $id, string $field, string $context = 'day'): void
    {
        if (! in_array($field, ['started', 'finished'], true)) {
            return;
        }

        if (! in_array($context, ['day', 'stats'], true)) {
            return;
        }

        $log = TaskLog::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingLogId = $log->id;
        $this->editingTodoItemId = null;
        $this->editingField = $field;
        $this->editingContext = $context;
        $this->editTime = $field === 'started'
            ? $log->started_at->timezone('Europe/Bucharest')->format('H:i')
            : $log->finished_at->timezone('Europe/Bucharest')->format('H:i');
        $this->editStartedAt = '';
        $this->editFinishedAt = '';
    }

    public function startEditingUntimedTime(int $todoItemId, string $field, string $context = 'stats'): void
    {
        if (! in_array($field, ['started', 'finished'], true)) {
            return;
        }

        if (! in_array($context, ['day', 'stats'], true)) {
            return;
        }

        TodoItem::query()
            ->whereKey($todoItemId)
            ->where('is_separator', false)
            ->where('is_completed', true)
            ->whereNotNull('date')
            ->firstOrFail();

        $this->resetValidation();
        $this->editingLogId = null;
        $this->editingTodoItemId = $todoItemId;
        $this->editingField = $field;
        $this->editingContext = $context;
        $this->editTime = '';
        $this->editStartedAt = '';
        $this->editFinishedAt = '';
    }

    public function finishEditingTime(): void
    {
        if ($this->editingTodoItemId) {
            $this->finishEditingUntimedTime();

            return;
        }

        if (! $this->editingLogId || ! in_array($this->editingField, ['started', 'finished'], true)) {
            return;
        }

        $this->editTime = $this->normalizeTime($this->editTime);

        $this->validate([
            'editTime' => ['required', 'date_format:H:i'],
        ]);

        $log = TaskLog::query()->findOrFail($this->editingLogId);
        $timezone = 'Europe/Bucharest';

        $started = $log->started_at->timezone($timezone);
        $finished = $log->finished_at->timezone($timezone);

        if ($this->editingField === 'started') {
            $started = Carbon::createFromFormat('Y-m-d H:i', $started->toDateString().' '.$this->editTime, $timezone);
        } else {
            $finished = Carbon::createFromFormat('Y-m-d H:i', $finished->toDateString().' '.$this->editTime, $timezone);
        }

        if ($finished->lte($started)) {
            $this->addError('editTime', 'Finish time must be after start time.');

            return;
        }

        $log->update([
            'started_at' => $started->clone()->utc(),
            'finished_at' => $finished->clone()->utc(),
        ]);

        $this->cancelEditingTime();
    }

    public function cancelEditingTime(): void
    {
        $this->editingLogId = null;
        $this->editingTodoItemId = null;
        $this->editingField = '';
        $this->editingContext = '';
        $this->editTime = '';
        $this->editStartedAt = '';
        $this->editFinishedAt = '';
        $this->resetValidation();
    }

    private function finishEditingUntimedTime(): void
    {
        if (! $this->editingTodoItemId) {
            return;
        }

        $this->editStartedAt = $this->normalizeTime($this->editStartedAt);
        $this->editFinishedAt = $this->normalizeTime($this->editFinishedAt);

        $this->validate([
            'editStartedAt' => ['required', 'date_format:H:i'],
            'editFinishedAt' => ['required', 'date_format:H:i'],
        ]);

        $item = TodoItem::query()
            ->whereKey($this->editingTodoItemId)
            ->where('is_separator', false)
            ->where('is_completed', true)
            ->whereNotNull('date')
            ->firstOrFail();

        $timezone = 'Europe/Bucharest';
        $day = $item->date->timezone($timezone)->toDateString();

        $started = Carbon::createFromFormat('Y-m-d H:i', $day.' '.$this->editStartedAt, $timezone);
        $finished = Carbon::createFromFormat('Y-m-d H:i', $day.' '.$this->editFinishedAt, $timezone);

        if ($finished->lte($started)) {
            $this->addError('editFinishedAt', 'Finish time must be after start time.');

            return;
        }

        TaskLog::query()->create([
            'todo_item_id' => $item->id,
            'title' => $item->title,
            'started_at' => $started->clone()->utc(),
            'finished_at' => $finished->clone()->utc(),
        ]);

        $this->cancelEditingTime();
    }

    public function weekStart(): Carbon
    {
        return $this->parseWeek($this->week) ?? Clock::today()->startOfWeek(Carbon::MONDAY);
    }

    public function weekNumber(): int
    {
        return (int) $this->weekStart()->isoWeek();
    }

    public function render()
    {
        $start = $this->weekStart()->copy()->startOfDay();
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        $today = Clock::todayString();

        /** @var Collection<int, TaskLog> $logs */
        $logs = TaskLog::query()
            ->whereBetween('finished_at', [$start, $end])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        /** @var Collection<string, Collection<int, TodoItem>> $completedUntimedByDate */
        $completedUntimedByDate = TodoItem::query()
            ->where('is_separator', false)
            ->where('is_completed', true)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereDoesntHave('taskLogs')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (TodoItem $item): string => $item->date->toDateString());

        $titles = $logs->pluck('title')
            ->merge($completedUntimedByDate->flatten(1)->pluck('title'))
            ->unique()
            ->values();

        $colorByTitle = $this->colorByTitle($titles);

        $dayGroups = [];
        $dayClock = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dateKey = $day->toDateString();

            $dayTimedLogs = $logs
                ->filter(fn (TaskLog $log): bool => $log->finished_at->timezone('Europe/Bucharest')->toDateString() === $dateKey)
                ->values();

            $dayGroups[$dateKey] = $this->mergeTouchingLogs($dayTimedLogs);
            $dayClock[$dateKey] = $this->clockArcs($dayGroups[$dateKey], $dateKey, $colorByTitle);
        }

        $maxMinutes = max(1, (int) collect($dayGroups)
            ->flatten(1)
            ->max(fn (Collection $group): int => $this->groupMinutes($group)));

        // Running timers count as busy on the idle bars (display only, no task_logs written).
        // Like loggingWindow(), a timer only covers the day it was started on: a timer left
        // running for days is stale and must not hide idle time on later days.
        $runningIntervals = TodoItem::query()
            ->whereNotNull('logging_started_at')
            ->get(['title', 'logging_started_at'])
            ->map(function (TodoItem $item): array {
                $started = Carbon::parse($item->logging_started_at);
                $endOfStartDay = $started->copy()->timezone(Clock::timezone())->endOfDay();

                return [$started, now()->min($endOfStartDay), (string) $item->title];
            });

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dateKey = $day->toDateString();

            $dayUntimed = $completedUntimedByDate->get($dateKey, collect());

            $dayLogs = $this->dayEntries(
                $dayGroups[$dateKey],
                $dayUntimed,
                $maxMinutes,
                $colorByTitle,
            );

            $timedDayLogs = $dayLogs->where('timed', true)->values();
            $totalMinutes = (int) $timedDayLogs->sum('minutes');

            $days[] = [
                'date' => $dateKey,
                'label' => strtoupper($day->format('D')),
                'dayNumber' => $day->day,
                'isToday' => $dateKey === $today,
                'totalMinutes' => $totalMinutes,
                'totalDuration' => $this->formatDuration($totalMinutes),
                'logs' => $dayLogs,
                'pieSlices' => $this->pieSlices($timedDayLogs, $totalMinutes),
                'clockArcs' => $dayClock[$dateKey],
                'idle' => IdleTimeline::forDay(
                    $dateKey,
                    $logs->map(fn (TaskLog $log): array => [$log->started_at, $log->finished_at])
                        ->concat($runningIntervals->map(fn (array $timer): array => [$timer[0], $timer[1]])),
                ) + ['running' => IdleTimeline::runningSegments($dateKey, $runningIntervals)],
            ];
        }

        // Weekends only count when something was logged or a timer ran that day.
        $weekIdleMinutes = (int) collect($days)
            ->filter(fn (array $day): bool => ! Carbon::parse($day['date'])->isWeekend()
                || $day['totalMinutes'] > 0
                || $day['idle']['running'] !== [])
            ->sum(fn (array $day): int => $day['idle']['idleMinutes']);

        $hasEntries = $logs->isNotEmpty() || $completedUntimedByDate->flatten(1)->isNotEmpty();

        return view('livewire.task-log-board', [
            'days' => $days,
            'weekIdleDuration' => $this->formatDuration($weekIdleMinutes),
            'weekIdleMinutes' => $weekIdleMinutes,
            'coverage' => [
                'partners' => $this->hubCoverage('partners', $logs->pluck('title')),
                'assets' => $this->hubCoverage('assets', $logs->pluck('title')),
            ],
            'activity' => [
                'partners' => $this->hubActivity('partners', $logs),
                'assets' => $this->hubActivity('assets', $logs),
            ],
            'activityModal' => $this->activityModal($logs),
            'weekNumber' => $this->weekNumber(),
            'weekRange' => \App\Support\WeekLabel::range($this->weekStart()),
            'hasLogs' => $hasEntries,
            'stats' => $this->stats($logs, $completedUntimedByDate, $colorByTitle),
            'selectableDays' => $this->selectableDays(),
            'tasks' => $this->tasksForLogDate(),
        ]);
    }

    /**
     * @param  Collection<int, Collection<int, TaskLog>>  $dayGroups
     * @param  Collection<int, TodoItem>  $dayUntimed
     * @param  array<string, string>  $colorByTitle
     * @return Collection<int, array{id: int|null, startLogId?: int, finishLogId?: int, todoItemId?: int, key: string, title: string, started: string, finished: string, timed: bool, minutes: int, duration: string, width: float, color: string}>
     */
    private function dayEntries(
        Collection $dayGroups,
        Collection $dayUntimed,
        int $maxMinutes,
        array $colorByTitle,
    ): Collection {
        $timed = $dayGroups
            ->map(fn (Collection $group): array => $this->timedEntry($group, $maxMinutes, $colorByTitle));

        $untimed = $dayUntimed
            ->map(fn (TodoItem $task): array => $this->untimedDayEntry($task, $colorByTitle));

        return $timed->concat($untimed)->values();
    }

    /**
     * Same title, next session starting on the displayed minute the previous one finished.
     *
     * @param  Collection<int, TaskLog>  $logs
     * @return Collection<int, Collection<int, TaskLog>>
     */
    private function mergeTouchingLogs(Collection $logs): Collection
    {
        $groups = collect();
        $current = null;

        foreach ($logs->sortBy(fn (TaskLog $log): array => [$log->started_at->timestamp, $log->id]) as $log) {
            if (! $current instanceof Collection) {
                $current = collect([$log]);

                continue;
            }

            /** @var TaskLog $previous */
            $previous = $current->last();
            $sameTitle = $previous->title === $log->title;
            $touches = $previous->finished_at->timezone('Europe/Bucharest')->format('H:i')
                === $log->started_at->timezone('Europe/Bucharest')->format('H:i');

            if ($sameTitle && $touches) {
                $current->push($log);

                continue;
            }

            $groups->push($current);
            $current = collect([$log]);
        }

        if ($current instanceof Collection) {
            $groups->push($current);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, TaskLog>  $group
     */
    private function groupMinutes(Collection $group): int
    {
        return (int) $group->sum(fn (TaskLog $log): int => $this->durationMinutes($log));
    }

    /**
     * @param  Collection<int, TaskLog>  $group
     * @param  array<string, string>  $colorByTitle
     * @return array{id: int, startLogId: int, finishLogId: int, key: string, title: string, started: string, finished: string, timed: bool, minutes: int, duration: string, width: float, color: string}
     */
    private function timedEntry(Collection $group, int $maxMinutes, array $colorByTitle): array
    {
        /** @var TaskLog $first */
        $first = $group->first();
        /** @var TaskLog $last */
        $last = $group->last();
        $minutes = $this->groupMinutes($group);

        return [
            'id' => $first->id,
            'startLogId' => $first->id,
            'finishLogId' => $last->id,
            'key' => 'log-'.$group->pluck('id')->implode('-'),
            'title' => $first->title,
            'started' => $first->started_at->timezone('Europe/Bucharest')->format('H:i'),
            'finished' => $last->finished_at->timezone('Europe/Bucharest')->format('H:i'),
            'timed' => true,
            'minutes' => $minutes,
            'duration' => $this->formatDuration($minutes),
            'width' => round(($minutes / $maxMinutes) * 100, 1),
            'color' => $colorByTitle[$first->title],
        ];
    }

    /**
     * @param  array<string, string>  $colorByTitle
     * @return array{id: null, todoItemId: int, key: string, title: string, started: string, finished: string, timed: bool, minutes: int, duration: string, width: float, color: string}
     */
    private function untimedDayEntry(TodoItem $task, array $colorByTitle): array
    {
        return [
            'id' => null,
            'todoItemId' => $task->id,
            'key' => 'untimed-'.$task->id,
            'title' => $task->title,
            'started' => 'N/A',
            'finished' => 'N/A',
            'timed' => false,
            'minutes' => 0,
            'duration' => 'N/A',
            'width' => 40.0,
            'color' => $colorByTitle[$task->title],
        ];
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
        $today = Clock::todayString();
        $start = $this->weekStart()->copy()->startOfDay();

        return collect(range(0, 6))
            ->map(fn (int $offset): string => $start->copy()->addDays($offset)->toDateString())
            ->filter(fn (string $date): bool => $date <= $today)
            ->values();
    }

    private function defaultLogDate(): string
    {
        $today = Clock::todayString();
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
     * @param  Collection<int, string>  $titles
     * @return array<string, string>
     */
    private function colorByTitle(Collection $titles): array
    {
        $colors = [];

        foreach ($titles->values() as $index => $title) {
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
     * @param  Collection<string, Collection<int, TodoItem>>  $completedUntimedByDate
     * @param  array<string, string>  $colorByTitle
     * @return array{totalMinutes: int, totalDuration: string, sessionCount: int, itemCount: int, days: list<array{date: string, label: string, heading: string, totalMinutes: int, totalDuration: string, items: list<array{title: string, minutes: int, duration: string, share: float, width: float, color: string, timed: bool, sessions: list<array{id: int|null, todoItemId?: int, day: string, date: string, started: string, finished: string, timed: bool, minutes: int, duration: string}>}>}>}
     */
    private function stats(Collection $logs, Collection $completedUntimedByDate, array $colorByTitle): array
    {
        $logsByDate = $logs->groupBy(
            fn (TaskLog $log): string => $log->finished_at->timezone('Europe/Bucharest')->toDateString()
        );

        $weekStart = $this->weekStart()->copy()->startOfDay();
        $days = [];
        $uniqueTitles = collect();

        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dateKey = $day->toDateString();
            $dayLogs = $logsByDate->get($dateKey, collect());
            $dayUntimed = $completedUntimedByDate->get($dateKey, collect());

            if ($dayLogs->isEmpty() && $dayUntimed->isEmpty()) {
                continue;
            }

            $items = $dayLogs
                ->groupBy(fn (TaskLog $log): string => $log->title)
                ->map(function (Collection $sessions, string $title) use ($colorByTitle): array {
                    $minutes = (int) $sessions->sum(fn (TaskLog $log): int => $this->durationMinutes($log));

                    $rows = $this->mergeTouchingLogs($sessions)
                        ->map(function (Collection $group): array {
                            /** @var TaskLog $first */
                            $first = $group->first();
                            /** @var TaskLog $last */
                            $last = $group->last();
                            $started = $first->started_at->timezone('Europe/Bucharest');
                            $finished = $last->finished_at->timezone('Europe/Bucharest');
                            $sessionMinutes = $this->groupMinutes($group);

                            return [
                                'id' => $first->id,
                                'startLogId' => $first->id,
                                'finishLogId' => $last->id,
                                'day' => strtoupper($finished->format('D')),
                                'date' => $finished->format('j M'),
                                'started' => $started->format('H:i'),
                                'finished' => $finished->format('H:i'),
                                'timed' => true,
                                'minutes' => $sessionMinutes,
                                'duration' => $this->formatDuration($sessionMinutes),
                            ];
                        });

                    return [
                        'title' => $title,
                        'minutes' => $minutes,
                        'duration' => $this->formatDuration($minutes),
                        'color' => $colorByTitle[$title],
                        'timed' => true,
                        'sessions' => $rows->all(),
                    ];
                });

            foreach ($dayUntimed as $task) {
                $title = $task->title ?? '';

                if ($items->has($title)) {
                    continue;
                }

                $items[$title] = [
                    'title' => $title,
                    'minutes' => 0,
                    'duration' => 'N/A',
                    'color' => $colorByTitle[$title],
                    'timed' => false,
                    'sessions' => [[
                        'id' => null,
                        'todoItemId' => $task->id,
                        'day' => strtoupper($day->format('D')),
                        'date' => $day->format('j M'),
                        'started' => 'N/A',
                        'finished' => 'N/A',
                        'timed' => false,
                        'minutes' => 0,
                        'duration' => 'N/A',
                    ]],
                ];
            }

            $items = $items
                ->sort(function (array $left, array $right): int {
                    if ($left['timed'] !== $right['timed']) {
                        return $left['timed'] ? -1 : 1;
                    }

                    return $right['minutes'] <=> $left['minutes'];
                })
                ->values();

            $dayTotalMinutes = (int) $items->sum('minutes');
            $dayMaxMinutes = max(1, (int) $items->max('minutes'));

            $items = $items->map(function (array $item) use ($dayTotalMinutes, $dayMaxMinutes): array {
                $item['share'] = $dayTotalMinutes > 0 ? round(($item['minutes'] / $dayTotalMinutes) * 100, 1) : 0;
                $item['width'] = $item['timed']
                    ? round(($item['minutes'] / $dayMaxMinutes) * 100, 1)
                    : 40.0;

                return $item;
            });

            $uniqueTitles = $uniqueTitles->merge($items->pluck('title'));

            $days[] = [
                'date' => $dateKey,
                'label' => strtoupper($day->format('D')),
                'heading' => strtoupper($day->format('D')).' '.$day->format('j M'),
                'totalMinutes' => $dayTotalMinutes,
                'totalDuration' => $this->formatDuration($dayTotalMinutes),
                'items' => $items->all(),
            ];
        }

        $totalMinutes = (int) $logs->sum(fn (TaskLog $log): int => $this->durationMinutes($log));

        return [
            'totalMinutes' => $totalMinutes,
            'totalDuration' => $this->formatDuration($totalMinutes),
            'sessionCount' => $logs->count(),
            'itemCount' => $uniqueTitles->unique()->count(),
            'days' => $days,
        ];
    }

    /**
     * @param  Collection<int, array{id: int|null, title: string, minutes: int, duration: string, color: string}>  $dayLogs
     * @return list<array{id: int|null, title: string, duration: string, color: string, path: string|null, full: bool}>
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

    /**
     * Sessions of one day on a 12h dial: normal clock face (12 top, 3 right), 08:00/20:00 at 8 o'clock, clockwise, Europe/Bucharest.
     * Each session is clipped to 08:00-20:00 of that day; chronological, so later ones draw on top.
     *
     * Uses the same merged groups as the list (touching same-title sessions = one arc).
     *
     * @param  Collection<int, Collection<int, TaskLog>>  $groups
     * @param  array<string, string>  $colorByTitle
     * @return list<array{title: string, color: string, started: string, finished: string, path: string|null, full: bool}>
     */
    private function clockArcs(Collection $groups, string $dateKey, array $colorByTitle): array
    {
        $tz = 'Europe/Bucharest';
        $from = Carbon::parse($dateKey.' 08:00:00', $tz);
        $to = $from->copy()->addHours(12);
        $arcs = [];

        foreach ($groups->sortBy(fn (Collection $g) => $g->min(fn (TaskLog $l) => $l->started_at->getTimestamp()))->values() as $group) {
            $log = $group->first();
            $startedAt = $group->map(fn (TaskLog $l) => $l->started_at)->sort()->first();
            $finishedAt = $group->map(fn (TaskLog $l) => $l->finished_at)->sort()->last();
            $s = $startedAt->copy()->timezone($tz)->max($from);
            $e = $finishedAt->copy()->timezone($tz)->min($to);
            if ($e->lte($s)) {
                continue;
            }
            $a = $from->diffInSeconds($s) / 43200 * 360 + 150; // 08:00 sits at 8 o'clock (240deg from top)
            $b = $from->diffInSeconds($e) / 43200 * 360 + 150; // 08:00 sits at 8 o'clock (240deg from top)
            $full = $b - $a >= 359.9;
            $p1 = $this->polarToCartesian(50, 50, 36, $a);
            $p2 = $this->polarToCartesian(50, 50, 36, $b);
            $large = ($b - $a) > 180 ? 1 : 0;

            $arcs[] = [
                'title' => (string) $log->title,
                'color' => $colorByTitle[$log->title] ?? '#888888',
                'started' => $startedAt->copy()->timezone($tz)->format('H:i'),
                'finished' => $finishedAt->copy()->timezone($tz)->format('H:i'),
                'path' => $full ? null : "M {$p1['x']} {$p1['y']} A 36 36 0 {$large} 1 {$p2['x']} {$p2['y']}",
                'full' => $full,
            ];
        }

        return $arcs;
    }

    /** @return list<array{label: string, x: float, y: float, tx: float, ty: float, ix: float, iy: float}> */
    public static function clockTicks(): array
    {
        $ticks = [];
        for ($i = 0; $i < 12; $i++) {
            $rad = deg2rad($i * 30 - 90); // normal clock face: 12 top, 3 right
            $h = $i === 0 ? 12 : $i;
            $ticks[] = [
                'label' => (string) $h,
                'x' => round(50 + 47 * cos($rad), 2), 'y' => round(50 + 47 * sin($rad), 2),
                'tx' => round(50 + 26 * cos($rad), 2), 'ty' => round(50 + 26 * sin($rad), 2),
                'ix' => round(50 + 43 * cos($rad), 2), 'iy' => round(50 + 43 * sin($rad), 2),
            ];
        }

        return $ticks;
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

    /**
     * How many non-archived projects of a Links hub had logged time, matched by the
     * "<Name> - task" title convention (no relation exists between logs and projects).
     *
     * @param  Collection<int, string>  $titles
     * @return array{covered: int, total: int, uncovered: list<string>}
     */
    public function hubCoverage(string $hub, Collection $titles): array
    {
        $prefixes = $titles
            ->map(fn (string $title): string => mb_strtolower(trim(explode(' - ', $title, 2)[0])))
            ->filter(fn (string $prefix): bool => $prefix !== '')
            ->unique();

        $projects = Project::query()
            ->where('hub', $hub)
            ->where('is_delimiter', false)
            ->where('is_archived', false)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('name');

        $uncovered = $projects
            ->reject(fn (string $name): bool => $prefixes->contains(fn (string $prefix): bool => self::prefixMatchesProject($prefix, $name)))
            ->values()
            ->all();

        return [
            'covered' => $projects->count() - count($uncovered),
            'total' => $projects->count(),
            'uncovered' => $uncovered,
        ];
    }

    /**
     * Logged minutes of the week split by the projects of a Links hub ("partners" = clients,
     * "assets" = assets). A log belongs to a project when its milestone is linked to that
     * project's Week Board card, otherwise by the "<Name> - task" title prefix (same rule as
     * hubCoverage, archived projects included), then by a manual assignment made from the
     * Others modal (activity_assignments, per hub and item title). Everything else is "Others".
     *
     * @param  Collection<int, TaskLog>  $logs
     * @return array{totalMinutes: int, totalDuration: string, rows: list<array{name: string, minutes: int, duration: string, share: float, width: float, color: string, others: bool, items: list<array{title: string, minutes: int, duration: string, share: float}>}>}
     */
    public function hubActivity(string $hub, Collection $logs): array
    {
        $projects = Project::query()
            ->where('hub', $hub)
            ->where('is_delimiter', false)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'name']);

        $linkedProjectByTodo = WeekBoardItem::query()
            ->whereNotNull('todo_item_id')
            ->whereIn('todo_item_id', $logs->pluck('todo_item_id')->filter()->unique()->values())
            ->whereIn('project_id', $projects->pluck('id'))
            ->orderBy('id')
            ->get(['todo_item_id', 'project_id'])
            ->groupBy('todo_item_id')
            ->map(fn (Collection $items): int => (int) $items->first()->project_id);

        $nameById = $projects->pluck('name', 'id');
        $assignedByTitle = ActivityAssignment::query()
            ->where('hub', $hub)
            ->whereIn('project_id', $projects->pluck('id'))
            ->pluck('project_id', 'title');
        $minutesByName = [];
        $itemsByName = [];
        $othersMinutes = 0;
        $othersItems = [];
        $othersDays = [];
        $daysByName = [];

        foreach ($logs as $log) {
            $minutes = $this->durationMinutes($log);
            $title = (string) $log->title;
            $dateKey = $log->finished_at->timezone('Europe/Bucharest')->toDateString();
            $name = null;

            if ($log->todo_item_id !== null && $linkedProjectByTodo->has($log->todo_item_id)) {
                $name = $nameById[$linkedProjectByTodo[$log->todo_item_id]] ?? null;
            }

            if ($name === null) {
                $prefix = mb_strtolower(trim(explode(' - ', $title, 2)[0]));
                $name = $prefix === '' ? null : $projects->first(
                    fn (Project $project): bool => self::prefixMatchesProject($prefix, $project->name)
                )?->name;
            }

            // Manual assignment from the Others modal: only reached when nothing stronger matched.
            if ($name === null && $assignedByTitle->has($title)) {
                $name = $nameById[$assignedByTitle[$title]] ?? null;
            }

            if ($name === null) {
                $othersMinutes += $minutes;
                $othersItems[$title] = ($othersItems[$title] ?? 0) + $minutes;
                $othersDays[$dateKey][$title] = ($othersDays[$dateKey][$title] ?? 0) + $minutes;
            } else {
                $daysByName[$name][$dateKey][$title] = ($daysByName[$name][$dateKey][$title] ?? 0) + $minutes;
                $minutesByName[$name] = ($minutesByName[$name] ?? 0) + $minutes;
                $itemsByName[$name][$title] = ($itemsByName[$name][$title] ?? 0) + $minutes;
            }
        }

        arsort($minutesByName);
        $rows = [];
        $index = 0;

        foreach ($minutesByName as $name => $minutes) {
            $rows[] = ['name' => (string) $name, 'minutes' => $minutes, 'color' => $this->colorAt($index++), 'others' => false, 'items' => $this->activityItems($itemsByName[$name], $minutes), 'days' => $this->activityDays($daysByName[$name])];
        }

        if ($othersMinutes > 0) {
            $rows[] = ['name' => 'Others', 'minutes' => $othersMinutes, 'color' => '#6e7681', 'others' => true, 'items' => $this->activityItems($othersItems, $othersMinutes), 'days' => $this->activityDays($othersDays)];
        }

        $total = array_sum(array_column($rows, 'minutes'));
        $max = max(1, $rows === [] ? 0 : max(array_column($rows, 'minutes')));

        return [
            'totalMinutes' => $total,
            'totalDuration' => $this->formatDuration($total),
            'rows' => array_map(fn (array $row): array => $row + [
                'duration' => $this->formatDuration($row['minutes']),
                'share' => $total > 0 ? round($row['minutes'] / $total * 100, 1) : 0.0,
                'width' => round($row['minutes'] / $max * 100, 1),
            ], $rows),
        ];
    }

    /**
     * @param  array<string, int>  $minutesByTitle
     * @return list<array{title: string, minutes: int, duration: string, share: float}>
     */
    private function activityItems(array $minutesByTitle, int $rowMinutes): array
    {
        arsort($minutesByTitle);
        $items = [];

        foreach ($minutesByTitle as $title => $minutes) {
            $items[] = [
                'title' => (string) $title,
                'minutes' => $minutes,
                'duration' => $this->formatDuration($minutes),
                'share' => $rowMinutes > 0 ? round($minutes / $rowMinutes * 100, 1) : 0.0,
            ];
        }

        return $items;
    }

    /**
     * Items of one chart row split by the day they were logged (ISO order, Monday first,
     * days without items omitted); each day lists that day's minutes, longest first.
     *
     * @param  array<string, array<string, int>>  $minutesByDateAndTitle
     * @return list<array{date: string, heading: string, duration: string, items: list<array{title: string, minutes: int, duration: string, share: float}>}>
     */
    private function activityDays(array $minutesByDateAndTitle): array
    {
        ksort($minutesByDateAndTitle);
        $days = [];

        foreach ($minutesByDateAndTitle as $date => $minutesByTitle) {
            $dayMinutes = array_sum($minutesByTitle);
            $days[] = [
                'date' => (string) $date,
                'heading' => Carbon::parse((string) $date)->format('l j M'),
                'duration' => $this->formatDuration($dayMinutes),
                'items' => $this->activityItems($minutesByTitle, $dayMinutes),
            ];
        }

        return $days;
    }

    /** Others modal: switch between client-side and asset-side Others. */
    public function switchActivityHub(string $hub): void
    {
        if ($this->activityRow === 'Others' && in_array($hub, ['partners', 'assets'], true)) {
            $this->activityHub = $hub;
        }
    }

    public function openActivity(string $hub, string $row): void
    {
        if (! in_array($hub, ['partners', 'assets'], true)) {
            return;
        }

        $this->activityHub = $hub;
        $this->activityRow = $row;
    }

    public function closeActivity(): void
    {
        $this->activityHub = null;
        $this->activityRow = null;
    }

    /** Assign an item currently in "Others" to a project of the open chart's hub. */
    public function assignActivityItem(string $title, int|string $projectId): void
    {
        $hub = $this->activityHub;

        if ($hub === null || $this->activityRow !== 'Others') {
            return;
        }

        $project = Project::query()
            ->where('hub', $hub)
            ->where('is_delimiter', false)
            ->find((int) $projectId);

        $others = collect($this->hubActivity($hub, $this->weekLogs())['rows'])->firstWhere('others', true);
        $inOthers = $others !== null && collect($others['items'])->contains('title', $title);

        if ($project === null || ! $inOthers) {
            return;
        }

        ActivityAssignment::query()->updateOrCreate(
            ['hub' => $hub, 'title' => $title],
            ['project_id' => $project->id],
        );
    }

    /** @return Collection<int, TaskLog> */
    private function weekLogs(): Collection
    {
        $start = $this->weekStart()->copy()->startOfDay();

        return TaskLog::query()
            ->whereBetween('finished_at', [$start, $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay()])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, TaskLog>  $logs
     * @return array{hub: string, label: string, row: string, others: bool, duration: string, items: list<array<string, mixed>>, projects: array<int, string>}|null
     */
    private function activityModal(Collection $logs): ?array
    {
        if ($this->activityHub === null || $this->activityRow === null) {
            return null;
        }

        $row = collect($this->hubActivity($this->activityHub, $logs)['rows'])->firstWhere('name', $this->activityRow);
        $others = $this->activityRow === 'Others';

        return [
            'hub' => $this->activityHub,
            'label' => $this->activityHub === 'partners' ? 'client' : 'asset',
            'row' => $this->activityRow,
            'others' => $others,
            'duration' => $row['duration'] ?? '0m',
            'items' => $row['items'] ?? [],
            'days' => $row['days'] ?? [],
            'projects' => $others
                ? Project::query()
                    ->where('hub', $this->activityHub)
                    ->where('is_delimiter', false)
                    ->where('is_archived', false)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->pluck('name', 'id')
                    ->all()
                : [],
        ];
    }

    /**
     * "saints" or "saints nearby" or "sn" match "Saints Nearby"; "(cu Sam)" style suffixes are ignored.
     */
    public static function prefixMatchesProject(string $prefix, string $name): bool
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $name)));

        if ($normalized === '') {
            return false;
        }

        if ($prefix === $normalized || str_starts_with($normalized, $prefix.' ') || str_starts_with($prefix, $normalized.' ')) {
            return true;
        }

        $words = preg_split('/\s+/u', $normalized);
        $initials = implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), $words));

        return count($words) >= 2 && $prefix === $initials;
    }
}
