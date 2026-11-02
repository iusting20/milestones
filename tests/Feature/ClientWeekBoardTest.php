<?php

namespace Tests\Feature;

use App\Enums\WeekBoardStatus;
use App\Livewire\ClientWeekBoard;
use App\Models\Project;
use App\Models\WeekBoardItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientWeekBoardTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $name, int $position = 1, string $hub = 'partners'): Project
    {
        return Project::query()->create([
            'hub' => $hub,
            'name' => $name,
            'slug' => Project::uniqueSlug($hub, $name),
            'position' => $position,
        ]);
    }

    public function test_page_renders_a_row_per_partner_with_three_columns(): void
    {
        $this->client('Acme', 1);
        $this->client('Globex', 2);
        $this->client('Internal Asset', 1, 'assets');

        $this->get(route('week-board'))
            ->assertOk()
            ->assertSeeLivewire(ClientWeekBoard::class)
            ->assertSeeInOrder(['Planned', 'WIP', 'Done', 'Acme', 'Globex'])
            ->assertDontSee('Internal Asset');
    }

    public function test_can_add_card_in_client_lane(): void
    {
        $client = $this->client('Acme');

        Livewire::test(ClientWeekBoard::class)
            ->call('startAdding', $client->id, 'wip')
            ->set('newTitles.'.$client->id.'-wip', 'Ship landing page')
            ->call('addCard', $client->id, 'wip')
            ->assertSee('Ship landing page');

        $this->assertDatabaseHas('week_board_items', [
            'project_id' => $client->id,
            'title' => 'Ship landing page',
            'status' => 'wip',
        ]);
    }

    public function test_can_move_card_between_columns(): void
    {
        $item = WeekBoardItem::factory()->create();

        Livewire::test(ClientWeekBoard::class)->call('moveCard', $item->id, 'done');

        $this->assertSame(WeekBoardStatus::Done, $item->fresh()->status);
    }

    public function test_drag_reorder_updates_lane_and_positions(): void
    {
        $client = $this->client('Acme');
        $first = WeekBoardItem::factory()->create(['project_id' => $client->id, 'position' => 1]);
        $second = WeekBoardItem::factory()->create(['project_id' => $client->id, 'position' => 2]);

        Livewire::test(ClientWeekBoard::class)
            ->call('reorder', [$second->id, $first->id], $client->id * 10 + WeekBoardStatus::Wip->code());

        $this->assertSame(WeekBoardStatus::Wip, $second->fresh()->status);
        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
    }

    public function test_reorder_ignores_unknown_lane(): void
    {
        $item = WeekBoardItem::factory()->create();

        Livewire::test(ClientWeekBoard::class)->call('reorder', [$item->id], 99999 * 10 + 9);

        $this->assertSame(WeekBoardStatus::Planned, $item->fresh()->status);
    }
}
