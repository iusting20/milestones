<?php

namespace App\Livewire;

use App\Support\Clock;
use App\Enums\WeekBoardStatus;
use App\Models\Project;
use App\Models\TodoItem;
use App\Models\WeekBoardItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class ClientWeekBoard extends Component
{
    /**
     * Which Links hub supplies the rows: 'partners' or 'assets'.
     */
    #[Url(as: 'view', history: true)]
    public string $view = 'partners';

    /**
     * New card titles keyed by "{projectId}-{status}".
     *
     * @var array<string, string>
     */
    public array $newTitles = [];

    public ?string $addingLane = null;

    public ?int $editingCardId = null;

    public string $editCardTitle = '';

    public function startAdding(int $projectId, string $status): void
    {
        $this->addingLane = $projectId.'-'.$status;
        $this->newTitles[$this->addingLane] = '';
    }

    public function cancelAdding(): void
    {
        $this->addingLane = null;
    }

    public function addCard(int $projectId, string $status): void
    {
        $lane = $projectId.'-'.$status;
        $title = trim($this->newTitles[$lane] ?? '');
        $statusEnum = WeekBoardStatus::tryFrom($status);

        if ($title === '' || $statusEnum === null) {
            return;
        }

        $project = $this->clientsQuery()->findOrFail($projectId);

        $max = (int) WeekBoardItem::query()
            ->where('project_id', $project->id)
            ->where('status', $statusEnum)
            ->max('position');

        WeekBoardItem::query()->create([
            'project_id' => $project->id,
            'title' => $title,
            'status' => $statusEnum,
            'position' => $max + 1,
        ]);

        $this->newTitles[$lane] = '';
    }

    public function moveCard(int $id, string $status): void
    {
        $statusEnum = WeekBoardStatus::tryFrom($status);

        if ($statusEnum === null) {
            return;
        }

        $item = WeekBoardItem::query()->findOrFail($id);

        if ($item->status === $statusEnum) {
            return;
        }

        $max = (int) WeekBoardItem::query()
            ->where('project_id', $item->project_id)
            ->where('status', $statusEnum)
            ->max('position');

        $previous = $item->status;

        $item->update(['status' => $statusEnum, 'position' => $max + 1]);

        $this->syncMilestone($item, $previous);
    }

    /**
     * Moving a card into Done ticks its linked milestone (same as /milestones, which also
     * stops a running timer); moving it out of Done unticks it. Cards never started have no milestone.
     */
    private function syncMilestone(WeekBoardItem $item, WeekBoardStatus $previous): void
    {
        $done = WeekBoardStatus::Done;

        if (($item->status === $done) === ($previous === $done) || $item->todo_item_id === null) {
            return;
        }

        $todo = TodoItem::query()->find($item->todo_item_id);

        if ($todo !== null && $todo->is_completed !== ($item->status === $done)) {
            $todo->setCompleted($item->status === $done);
        }
    }

    /**
     * Create a milestone for today titled "<Client> - <Card>", start its timer
     * (same fields DayList::addItem and DayList::startLogging set) and move the card to WIP.
     */
    public function startCard(int $id): void
    {
        $item = WeekBoardItem::query()->with(['project', 'todoItem'])->findOrFail($id);

        if ($item->todoItem !== null) {
            return;
        }

        $today = Clock::todayString();

        DB::transaction(function () use ($item, $today): void {
            $max = (int) TodoItem::query()->whereDate('date', $today)->max('position');

            $todo = TodoItem::query()->create([
                'date' => $today,
                'title' => $item->project->name.' - '.$item->title,
                'is_completed' => false,
                'is_separator' => false,
                'position' => $max + 1,
            ]);

            $todo->update(['logging_started_at' => now()]);

            $this->moveCard($item->id, WeekBoardStatus::Wip->value);

            $item->refresh()->update(['todo_item_id' => $todo->id]);
        });
    }

    public function startEditingCard(int $id): void
    {
        $item = $this->cardInView($id);

        $this->editingCardId = $item->id;
        $this->editCardTitle = $item->title;
    }

    /**
     * Save the edited card title (card only; an already-started milestone keeps its title).
     */
    public function saveCardTitle(): void
    {
        if ($this->editingCardId === null) {
            return;
        }

        $title = trim($this->editCardTitle);

        if ($title !== '') {
            $item = $this->cardInView($this->editingCardId);

            if ($item->title !== $title) {
                $item->update(['title' => $title]);
            }
        }

        $this->cancelEditingCard();
    }

    public function cancelEditingCard(): void
    {
        $this->editingCardId = null;
        $this->editCardTitle = '';
    }

    public function deleteCard(int $id): void
    {
        WeekBoardItem::query()->whereKey($id)->delete();
    }

    /**
     * Called by the shared Sortable script. The lane is encoded as
     * projectId * 10 + status code, passed through data-parent-id.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds, ?int $lane = null): void
    {
        if ($lane === null) {
            return;
        }

        $status = WeekBoardStatus::fromCode($lane % 10);
        $projectId = intdiv($lane, 10);

        if ($status === null || ! $this->clientsQuery()->whereKey($projectId)->exists()) {
            return;
        }

        DB::transaction(function () use ($orderedIds, $status, $projectId): void {
            foreach (array_values($orderedIds) as $index => $id) {
                $card = WeekBoardItem::query()->find($id);

                if ($card === null) {
                    continue;
                }

                $previous = $card->status;

                $card->update([
                    'project_id' => $projectId,
                    'status' => $status,
                    'position' => $index + 1,
                ]);

                $this->syncMilestone($card, $previous);
            }
        });
    }

    /**
     * Persist the Week Board row order (does not affect Links or Comms ordering).
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorderClients(array $orderedIds): void
    {
        // Shares the Links page order (projects.position): delimiters and other
        // hidden rows keep their slots; visible clients fill theirs in the new order.
        $orderedIds = array_values(array_map(intval(...), $orderedIds));

        DB::transaction(function () use ($orderedIds): void {
            $all = Project::query()
                ->where('hub', $this->hub())
                ->where('is_archived', false)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $visible = $all->where('is_delimiter', false)->pluck('id')->all();

            if (count($visible) !== count($orderedIds) || array_diff($visible, $orderedIds) !== []) {
                return;
            }

            $queue = $orderedIds;
            $position = 1;

            foreach ($all as $project) {
                $id = $project->is_delimiter ? $project->id : array_shift($queue);
                Project::query()->whereKey($id)->update(['position' => $position++]);
            }
        });
    }

    public function render()
    {
        $clients = $this->clientsQuery()->get();

        $items = WeekBoardItem::query()
            ->with('todoItem')
            ->whereIn('project_id', $clients->pluck('id'))
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (WeekBoardItem $item): string => $item->project_id.'-'.$item->status->value);

        return view('livewire.client-week-board', [
            'clients' => $clients,
            'hub' => $this->hub(),
            'items' => $items,
            'statuses' => WeekBoardStatus::cases(),
        ]);
    }

    private function cardInView(int $id): WeekBoardItem
    {
        return WeekBoardItem::query()
            ->whereKey($id)
            ->whereIn('project_id', $this->clientsQuery()->select('id'))
            ->firstOrFail();
    }

    private function hub(): string
    {
        return $this->view === 'assets' ? 'assets' : 'partners';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Project>
     */
    private function clientsQuery()
    {
        return Project::query()
            ->where('hub', $this->hub())
            ->where('is_delimiter', false)
            ->where('is_archived', false)
            ->orderBy('position')
            ->orderBy('id');
    }
}
