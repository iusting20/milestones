<?php

namespace App\Livewire;

use App\Enums\DripTag;
use App\Models\BacklogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Backlog DRIP Matrix')]
class BacklogDripMatrix extends Component
{
    public string $status = 'all';

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

    /**
     * @return Builder<BacklogItem>
     */
    private function itemsQuery(): Builder
    {
        $query = BacklogItem::query()
            ->where('is_separator', false)
            ->orderBy('position')
            ->orderBy('id');

        return match ($this->status) {
            'pending' => $query->where('is_completed', false),
            'completed' => $query->where('is_completed', true),
            default => $query,
        };
    }
}
