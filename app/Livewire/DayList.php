<?php

namespace App\Livewire;

use App\Models\TodoItem;
use Carbon\Carbon;
use Livewire\Component;

class DayList extends Component
{
    public string $date;

    public string $dayLabel = '';

    public bool $isToday = false;

    public bool $expanded = false;

    public string $newTitle = '';

    public bool $adding = false;

    public ?int $editingId = null;

    public string $editTitle = '';

    public function mount(): void
    {
        $this->expanded = $this->isToday;
    }

    public function toggleExpanded(): void
    {
        if ($this->isToday) {
            return;
        }

        $this->expanded = ! $this->expanded;
    }

    public function startAdding(): void
    {
        $this->adding = true;
        $this->newTitle = '';
    }

    public function cancelAdding(): void
    {
        $this->adding = false;
        $this->newTitle = '';
    }

    public function addItem(): void
    {
        $title = trim($this->newTitle);

        if ($title === '') {
            return;
        }

        $max = (int) TodoItem::query()->whereDate('date', $this->date)->max('position');

        TodoItem::query()->create([
            'date' => $this->date,
            'title' => $title,
            'url' => null,
            'is_completed' => false,
            'position' => $max + 1,
        ]);

        $this->newTitle = '';
    }

    public function updatedNewTitle(string $value): void
    {
        if (trim($value) !== '') {
            $this->addItem();
        }
    }

    public function finishAdding(): void
    {
        $this->addItem();

        if (trim($this->newTitle) === '') {
            $this->adding = false;
        }
    }

    public function startEditing(int $id): void
    {
        $this->saveEdit();

        $item = TodoItem::query()
            ->whereDate('date', $this->date)
            ->whereKey($id)
            ->firstOrFail();

        $this->editingId = $item->id;
        $this->editTitle = $item->title;
    }

    public function saveEdit(): void
    {
        if (! $this->editingId) {
            return;
        }

        $title = trim($this->editTitle);

        if ($title === '') {
            return;
        }

        TodoItem::query()
            ->whereDate('date', $this->date)
            ->whereKey($this->editingId)
            ->update(['title' => $title]);
    }

    public function updatedEditTitle(): void
    {
        $this->saveEdit();
    }

    public function finishEditing(): void
    {
        $this->saveEdit();
        $this->editingId = null;
        $this->editTitle = '';
    }

    public function toggle(int $id): void
    {
        $item = TodoItem::query()
            ->whereDate('date', $this->date)
            ->whereKey($id)
            ->firstOrFail();

        $item->update(['is_completed' => ! $item->is_completed]);
    }

    public function delete(int $id): void
    {
        TodoItem::query()
            ->whereDate('date', $this->date)
            ->whereKey($id)
            ->delete();
    }

    public function copyUnfinishedFromPreviousDay(): void
    {
        $previousDate = Carbon::parse($this->date)->subDay()->toDateString();

        $unfinished = TodoItem::query()
            ->whereDate('date', $previousDate)
            ->where('is_completed', false)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($unfinished->isEmpty()) {
            return;
        }

        $max = (int) TodoItem::query()->whereDate('date', $this->date)->max('position');
        $existingTitles = TodoItem::query()
            ->whereDate('date', $this->date)
            ->pluck('title')
            ->all();

        foreach ($unfinished as $item) {
            if (in_array($item->title, $existingTitles, true)) {
                continue;
            }

            $max++;

            TodoItem::query()->create([
                'date' => $this->date,
                'title' => $item->title,
                'url' => $item->url,
                'is_completed' => false,
                'position' => $max,
            ]);

            $existingTitles[] = $item->title;
        }
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            TodoItem::query()
                ->whereDate('date', $this->date)
                ->whereKey($id)
                ->update(['position' => $index + 1]);
        }
    }

    public function render()
    {
        $items = TodoItem::query()
            ->whereDate('date', $this->date)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('livewire.day-list', [
            'items' => $items,
        ]);
    }
}
