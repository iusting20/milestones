<?php

namespace App\Livewire;

use App\Enums\WeekBoardStatus;
use App\Models\Project;
use App\Models\TodoItem;
use App\Models\WeekBoardItem;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ClientWeekBoard extends Component
{
    /**
     * New card titles keyed by "{projectId}-{status}".
     *
     * @var array<string, string>
     */
    public array $newTitles = [];

    public ?string $addingLane = null;

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

        $item->update(['status' => $statusEnum, 'position' => $max + 1]);
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

        $today = now()->toDateString();

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
                WeekBoardItem::query()
                    ->whereKey($id)
                    ->update([
                        'project_id' => $projectId,
                        'status' => $status->value,
                        'position' => $index + 1,
                    ]);
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
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                Project::query()
                    ->where('hub', 'partners')
                    ->whereKey($id)
                    ->update(['week_board_position' => $index + 1]);
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
            'items' => $items,
            'statuses' => WeekBoardStatus::cases(),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Project>
     */
    private function clientsQuery()
    {
        return Project::query()
            ->where('hub', 'partners')
            ->where('is_delimiter', false)
            ->where('is_archived', false)
            ->orderByRaw('week_board_position is null')
            ->orderBy('week_board_position')
            ->orderBy('position')
            ->orderBy('id');
    }
}
