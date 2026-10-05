<?php

namespace App\Livewire;

use App\Livewire\Concerns\SetsDripTags;
use App\Models\TaskLog;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class WeekList extends Component
{
    use SetsDripTags;

    public string $week = '';

    #[Reactive]
    public bool $showTags = false;

    #[Reactive]
    public bool $showLogControls = false;

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

    public bool $showMoveModal = false;

    public ?int $movingId = null;

    public bool $moveCompleted = false;

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

        $max = (int) $this->weekItemsQuery()->max('position');

        TodoItem::query()->create([
            'date' => null,
            'week' => $this->week,
            'title' => $title,
            'is_completed' => false,
            'is_separator' => false,
            'position' => $max + 1,
        ]);

        $this->newTitle = '';
    }

    public function addDivider(): void
    {
        $max = (int) $this->weekItemsQuery()->max('position');

        TodoItem::query()->create([
            'date' => null,
            'week' => $this->week,
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
            'date' => null,
            'week' => $this->week,
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

        $this->weekItemsQuery()
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

    public function openMoveModal(int $id, bool $completed): void
    {
        $item = $this->todoItem($id);

        if ($item->is_separator) {
            return;
        }

        $this->movingId = $item->id;
        $this->moveCompleted = $completed;
        $this->showMoveModal = true;
    }

    public function closeMoveModal(): void
    {
        $this->showMoveModal = false;
        $this->movingId = null;
        $this->moveCompleted = false;
    }

    public function moveToDay(string $date): void
    {
        if (! $this->movingId) {
            return;
        }

        if (! $this->isDateInWeek($date)) {
            return;
        }

        $item = $this->todoItem($this->movingId);

        if ($item->is_separator) {
            return;
        }

        $completed = $this->moveCompleted;
        $max = (int) TodoItem::query()->whereDate('date', $date)->max('position');

        DB::transaction(function () use ($item, $date, $completed, &$max): void {
            if ($completed && $item->isLogging()) {
                [$startedAt, $finishedAt] = $item->loggingWindow();

                TaskLog::query()->create([
                    'todo_item_id' => $item->id,
                    'title' => $item->title,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                ]);
            }

            $max++;

            $item->update([
                'date' => $date,
                'week' => null,
                'is_completed' => $completed,
                'logging_started_at' => $completed ? null : $item->logging_started_at,
                'position' => $max,
            ]);

            $item->children()->update([
                'date' => $date,
                'week' => null,
            ]);
        });

        $this->closeMoveModal();
        $this->dispatch('todo-assigned-to-day');
    }

    public function delete(int $id): void
    {
        $this->todoItem($id)->delete();
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

            $item = $this->weekItemsQuery()
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
        $items = $this->weekItemsQuery()
            ->with(['links', 'children.links'])
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('livewire.week-list', [
            'items' => $items,
            'selectableDays' => $this->selectableDays(),
        ]);
    }

    protected function findDripTaggable(int $id): Model
    {
        return $this->todoItem($id);
    }

    /**
     * @return list<array{date: string, label: string}>
     */
    private function selectableDays(): array
    {
        $start = $this->weekStart();

        if (! $start) {
            return [];
        }

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
            ];
        }

        return $days;
    }

    private function isDateInWeek(string $date): bool
    {
        foreach ($this->selectableDays() as $day) {
            if ($day['date'] === $date) {
                return true;
            }
        }

        return false;
    }

    private function weekStart(): ?Carbon
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $this->week, $m)) {
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

    private function weekItemsQuery()
    {
        return TodoItem::query()
            ->whereNull('date')
            ->where('week', $this->week);
    }

    private function todoItem(int $id): TodoItem
    {
        return $this->weekItemsQuery()
            ->whereKey($id)
            ->firstOrFail();
    }
}
