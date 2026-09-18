<?php

namespace App\Livewire;

use App\Models\RoadmapItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Roadmaps extends Component
{
    #[Url(as: 'month', history: true)]
    public string $month = '';

    public string $newTitle = '';

    public string $startedOn = '';

    public function mount(): void
    {
        if ($this->month === '' || ! $this->parseMonth($this->month)) {
            $this->month = now()->format('Y-m');
        }

        if ($this->startedOn === '') {
            $this->startedOn = now()->toDateString();
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
        $this->month = now()->format('Y-m');
        $this->startedOn = now()->toDateString();
    }

    public function selectDay(string $date): void
    {
        if (! $this->parseDate($date)) {
            return;
        }

        $this->startedOn = $date;
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
    }

    public function toggleComplete(int $id): void
    {
        $item = RoadmapItem::query()->findOrFail($id);
        $today = now()->startOfDay();

        if ($item->completed_on !== null) {
            $item->update(['completed_on' => null]);

            return;
        }

        $start = $item->rangeStart();
        $completedOn = $today->lt($start) ? $start : $today;

        $item->update(['completed_on' => $completedOn->toDateString()]);
    }

    public function deleteItem(int $id): void
    {
        RoadmapItem::query()->whereKey($id)->delete();
    }

    public function monthStart(): Carbon
    {
        return $this->parseMonth($this->month) ?? now()->startOfMonth()->startOfDay();
    }

    public function render()
    {
        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $today = now()->startOfDay();
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
            'today' => $today->toDateString(),
            'weeks' => $this->weeks($gridStart, $gridEnd, $monthStart, $monthEnd, $items, $today),
            'visibleItems' => $visibleItems,
            'pendingCount' => $visibleItems->filter(fn (RoadmapItem $item) => $item->isPending())->count(),
        ]);
    }

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return list<array{days: list<Carbon>, lanes: list<list<array{id: int, title: string, pending: bool, col: int, span: int, continuesLeft: bool, continuesRight: bool}>>}>
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
     * @return list<list<array{id: int, title: string, pending: bool, col: int, span: int, continuesLeft: bool, continuesRight: bool}>>
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
