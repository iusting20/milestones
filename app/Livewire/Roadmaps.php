<?php

namespace App\Livewire;

use App\Support\Clock;
use App\Models\RoadmapItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Roadmaps extends Component
{
    public const PENDING_COLOR_COUNT = 8;

    /**
     * @var list<string>
     */
    private const PENDING_COLORS = [
        '#ffc107',
        '#ff9800',
        '#ff5722',
        '#e91e63',
        '#9c27b0',
        '#3f51b5',
        '#2196f3',
        '#00bcd4',
    ];

    private const DONE_COLOR = '#7cb518';

    #[Url(as: 'month', history: true)]
    public string $month = '';

    public string $newTitle = '';

    public string $startedOn = '';

    public bool $creating = false;

    public function mount(): void
    {
        if ($this->month === '' || ! $this->parseMonth($this->month)) {
            $this->month = Clock::currentMonth();
        }

        if ($this->startedOn === '') {
            $this->startedOn = Clock::todayString();
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->copy()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->copy()->addMonthNoOverflow()->format('Y-m');
    }

    public function goToThisMonth(): void
    {
        $this->month = Clock::currentMonth();
        $this->startedOn = Clock::todayString();
    }

    public function showMonth(string $month): void
    {
        if (! $this->parseMonth($month)) {
            return;
        }

        $this->month = $month;
    }

    public function selectDay(string $date): void
    {
        if (! $this->parseDate($date)) {
            return;
        }

        $this->startedOn = $date;
    }

    public function openCreate(): void
    {
        $this->newTitle = '';
        $this->startedOn = Clock::todayString();
        $this->creating = true;
    }

    public function closeCreate(): void
    {
        $this->creating = false;
    }

    public function addItem(): void
    {
        $title = trim($this->newTitle);
        $startedOn = $this->parseDate($this->startedOn);

        if ($title === '' || ! $startedOn) {
            return;
        }

        RoadmapItem::query()->create([
            'title' => $title,
            'started_on' => $startedOn->toDateString(),
            'completed_on' => null,
        ]);

        $this->newTitle = '';
        $this->creating = false;
        $this->month = $startedOn->format('Y-m');
    }

    public function toggleComplete(int $id): void
    {
        $item = RoadmapItem::query()->findOrFail($id);
        $today = Clock::today();

        if ($item->completed_on !== null) {
            $item->update(['completed_on' => null]);

            return;
        }

        $start = $item->rangeStart();
        $completedOn = $today->lt($start) ? $start : $today;

        $item->update(['completed_on' => $completedOn->toDateString()]);
    }

    public function updateStartedOn(int $id, string $date): void
    {
        $startedOn = $this->parseDate($date);

        if (! $startedOn) {
            return;
        }

        $item = RoadmapItem::query()->findOrFail($id);
        $payload = ['started_on' => $startedOn->toDateString()];

        if ($item->completed_on !== null && $item->completed_on->lt($startedOn)) {
            $payload['completed_on'] = $startedOn->toDateString();
        }

        $item->update($payload);
    }

    public function updateCompletedOn(int $id, ?string $date = null): void
    {
        $item = RoadmapItem::query()->findOrFail($id);

        if ($date === null || trim($date) === '') {
            $item->update(['completed_on' => null]);

            return;
        }

        $completedOn = $this->parseDate($date);

        if (! $completedOn) {
            return;
        }

        $start = $item->rangeStart();

        if ($completedOn->lt($start)) {
            $completedOn = $start;
        }

        $item->update(['completed_on' => $completedOn->toDateString()]);
    }

    public function deleteItem(int $id): void
    {
        RoadmapItem::query()->whereKey($id)->delete();
    }

    public function monthStart(): Carbon
    {
        return $this->parseMonth($this->month) ?? Clock::today()->startOfMonth()->startOfDay();
    }

    public function render()
    {
        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $today = Clock::today();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $items = RoadmapItem::query()
            ->whereDate('started_on', '<=', $gridEnd->toDateString())
            ->where(function ($query) use ($gridStart): void {
                $query->whereNull('completed_on')
                    ->orWhereDate('completed_on', '>=', $gridStart->toDateString());
            })
            ->orderBy('started_on')
            ->orderBy('id')
            ->get();

        $visibleItems = $items
            ->filter(fn (RoadmapItem $item) => $this->overlaps($item, $monthStart, $monthEnd, $today))
            ->values();

        return view('livewire.roadmaps', [
            'monthLabel' => strtoupper($monthStart->format('F Y')),
            'yearMinimap' => $this->yearMinimap($monthStart, $today),
            'today' => $today->toDateString(),
            'weeks' => $this->weeks($gridStart, $gridEnd, $monthStart, $monthEnd, $items, $today),
            'visibleItems' => $visibleItems,
            'activeCount' => RoadmapItem::query()
                ->whereDate('started_on', '<=', $today->toDateString())
                ->whereNull('completed_on')
                ->count(),
            'completedCount' => RoadmapItem::query()->whereNotNull('completed_on')->count(),
            'notStartedCount' => RoadmapItem::query()
                ->whereDate('started_on', '>', $today->toDateString())
                ->whereNull('completed_on')
                ->count(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, active: bool, weeks: list<list<array{inMonth: bool, colors: list<string>}>>}>
     */
    private function yearMinimap(Carbon $monthStart, Carbon $today): array
    {
        $year = (int) $monthStart->year;
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->startOfDay();

        $items = RoadmapItem::query()
            ->whereDate('started_on', '<=', $yearEnd->toDateString())
            ->where(function ($query) use ($yearStart): void {
                $query->whereNull('completed_on')
                    ->orWhereDate('completed_on', '>=', $yearStart->toDateString());
            })
            ->orderBy('started_on')
            ->orderBy('id')
            ->get();

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $gridEnd = $start->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            $weeks = [];

            while ($cursor->lte($gridEnd)) {
                $days = [];

                for ($i = 0; $i < 7; $i++) {
                    $day = $cursor->copy()->addDays($i);
                    $days[] = [
                        'inMonth' => $day->month === $month,
                        'colors' => $day->month === $month ? $this->minimapColors($items, $day, $today) : [],
                    ];
                }

                $weeks[] = $days;
                $cursor->addWeek();
            }

            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => strtoupper($start->format('M')),
                'active' => $start->format('Y-m') === $monthStart->format('Y-m'),
                'weeks' => $weeks,
            ];
        }

        return $months;
    }

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return list<string>
     */
    private function minimapColors(Collection $items, Carbon $day, Carbon $today): array
    {
        $colors = [];

        foreach ($items as $item) {
            if ($item->rangeStart()->gt($day) || $item->rangeEnd($today)->lt($day)) {
                continue;
            }

            $colors[] = $item->isPending()
                ? self::PENDING_COLORS[$this->colorIndexFor($item->id)]
                : self::DONE_COLOR;
        }

        return array_values(array_unique($colors));
    }

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return list<array{days: list<Carbon>, lanes: list<list<array{id: int, title: string, pending: bool, colorIndex: int, col: int, span: int, continuesLeft: bool, continuesRight: bool}>>}>
     */
    private function weeks(Carbon $gridStart, Carbon $gridEnd, Carbon $monthStart, Carbon $monthEnd, Collection $items, Carbon $today): array
    {
        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $days[] = $cursor->copy()->addDays($i);
            }

            $weeks[] = [
                'days' => $days,
                'lanes' => $this->lanesForWeek($days[0], $days[6], $monthStart, $monthEnd, $items, $today),
            ];

            $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return list<list<array{id: int, title: string, pending: bool, colorIndex: int, col: int, span: int, continuesLeft: bool, continuesRight: bool}>>
     */
    private function lanesForWeek(Carbon $weekStart, Carbon $weekEnd, Carbon $monthStart, Carbon $monthEnd, Collection $items, Carbon $today): array
    {
        $segments = [];

        foreach ($items as $item) {
            $originalStart = $item->rangeStart();
            $originalEnd = $item->rangeEnd($today);
            $rangeStart = $originalStart->lt($monthStart) ? $monthStart->copy() : $originalStart;
            $rangeEnd = $originalEnd->gt($monthEnd) ? $monthEnd->copy() : $originalEnd;

            if ($rangeEnd->lt($weekStart) || $rangeStart->gt($weekEnd)) {
                continue;
            }

            $segStart = $rangeStart->greaterThan($weekStart) ? $rangeStart : $weekStart;
            $segEnd = $rangeEnd->lessThan($weekEnd) ? $rangeEnd : $weekEnd;
            $col = $segStart->dayOfWeekIso;
            $span = $segStart->diffInDays($segEnd) + 1;

            $segments[] = [
                'id' => $item->id,
                'title' => $item->title,
                'pending' => $item->isPending(),
                'colorIndex' => $this->colorIndexFor($item->id),
                'col' => $col,
                'span' => (int) $span,
                'continuesLeft' => $originalStart->lt($segStart),
                'continuesRight' => $originalEnd->gt($segEnd),
            ];
        }

        usort($segments, function (array $a, array $b): int {
            return [$a['col'], -$a['span'], $a['id']] <=> [$b['col'], -$b['span'], $b['id']];
        });

        $lanes = [];

        foreach ($segments as $segment) {
            $placed = false;
            $segEnd = $segment['col'] + $segment['span'] - 1;

            foreach ($lanes as $index => $lane) {
                $fits = true;

                foreach ($lane as $existing) {
                    $existingEnd = $existing['col'] + $existing['span'] - 1;

                    if ($segment['col'] <= $existingEnd && $existing['col'] <= $segEnd) {
                        $fits = false;
                        break;
                    }
                }

                if ($fits) {
                    $lanes[$index][] = $segment;
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $lanes[] = [$segment];
            }
        }

        return $lanes;
    }

    private function overlaps(RoadmapItem $item, Carbon $from, Carbon $to, Carbon $today): bool
    {
        return ! $item->rangeEnd($today)->lt($from) && ! $item->rangeStart()->gt($to);
    }

    public function colorIndexFor(int $id): int
    {
        return (($id % self::PENDING_COLOR_COUNT) + self::PENDING_COLOR_COUNT) % self::PENDING_COLOR_COUNT;
    }

    private function parseMonth(string $month): ?Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            return null;
        }

        $year = (int) $m[1];
        $monthNumber = (int) $m[2];

        if ($monthNumber < 1 || $monthNumber > 12) {
            return null;
        }

        return Carbon::create($year, $monthNumber, 1)->startOfDay();
    }

    private function parseDate(string $date): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);

            return $parsed ? $parsed->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
