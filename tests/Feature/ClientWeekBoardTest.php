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

    public function test_client_rows_reorder_syncs_with_links_order(): void
    {
        $acme = $this->client('Acme', 1);
        $divider = $this->client('Divider', 2);
        $divider->update(['is_delimiter' => true]);
        $globex = $this->client('Globex', 3);
        $initech = $this->client('Initech', 4);

        Livewire::test(ClientWeekBoard::class)
            ->call('reorderClients', [$initech->id, $acme->id, $globex->id])
            ->assertSeeInOrder(['Initech', 'Acme', 'Globex']);

        $this->assertSame(1, $initech->fresh()->position);
        $this->assertSame(2, $divider->fresh()->position);
        $this->assertSame(3, $acme->fresh()->position);
        $this->assertSame(4, $globex->fresh()->position);
    }

    public function test_incomplete_reorder_is_ignored(): void
    {
        $acme = $this->client('Acme', 1);
        $globex = $this->client('Globex', 2);

        Livewire::test(ClientWeekBoard::class)->call('reorderClients', [$globex->id]);

        $this->assertSame(1, $acme->fresh()->position);
        $this->assertSame(2, $globex->fresh()->position);
    }

    public function test_start_creates_running_milestone_and_moves_card_to_wip(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $client = $this->client('Acme');
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Ship landing page']);

        Livewire::test(ClientWeekBoard::class)
            ->call('startCard', $item->id)
            ->call('startCard', $item->id)
            ->assertDontSeeHtml('>Started</a>')
            ->assertDontSee('title="Move to', false);

        $todo = \App\Models\TodoItem::query()->sole();
        $this->assertSame('Acme - Ship landing page', $todo->title);
        $this->assertSame('2026-11-02', $todo->date->toDateString());
        $this->assertTrue($todo->isLogging());
        $this->assertSame(WeekBoardStatus::Wip, $item->fresh()->status);
        $this->assertSame($todo->id, $item->fresh()->todo_item_id);
    }

    public function test_wip_card_with_finished_session_shows_continue_and_starts_new_session(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $client = $this->client('Acme');
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Ship landing page']);

        $component = Livewire::test(ClientWeekBoard::class)->call('startCard', $item->id)
            ->assertDontSeeHtml('>Continue</button>')
            ->assertDontSeeHtml('>Started</a>');

        $todo = \App\Models\TodoItem::query()->sole();
        $todo->update(['logging_started_at' => null]);

        $component->call('$refresh')
            ->assertSeeHtml('>Continue</button>')
            ->assertDontSeeHtml('>Started</a>');

        $this->travelTo('2026-11-02 11:30:00');
        $component->call('continueCard', $item->id)
            ->assertDontSeeHtml('>Continue</button>');

        $todo->refresh();
        $this->assertTrue($todo->isLogging());
        $this->assertSame('2026-11-02 11:30:00', $todo->logging_started_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, \App\Models\TodoItem::query()->count());
        $this->assertSame(WeekBoardStatus::Wip, $item->fresh()->status);

        // Already running: continue is a no-op.
        $this->travelTo('2026-11-02 12:00:00');
        $component->call('continueCard', $item->id);
        $this->assertSame('2026-11-02 11:30:00', $todo->fresh()->logging_started_at->format('Y-m-d H:i:s'));
    }

    public function test_continue_ignores_cards_outside_wip(): void
    {
        $client = $this->client('Acme');
        $todo = \App\Models\TodoItem::query()->create(['date' => '2026-11-02', 'title' => 'x', 'is_completed' => false, 'is_separator' => false, 'position' => 1]);
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'x', 'status' => WeekBoardStatus::Planned, 'todo_item_id' => $todo->id]);

        Livewire::test(ClientWeekBoard::class)->call('continueCard', $item->id);

        $this->assertFalse($todo->fresh()->isLogging());
    }

    public function test_running_card_shows_start_time_and_elapsed_element(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $client = $this->client('Acme');
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Ship landing page']);

        $component = Livewire::test(ClientWeekBoard::class)->assertDontSeeHtml('data-elapsed-since=');
        $component->call('startCard', $item->id);

        $todo = \App\Models\TodoItem::query()->sole();
        $component->assertSeeHtml('data-elapsed-since="'.$todo->logging_started_at->getTimestampMs().'"')
            ->assertSee($todo->logging_started_at->timezone('Europe/Bucharest')->format('H:i'));
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

    public function test_dragging_started_card_to_done_completes_milestone_and_logs_timer(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $client = $this->client('Acme');
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Ship']);

        $component = Livewire::test(ClientWeekBoard::class)->call('startCard', $item->id);

        $this->travelTo('2026-11-02 11:00:00');

        $component->call('reorder', [$item->id], $client->id * 10 + WeekBoardStatus::Done->code());

        $todo = \App\Models\TodoItem::query()->sole();
        $this->assertTrue($todo->is_completed);
        $this->assertFalse($todo->isLogging());
        $log = \App\Models\TaskLog::query()->sole();
        $this->assertSame($todo->id, $log->todo_item_id);
        $this->assertSame(60, (int) $log->started_at->diffInMinutes($log->finished_at));

        $component->call('reorder', [$item->id], $client->id * 10 + WeekBoardStatus::Wip->code());

        $this->assertFalse($todo->fresh()->is_completed);
        $this->assertSame(1, \App\Models\TaskLog::query()->count());

        $component->call('moveCard', $item->id, WeekBoardStatus::Done->value);
        $this->assertTrue($todo->fresh()->is_completed);
    }

    public function test_moving_unstarted_card_to_done_touches_no_milestone(): void
    {
        $client = $this->client('Acme');
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Ship']);

        Livewire::test(ClientWeekBoard::class)->call('moveCard', $item->id, WeekBoardStatus::Done->value);

        $this->assertSame(WeekBoardStatus::Done, $item->fresh()->status);
        $this->assertDatabaseCount('todo_items', 0);
    }

    public function test_page_titles_have_no_app_suffix(): void
    {
        foreach ([
            '/milestones' => 'Milestones',
            '/milestones/drip-matrix' => 'DRIP Matrix',
            '/milestones/log' => 'Log',
            '/backlog' => 'Backlog',
            '/backlog/drip-matrix' => 'Backlog DRIP Matrix',
            '/roadmaps' => 'Timeline',
            '/week-board' => 'Week Board',
            '/comms' => 'Comms',
            '/links/partners' => 'Links',
        ] as $url => $title) {
            $this->get($url)->assertOk()->assertSee('<title>'.$title.'</title>', false);
        }
    }

    public function test_week_toggle_defaults_to_current_week_and_navigates_with_url(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-04 12:00:00', 'Europe/Bucharest')->utc());

        Livewire::test(ClientWeekBoard::class)
            ->assertSeeHtml('data-week-toggle')
            ->assertSee('W45 · 2-8 Nov')
            ->assertDontSeeHtml('data-week-today')
            ->call('nextWeek')
            ->assertSet('week', '2026-W46')
            ->assertSee('W46 · 9-15 Nov')
            ->assertSeeHtml('data-week-today')
            ->call('goToCurrentWeek')
            ->assertSet('week', '')
            ->call('previousWeek')
            ->assertSet('week', '2026-W44');

        Livewire::withQueryParams(['week' => 'garbage'])->test(ClientWeekBoard::class)->assertSee('W45 · 2-8 Nov');
        Livewire::withQueryParams(['week' => '2025-W53'])->test(ClientWeekBoard::class)->assertSee('W45 · 2-8 Nov'); // 2025 has 52 weeks
        Livewire::withQueryParams(['week' => '2026-W53'])->test(ClientWeekBoard::class)->assertSee('W53')
            ->call('nextWeek')->assertSet('week', '2027-W01');
        Livewire::withQueryParams(['week' => '2027-W01'])->test(ClientWeekBoard::class)
            ->call('previousWeek')->assertSet('week', '2026-W53');
        Livewire::withQueryParams(['week' => '2025-W52'])->test(ClientWeekBoard::class)
            ->call('nextWeek')->assertSet('week', '2026-W01');

        $this->get(route('week-board', ['week' => '2026-W40']))->assertOk()->assertSee('W40 · 28 Sep-4 Oct');
    }

    public function test_board_shows_cards_of_the_selected_week_and_carries_unfinished_ones(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-04 12:00:00', 'Europe/Bucharest')->utc());
        $client = $this->client('Acme');

        foreach ([['Old planned', WeekBoardStatus::Planned, '2026-W44'], ['Old done', WeekBoardStatus::Done, '2026-W44'], ['Now done', WeekBoardStatus::Done, '2026-W45'], ['Next planned', WeekBoardStatus::Planned, '2026-W46']] as $i => [$title, $status, $week]) {
            WeekBoardItem::query()->create(['project_id' => $client->id, 'title' => $title, 'status' => $status, 'position' => $i, 'week' => $week]);
        }

        Livewire::test(ClientWeekBoard::class)
            ->assertSee('Old planned')->assertSee('Now done')
            ->assertDontSee('Old done')->assertDontSee('Next planned');

        Livewire::withQueryParams(['week' => '2026-W44'])->test(ClientWeekBoard::class)
            ->assertSee('Old planned')->assertSee('Old done')->assertDontSee('Now done');

        $component = Livewire::withQueryParams(['week' => '2026-W46'])->test(ClientWeekBoard::class)
            ->assertSee('Next planned')->assertSee('Old planned')->assertDontSee('Now done')
            ->call('startAdding', $client->id, 'planned')
            ->set('newTitles.'.$client->id.'-planned', 'Added in W46')
            ->call('addCard', $client->id, 'planned');

        $this->assertDatabaseHas('week_board_items', ['title' => 'Added in W46', 'week' => '2026-W46']);
        Livewire::withQueryParams(['week' => '2026-W45'])->test(ClientWeekBoard::class)->assertDontSee('Added in W46');

        $old = WeekBoardItem::query()->where('title', 'Old planned')->first();
        $component->call('moveCard', $old->id, 'done');
        $this->assertSame('2026-W46', $old->refresh()->week);
        $this->assertNotNull(WeekBoardItem::query()->create(['project_id' => $client->id, 'title' => 'Default', 'status' => WeekBoardStatus::Planned])->week);
        $this->assertSame('2026-W45', WeekBoardItem::query()->where('title', 'Default')->value('week'));
    }
}
