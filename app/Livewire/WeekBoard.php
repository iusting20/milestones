<?php

namespace App\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

class WeekBoard extends Component
{
    #[Url(as: 'week', history: true)]
    public string $week = '';

    public bool $showTags = false;

    public bool $showLogControls = false;

    public function mount(): void
    {
        if ($this->week === '' || ! $this->parseWeek($this->week)) {
            $this->week = now()->format('o-\WW');
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
        $this->week = now()->format('o-\WW');
    }

    public function weekStart(): Carbon
    {
        $parsed = $this->parseWeek($this->week);

        if (! $parsed) {
            return now()->startOfWeek(Carbon::MONDAY);
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

    public function render()
    {
        return view('livewire.week-board', [
            'days' => $this->days(),
            'weekNumber' => $this->weekNumber(),
            'today' => now()->toDateString(),
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
