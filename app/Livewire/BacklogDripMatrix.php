<?php

namespace App\Livewire;

use App\Enums\DripTag;
use App\Livewire\Concerns\SetsDripTags;
use App\Models\BacklogItem;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Backlog DRIP Matrix')]
class BacklogDripMatrix extends Component
{
    use SetsDripTags;

    #[Url(as: 'period', history: true)]
    public string $period = 'week';

    public function mount(): void
    {
        if (! in_array($this->period, ['today', 'week', 'month'], true)) {
            $this->period = 'week';
        }
    }

    /**
     * @return Collection<string, Collection<int, BacklogItem>>
     */
    public function quadrants(): Collection
    {
        $items = $this->itemsQuery()
            ->whereNotNull('drip_tag')
            ->get()
            ->groupBy(fn (BacklogItem $item) => $item->drip_tag->value);

        return collect(DripTag::cases())->mapWithKeys(
            fn (DripTag $tag) => [$tag->value => $items->get($tag->value, collect())]
        );
    }

    /**
     * @return Collection<int, BacklogItem>
     */
    public function untagged(): Collection
    {
        return $this->itemsQuery()
            ->whereNull('drip_tag')
            ->get();
    }

    public function render()
    {
        return view('livewire.backlog-drip-matrix', [
            'quadrants' => $this->quadrants(),
            'untagged' => $this->untagged(),
            'grid' => [
                [DripTag::D, DripTag::I],
                [DripTag::R, DripTag::P],
            ],
        ]);
    }

    protected function findDripTaggable(int $id): Model
    {
        return BacklogItem::query()
            ->where('is_separator', false)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return Builder<BacklogItem>
     */
    private function itemsQuery(): Builder
    {
        return BacklogItem::query()
            ->where('is_separator', false)
            ->where('created_at', '>=', $this->periodStart()->utc())
            ->orderBy('position')
            ->orderBy('id');
    }

    private function periodStart(): Carbon
    {
        $now = Clock::now();

        return match ($this->period) {
            'today' => $now->copy()->startOfDay(),
            'month' => $now->copy()->startOfMonth()->startOfDay(),
            default => $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
        };
    }
}
