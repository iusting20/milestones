<?php

namespace App\Livewire;

use App\Enums\DripTag;
use App\Models\TodoItem;
use App\Support\Clock;
use App\Support\Onboarding;
use App\Support\WeekLabel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('DRIP Matrix')]
class DripMatrix extends Component
{
    #[Url(as: 'week', history: true)]
    public string $week = '';

    public string $status = 'all';

    public bool $todayOnly = true;

    public function mount(): void
    {
        if ($this->week === '' || ! $this->parseWeek($this->week)) {
            $this->week = Clock::currentWeek();
        }

        Onboarding::complete('milestone.matrix');
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

    public function weekStart(): Carbon
    {
        return $this->parseWeek($this->week) ?? Clock::today()->startOfWeek(Carbon::MONDAY);
    }

    public function weekNumber(): int
    {
        return (int) $this->weekStart()->isoWeek();
    }

    /**
     * @return Collection<string, Collection<int, TodoItem>>
     */
    public function quadrants(): Collection
    {
        $items = $this->weekTasksQuery()
            ->whereNotNull('drip_tag')
            ->get()
            ->groupBy(fn (TodoItem $item) => $item->drip_tag->value);

        return collect(DripTag::cases())->mapWithKeys(
            fn (DripTag $tag) => [$tag->value => $items->get($tag->value, collect())]
        );
    }

    /**
     * @return Collection<int, TodoItem>
     */
    public function untagged(): Collection
    {
        return $this->weekTasksQuery()
            ->whereNull('drip_tag')
            ->get();
    }

    public function render()
    {
        return view('livewire.drip-matrix', [
            'weekNumber' => $this->weekNumber(),
            'weekRange' => WeekLabel::range($this->weekStart()),
            'quadrants' => $this->quadrants(),
            'untagged' => $this->untagged(),
            'grid' => [
                [DripTag::D, DripTag::I],
                [DripTag::R, DripTag::P],
            ],
        ]);
    }

    /**
     * @return Builder<TodoItem>
     */
    private function weekTasksQuery(): Builder
    {
        $query = TodoItem::query()
            ->with('parent')
            ->where('is_separator', false)
            ->whereDoesntHave('children')
            ->orderBy('date')
            ->orderBy('position')
            ->orderBy('id');

        if ($this->todayOnly) {
            $query->whereDate('date', Clock::todayString());
        } else {
            $start = $this->weekStart()->toDateString();
            $end = $this->weekStart()->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

            $query->whereDate('date', '>=', $start)
                ->whereDate('date', '<=', $end);
        }

        return match ($this->status) {
            'pending' => $query->where('is_completed', false),
            'completed' => $query->where('is_completed', true),
            default => $query,
        };
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
