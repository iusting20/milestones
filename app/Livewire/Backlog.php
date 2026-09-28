<?php

namespace App\Livewire;

use App\Livewire\Concerns\SetsDripTags;
use App\Models\BacklogItem;
use App\Models\TodoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Backlog extends Component
{
    use SetsDripTags;

    public string $newTitle = '';

    public bool $showTags = false;

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

        $item->update([
            'is_completed' => ! $item->is_completed,
        ]);
    }

    public function moveToToday(int $id): void
    {
        $item = BacklogItem::query()->findOrFail($id);

        if ($item->is_separator || blank($item->title)) {
            return;
        }

        $today = now()->toDateString();
        $max = (int) TodoItem::query()->whereDate('date', $today)->max('position');

        DB::transaction(function () use ($item, $today, $max): void {
            TodoItem::query()->create([
                'date' => $today,
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
        return view('livewire.backlog', [
            'items' => BacklogItem::query()
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
        ]);
    }

    protected function findDripTaggable(int $id): Model
    {
        return BacklogItem::query()->findOrFail($id);
    }
}
