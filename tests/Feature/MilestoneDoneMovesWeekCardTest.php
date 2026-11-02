<?php

namespace Tests\Feature;

use App\Enums\WeekBoardStatus;
use App\Livewire\ClientWeekBoard;
use App\Models\Project;
use App\Models\TodoItem;
use App\Models\WeekBoardItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MilestoneDoneMovesWeekCardTest extends TestCase
{
    use RefreshDatabase;

    private function linkedCard(WeekBoardStatus $status = WeekBoardStatus::Wip): WeekBoardItem
    {
        $client = Project::query()->create(['hub' => 'partners', 'name' => 'Acme', 'slug' => Project::uniqueSlug('partners', 'Acme'), 'position' => 1]);
        $todo = TodoItem::query()->create(['date' => now()->toDateString(), 'title' => 'Acme - Ship', 'position' => 1, 'is_completed' => false]);
        WeekBoardItem::query()->create(['project_id' => $client->id, 'title' => 'Old done', 'status' => WeekBoardStatus::Done, 'position' => 1]);

        return WeekBoardItem::query()->create(['project_id' => $client->id, 'todo_item_id' => $todo->id, 'title' => 'Ship', 'status' => $status, 'position' => 1]);
    }

    public function test_ticking_milestone_moves_card_to_bottom_of_done(): void
    {
        $card = $this->linkedCard();

        $card->todoItem->setCompleted(true);

        $card->refresh();
        $this->assertSame(WeekBoardStatus::Done, $card->status);
        $this->assertSame(2, $card->position);
    }

    public function test_unticking_moves_done_card_back_to_wip(): void
    {
        $card = $this->linkedCard();
        $card->todoItem->setCompleted(true);
        $card->todoItem->fresh()->setCompleted(false);

        $this->assertSame(WeekBoardStatus::Wip, $card->fresh()->status);
    }

    public function test_unlinked_cards_are_untouched_and_board_move_still_ticks(): void
    {
        $card = $this->linkedCard(WeekBoardStatus::Planned);
        $other = WeekBoardItem::query()->create(['project_id' => $card->project_id, 'title' => 'Loose', 'status' => WeekBoardStatus::Wip, 'position' => 2]);

        Livewire::test(ClientWeekBoard::class)->call('moveCard', $card->id, 'done');

        $this->assertTrue($card->todoItem->fresh()->is_completed);
        $this->assertSame(WeekBoardStatus::Done, $card->fresh()->status);
        $this->assertSame(WeekBoardStatus::Wip, $other->fresh()->status);
    }
}
