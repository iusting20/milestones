<?php

namespace App\Livewire;

use App\Livewire\Concerns\SetsDripTags;
use App\Models\BacklogItem;
use App\Models\TodoItem;
use App\Support\Clock;
use App\Support\Onboarding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Backlog extends Component
{
    use SetsDripTags;

    public string $newTitle = '';

    public bool $adding = false;

    public bool $showTags = false;

    public string $display = 'pending';

    public function startAdding(): void
    {
        $this->adding = true;
        $this->newTitle = '';
    }

    public function finishAdding(): void
    {
        $this->addItem();

        if (trim($this->newTitle) === '') {
            $this->adding = false;
        }
    }

    public function addItem(): void
    {
        $title = trim($this->newTitle);

        if ($title === '') {
            return;
        }

        $max = (int) BacklogItem::query()->max('position');

        BacklogItem::query()->create([
            'title' => $title,
            'is_completed' => false,
            'is_separator' => false,
            'position' => $max + 1,
        ]);

        Onboarding::complete('milestone.backlog');

        $this->newTitle = '';
    }

    public function addDivider(): void
    {
        $max = (int) BacklogItem::query()->max('position');

        BacklogItem::query()->create([
            'title' => null,
            'is_completed' => false,
            'is_separator' => true,
            'position' => $max + 1,
        ]);
    }

    public function toggle(int $id): void
    {
        $item = BacklogItem::query()->findOrFail($id);

        if ($item->is_separator) {
            return;
        }

        $completing = ! $item->is_completed;

        $item->update([
            'is_completed' => $completing,
            'completed_at' => $completing ? now() : null,
        ]);
    }

    public function moveToThisWeek(int $id): void
    {
        $item = BacklogItem::query()->findOrFail($id);

        if ($item->is_separator || blank($item->title)) {
            return;
        }

        $week = Clock::currentWeek();
        $max = (int) TodoItem::query()
            ->whereNull('date')
            ->where('week', $week)
            ->max('position');

        DB::transaction(function () use ($item, $week, $max): void {
            TodoItem::query()->create([
                'date' => null,
                'week' => $week,
                'title' => $item->title,
                'is_completed' => false,
                'is_separator' => false,
                'position' => $max + 1,
            ]);

            $item->delete();
        });
    }

    public function deleteItem(int $id): void
    {
        BacklogItem::query()->whereKey($id)->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            BacklogItem::query()
                ->whereKey($id)
                ->update(['position' => $index + 1]);
        }
    }

    public function render()
    {
        if (! in_array($this->display, ['pending', 'finished', 'all'], true)) {
            $this->display = 'pending';
        }

        $items = BacklogItem::query()
            ->orderBy('position')
            ->orderBy('id');

        if ($this->display === 'pending') {
            $items->where(function ($query): void {
                $query->where('is_separator', true)
                    ->orWhere('is_completed', false);
            });
        } elseif ($this->display === 'finished') {
            $items->where('is_separator', false)
                ->where('is_completed', true);
        }

        return view('livewire.backlog', [
            'items' => $items->get(),
        ]);
    }

    protected function findDripTaggable(int $id): Model
    {
        return BacklogItem::query()->findOrFail($id);
    }
}
