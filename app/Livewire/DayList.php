<?php

namespace App\Livewire;

use App\Livewire\Concerns\SetsDripTags;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class DayList extends Component
{
    use SetsDripTags;

    public string $date;

    public string $dayLabel = '';

    public bool $isToday = false;

    #[Reactive]
    public bool $showTags = false;

    public bool $expanded = false;

    public string $newTitle = '';

    public bool $adding = false;

    public ?int $editingId = null;

    public string $editTitle = '';

    public ?int $linkingId = null;

    /**
     * @var list<array{title: string, url: string}>
     */
    public array $editLinks = [];

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
            'is_completed' => false,
            'position' => $max + 1,
        ]);

        $this->newTitle = '';
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
        $this->cancelLinking();

        $item = $this->todoItem($id);

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

    public function startLinking(int $id): void
    {
        $this->finishEditing();

        if ($this->linkingId && $this->linkingId !== $id) {
            $this->finishLinking();
        }

        $item = $this->todoItem($id);
        $item->loadMissing('links');

        $this->linkingId = $item->id;
        $this->editLinks = $item->links
            ->map(fn ($link): array => [
                'title' => $link->title,
                'url' => $link->url,
            ])
            ->values()
            ->all();

        if ($this->editLinks === []) {
            $this->editLinks = [$this->emptyLinkRow()];
        }
    }

    public function addLinkRow(): void
    {
        if (! $this->linkingId) {
            return;
        }

        $this->editLinks[] = $this->emptyLinkRow();
    }

    public function cancelLinking(): void
    {
        $this->linkingId = null;
        $this->editLinks = [];
    }

    public function finishLinking(): void
    {
        if (! $this->linkingId) {
            return;
        }

        $prepared = [];

        foreach ($this->editLinks as $row) {
            $title = trim($row['title'] ?? '');
            $url = trim($row['url'] ?? '');

            if ($title === '' && $url === '') {
                continue;
            }

            if ($title === '' || $url === '') {
                return;
            }

            $url = $this->normalizeUrl($url);

            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                return;
            }

            $prepared[] = [
                'title' => $title,
                'url' => $url,
            ];
        }

        $item = $this->todoItem($this->linkingId);

        DB::transaction(function () use ($item, $prepared): void {
            $item->links()->delete();

            foreach ($prepared as $index => $link) {
                $item->links()->create([
                    'title' => $link['title'],
                    'url' => $link['url'],
                    'position' => $index + 1,
                ]);
            }
        });

        $this->cancelLinking();
    }

    /**
     * @return array{title: string, url: string}
     */
    private function emptyLinkRow(): array
    {
        return [
            'title' => '',
            'url' => '',
        ];
    }

    private function normalizeUrl(string $url): string
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return 'https://'.$url;
        }

        return $url;
    }

    public function toggle(int $id): void
    {
        $item = $this->todoItem($id);

        $item->update(['is_completed' => ! $item->is_completed]);
    }

    public function delete(int $id): void
    {
        $this->todoItem($id)->delete();
    }

    public function copyUnfinishedFromPreviousDay(): void
    {
        $previousDate = Carbon::parse($this->date)->subDay()->toDateString();

        $unfinished = TodoItem::query()
            ->with('links')
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

            $copy = TodoItem::query()->create([
                'date' => $this->date,
                'title' => $item->title,
                'is_completed' => false,
                'drip_tag' => $item->drip_tag,
                'position' => $max,
            ]);

            foreach ($item->links as $index => $link) {
                $copy->links()->create([
                    'title' => $link->title,
                    'url' => $link->url,
                    'position' => $index + 1,
                ]);
            }

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
            ->with('links')
            ->whereDate('date', $this->date)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('livewire.day-list', [
            'items' => $items,
        ]);
    }

    protected function findDripTaggable(int $id): Model
    {
        return $this->todoItem($id);
    }

    private function todoItem(int $id): TodoItem
    {
        return TodoItem::query()
            ->whereDate('date', $this->date)
            ->whereKey($id)
            ->firstOrFail();
    }
}
