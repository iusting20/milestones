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

    public function test_client_rows_can_be_reordered_without_changing_shared_position(): void
    {
        $acme = $this->client('Acme', 1);
        $globex = $this->client('Globex', 2);
        $initech = $this->client('Initech', 3);

        Livewire::test(ClientWeekBoard::class)
            ->call('reorderClients', [$initech->id, $acme->id])
            ->assertSeeInOrder(['Initech', 'Acme', 'Globex']);

        $this->assertSame(1, $initech->fresh()->week_board_position);
        $this->assertSame(3, $initech->fresh()->position);
        $this->assertNull($globex->fresh()->week_board_position);
    }

    public function test_start_creates_running_milestone_and_moves_card_to_wip(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $client = $this->client('Acme');
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Ship landing page']);

        Livewire::test(ClientWeekBoard::class)
            ->call('startCard', $item->id)
            ->call('startCard', $item->id)
            ->assertSee('Started')
            ->assertDontSee('title="Move to', false);

        $todo = \App\Models\TodoItem::query()->sole();
        $this->assertSame('Acme - Ship landing page', $todo->title);
        $this->assertSame('2026-11-02', $todo->date->toDateString());
        $this->assertTrue($todo->isLogging());
        $this->assertSame(WeekBoardStatus::Wip, $item->fresh()->status);
        $this->assertSame($todo->id, $item->fresh()->todo_item_id);
    }

    public function test_assets_view_shows_asset_rows_and_starts_with_asset_name(): void
    {
        $this->client('Acme', 1);
        $asset = $this->client('Website', 1, 'assets');
        $item = WeekBoardItem::factory()->create(['project_id' => $asset->id, 'title' => 'Fix footer']);

        $this->get(route('week-board', ['view' => 'assets']))
            ->assertOk()
            ->assertSee('Website')
            ->assertDontSee('Acme');

        Livewire::withQueryParams(['view' => 'assets'])
            ->test(ClientWeekBoard::class)
            ->assertSet('view', 'assets')
            ->call('startCard', $item->id);

        $this->assertSame('Website - Fix footer', \App\Models\TodoItem::query()->sole()->title);
    }

    public function test_assets_view_empty_state(): void
    {
        Livewire::test(ClientWeekBoard::class)
            ->set('view', 'assets')
            ->assertSee('No assets yet. Add them under Links → Assets.');
    }

    public function test_cannot_add_card_to_partner_row_from_assets_view(): void
    {
        $client = $this->client('Acme');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(ClientWeekBoard::class)
            ->set('view', 'assets')
            ->set('newTitles.'.$client->id.'-planned', 'Nope')
            ->call('addCard', $client->id, 'planned');
    }

    public function test_can_rename_card_inline_without_renaming_started_milestone(): void
    {
        $client = $this->client('Acme');
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Old']);

        $component = Livewire::test(ClientWeekBoard::class)
            ->call('startCard', $item->id)
            ->call('startEditingCard', $item->id)
            ->assertSet('editCardTitle', 'Old')
            ->set('editCardTitle', '  New title  ')
            ->call('saveCardTitle')
            ->assertSet('editingCardId', null)
            ->assertSee('New title');

        $this->assertSame('New title', $item->fresh()->title);
        $this->assertSame('Acme - Old', \App\Models\TodoItem::query()->sole()->title);

        $component->call('startEditingCard', $item->id)
            ->set('editCardTitle', '   ')
            ->call('saveCardTitle')
            ->call('startEditingCard', $item->id)
            ->set('editCardTitle', 'Discarded')
            ->call('cancelEditingCard');

        $this->assertSame('New title', $item->fresh()->title);
    }

    public function test_cannot_edit_card_from_other_view(): void
    {
        $asset = $this->client('Website', 1, 'assets');
        $item = WeekBoardItem::factory()->create(['project_id' => $asset->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(ClientWeekBoard::class)->call('startEditingCard', $item->id);
    }

    public function test_reorder_ignores_unknown_lane(): void
    {
        $item = WeekBoardItem::factory()->create();

        Livewire::test(ClientWeekBoard::class)->call('reorder', [$item->id], 99999 * 10 + 9);

        $this->assertSame(WeekBoardStatus::Planned, $item->fresh()->status);
    }
}
