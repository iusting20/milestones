<?php

namespace App\Livewire;

use App\Models\TaskLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    ];

    #[Url(as: 'week', history: true)]
    public string $week = '';

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

        $maxMinutes = max(1, (int) $logs->max(fn (TaskLog $log): int => $this->durationMinutes($log)));

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dateKey = $day->toDateString();

            $dayLogs = $logs
                ->filter(fn (TaskLog $log): bool => $log->finished_at->timezone('Europe/Bucharest')->toDateString() === $dateKey)
                ->values()
                ->map(function (TaskLog $log, int $index) use ($maxMinutes): array {
                    $minutes = $this->durationMinutes($log);

                    return [
                        'id' => $log->id,
                        'title' => $log->title,
                        'started' => $log->started_at->timezone('Europe/Bucharest')->format('H:i'),
                        'finished' => $log->finished_at->timezone('Europe/Bucharest')->format('H:i'),
                        'minutes' => $minutes,
                        'duration' => $this->formatDuration($minutes),
                        'width' => round(($minutes / $maxMinutes) * 100, 1),
                        'color' => self::PIE_COLORS[$index % count(self::PIE_COLORS)],
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
        ]);
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
