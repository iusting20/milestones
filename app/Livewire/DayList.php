<?php

namespace App\Livewire;

use App\Livewire\Concerns\EditsLoggingStart;
use App\Livewire\Concerns\SetsDripTags;
use App\Models\TaskLog;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class DayList extends Component
{
    use EditsLoggingStart;
    use SetsDripTags;

    public string $date;

    public string $dayLabel = '';

    public bool $isToday = false;

    #[Reactive]
    public bool $showTags = false;

    #[Reactive]
    public bool $showLogControls = false;

    public bool $expanded = false;

    public string $newTitle = '';

    public bool $adding = false;

    public ?int $addingChildFor = null;

    public string $newChildTitle = '';

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

    #[On('todo-assigned-to-day')]
    public function refreshAfterAssignment(): void
    {
        //
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
            'is_separator' => false,
            'position' => $max + 1,
        ]);

        $this->newTitle = '';
    }

    public function addDivider(): void
    {
        $max = (int) TodoItem::query()->whereDate('date', $this->date)->max('position');

        TodoItem::query()->create([
            'date' => $this->date,
            'title' => null,
            'is_completed' => false,
            'is_separator' => true,
            'position' => $max + 1,
        ]);
    }

    public function finishAdding(): void
    {
        $this->addItem();

        if (trim($this->newTitle) === '') {
            $this->adding = false;
        }
    }

    public function startAddingChild(int $id): void
    {
        $item = $this->todoItem($id);

        if ($item->is_separator || $item->parent_id !== null) {
            return;
        }

        $this->cancelAdding();
        $this->cancelLinking();

        if ($this->addingChildFor === $item->id) {
            $this->cancelAddingChild();

            return;
        }

        $this->addingChildFor = $item->id;
        $this->newChildTitle = '';
    }

    public function cancelAddingChild(): void
    {
        $this->addingChildFor = null;
        $this->newChildTitle = '';
    }

    public function addChild(): void
    {
        if (! $this->addingChildFor) {
            return;
        }

        $title = trim($this->newChildTitle);

        if ($title === '') {
            return;
        }

        $parent = $this->todoItem($this->addingChildFor);

        if ($parent->is_separator || $parent->parent_id !== null) {
            return;
        }

        $max = (int) TodoItem::query()->where('parent_id', $parent->id)->max('position');

        TodoItem::query()->create([
            'date' => $this->date,
            'parent_id' => $parent->id,
            'title' => $title,
            'is_completed' => false,
            'is_separator' => false,
            'position' => $max + 1,
        ]);

        $this->newChildTitle = '';
    }

    public function startEditing(int $id): void
    {
        $this->saveEdit();
        $this->cancelLinking();

        $item = $this->todoItem($id);

        if ($item->is_separator) {
            return;
        }

        $this->editingId = $item->id;
        $this->editTitle = $item->title ?? '';
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

        if ($item->is_separator) {
            return;
        }

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

        if ($item->is_separator) {
            return;
        }

        $item->setCompleted(! $item->is_completed);
    }

    public function startLogging(int $id): void
    {
        $this->todoItem($id)->startLogging();
    }

    public function finishLogging(int $id): void
    {
        $item = $this->todoItem($id);

        if ($item->is_separator || ! $item->isLogging()) {
            return;
        }

        $item->finishLogging();
    }

    public function delete(int $id): void
    {
        $this->todoItem($id)->delete();
    }

    public function moveToThisWeek(int $id): void
    {
        if (! $this->isToday) {
            return;
        }

        $item = $this->todoItem($id);

        if ($item->is_separator) {
            return;
        }

        $week = Carbon::parse($this->date)->format('o-\WW');
        $max = (int) TodoItem::query()->whereNull('date')->where('week', $week)->max('position');

        DB::transaction(function () use ($item, $week, &$max): void {
            $max++;

            $item->update([
                'date' => null,
                'week' => $week,
                'parent_id' => null,
                'position' => $max,
            ]);

            $item->children()->update([
                'date' => null,
                'week' => $week,
            ]);
        });

        $this->dispatch('todo-moved-to-week');
    }

    public function copyUnfinishedFromPreviousDay(): void
    {
        $previousDate = Carbon::parse($this->date)->subDay()->toDateString();

        $unfinished = TodoItem::query()
            ->with('links')
            ->whereDate('date', $previousDate)
            ->where('is_completed', false)
            ->where('is_separator', false)
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

        $roots = $unfinished->whereNull('parent_id');
        $copiedIds = [];
        $childPositions = [];

        foreach ($roots as $item) {
            $copy = $this->copyUnfinishedItem($item, null, $max, $existingTitles);

            if (! $copy) {
                continue;
            }

            $copiedIds[$item->id] = $copy->id;
            $childPositions[$copy->id] = 0;
        }

        foreach ($unfinished->whereNotNull('parent_id') as $item) {
            $parentCopyId = $copiedIds[$item->parent_id] ?? null;

            if ($parentCopyId) {
                $this->copyUnfinishedItem($item, $parentCopyId, $childPositions[$parentCopyId], $existingTitles);
            } else {
                $this->copyUnfinishedItem($item, null, $max, $existingTitles);
            }
        }
    }

    /**
     * @param  list<string>  $existingTitles
     */
    private function copyUnfinishedItem(TodoItem $item, ?int $parentId, int &$position, array &$existingTitles): ?TodoItem
    {
        if (in_array($item->title, $existingTitles, true)) {
            return null;
        }

        $position++;

        $copy = TodoItem::query()->create([
            'date' => $this->date,
            'parent_id' => $parentId,
            'title' => $item->title,
            'is_completed' => false,
            'is_separator' => false,
            'drip_tag' => $item->drip_tag,
            'position' => $position,
        ]);

        foreach ($item->links as $index => $link) {
            $copy->links()->create([
                'title' => $link->title,
                'url' => $link->url,
                'position' => $index + 1,
            ]);
        }

        $existingTitles[] = $item->title;

        return $copy;
    }

    public function nest(int $id, int $parentId): void
    {
        if ($id === $parentId) {
            return;
        }

        $item = $this->todoItem($id);
        $parent = $this->todoItem($parentId);

        if ($item->is_separator || $parent->is_separator || $parent->parent_id !== null || $item->children()->exists()) {
            return;
        }

        $max = (int) TodoItem::query()->where('parent_id', $parent->id)->max('position');

        $item->update([
            'parent_id' => $parent->id,
            'position' => $max + 1,
        ]);
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds, ?int $parentId = null): void
    {
        if ($parentId !== null) {
            $parent = $this->todoItem($parentId);

            if ($parent->parent_id !== null) {
                return;
            }
        }

        foreach ($orderedIds as $index => $id) {
            if ($parentId !== null && (int) $id === $parentId) {
                continue;
            }

            $item = TodoItem::query()
                ->whereDate('date', $this->date)
                ->whereKey($id)
                ->first();

            if (! $item) {
                continue;
            }

            if ($parentId !== null && ($item->is_separator || $item->children()->exists())) {
                continue;
            }

            $item->update([
                'position' => $index + 1,
                'parent_id' => $parentId,
            ]);
        }
    }

    public function render()
    {
        $items = TodoItem::query()
            ->with(['links', 'children.links'])
            ->whereDate('date', $this->date)
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('livewire.day-list', [
            'items' => $items,
            'itemCount' => $items->count() + $items->sum(fn (TodoItem $item) => $item->children->count()),
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
