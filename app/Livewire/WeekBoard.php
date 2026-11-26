<?php

namespace App\Livewire;

use App\Models\TodoItem;
use App\Support\Clock;
use App\Support\WeekLabel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class WeekBoard extends Component
{
    #[Url(as: 'week', history: true)]
    public string $week = '';

    public bool $showOpenItems = false;

    public function mount(): void
    {
        if ($this->week === '' || ! $this->parseWeek($this->week)) {
            $this->week = Clock::currentWeek();
        }
    }

    public function previousWeek(): void
    {
        $start = $this->weekStart();
        $this->week = $start->copy()->subWeek()->format('o-\WW');
    }

    public function nextWeek(): void
    {
        $start = $this->weekStart();
        $this->week = $start->copy()->addWeek()->format('o-\WW');
    }

    public function goToToday(): void
    {
        $this->week = Clock::currentWeek();
    }

    public function weekStart(): Carbon
    {
        $parsed = $this->parseWeek($this->week);

        if (! $parsed) {
            return Clock::today()->startOfWeek(Carbon::MONDAY);
        }

        return $parsed;
    }

    /**
     * @return array<int, Carbon>
     */
    public function days(): array
    {
        $start = $this->weekStart();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->copy()->addDays($i);
        }

        return $days;
    }

    public function weekNumber(): int
    {
        return (int) $this->weekStart()->isoWeek();
    }

    public function focusOpenItem(int $id, string $date): void
    {
        $this->dispatch('open-day', date: $date, itemId: $id);
    }

    /**
     * @return Collection<int, TodoItem>
     */
    public function openItems(): Collection
    {
        $start = $this->weekStart();

        return TodoItem::query()
            ->where('is_separator', false)
            ->where('is_completed', false)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())
            ->orderBy('date')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function render()
    {
        return view('livewire.week-board', [
            'days' => $this->days(),
            'weekNumber' => $this->weekNumber(),
            'weekRange' => WeekLabel::range($this->weekStart()),
            'isCurrentWeek' => $this->week === Clock::currentWeek(),
            'today' => Clock::todayString(),
            'openItems' => $this->openItems(),
        ]);
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
