<?php

namespace Tests\Feature;

use App\Livewire\Roadmaps;
use App\Models\RoadmapItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoadmapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_roadmaps_page_renders_current_month(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $this->get(route('roadmaps'))
            ->assertOk()
            ->assertSee('Timeline')
            ->assertSee('SEPTEMBER 2026')
            ->assertSeeLivewire(Roadmaps::class);
    }

    public function test_year_minimap_uses_event_colors(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $pending = RoadmapItem::query()->create([
            'title' => 'Amber range',
            'started_on' => '2026-03-04',
            'completed_on' => null,
        ]);
        RoadmapItem::query()->create([
            'title' => 'Finished range',
            'started_on' => '2026-03-10',
            'completed_on' => '2026-03-12',
        ]);

        $pendingColor = ['#ffc107', '#ff9800', '#ff5722', '#e91e63', '#9c27b0', '#3f51b5', '#2196f3', '#00bcd4'][$pending->id % 8];

        Livewire::test(Roadmaps::class)
            ->assertSee($pendingColor, false)
            ->assertSee('#7cb518', false);
    }

    public function test_year_minimap_jumps_to_a_month(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        Livewire::test(Roadmaps::class)
            ->assertSee('JAN')
            ->assertSee('DEC')
            ->call('showMonth', '2026-03')
            ->assertSet('month', '2026-03')
            ->assertSee('MARCH 2026');
    }

    public function test_nav_shows_week_board_before_timeline_and_backlog_on_page(): void
    {
        $this->get(route('milestones'))
            ->assertSeeInOrder(['Milestones', 'Week Board', 'Timeline', 'Comms', 'Links', 'For this week', 'Backlog'])
            ->assertSee(route('backlog', absolute: false), false);
    }

    public function test_can_add_pending_range_from_selected_day(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        Livewire::test(Roadmaps::class)
            ->call('openCreate')
            ->assertSet('creating', true)
            ->assertSet('startedOn', '2026-09-18')
            ->set('newTitle', 'Auth rewrite')
            ->set('startedOn', '2026-09-03')
            ->call('addItem')
            ->assertSet('creating', false)
            ->assertSee('Auth rewrite')
            ->assertSee('pending')
            ->assertSee('Add project');

        $item = RoadmapItem::query()->where('title', 'Auth rewrite')->first();

        $this->assertNotNull($item);
        $this->assertSame('2026-09-03', $item->started_on->toDateString());
        $this->assertNull($item->completed_on);
    }

    public function test_status_counts_split_active_completed_and_future_ranges(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        RoadmapItem::factory()->create([
            'title' => 'In progress',
            'started_on' => '2026-09-01',
            'completed_on' => null,
        ]);
        RoadmapItem::factory()->create([
            'title' => 'Finished',
            'started_on' => '2026-09-02',
            'completed_on' => '2026-09-10',
        ]);
        RoadmapItem::factory()->create([
            'title' => 'Later',
            'started_on' => '2026-10-01',
            'completed_on' => null,
        ]);

        Livewire::test(Roadmaps::class)
            ->assertSee('1 active')
            ->assertSee('1 completed')
            ->assertSee('1 not yet started');
    }

    public function test_does_not_add_blank_title(): void
    {
        Livewire::test(Roadmaps::class)
            ->set('newTitle', '   ')
            ->call('addItem');

        $this->assertDatabaseCount('roadmap_items', 0);
    }

    public function test_can_complete_and_reopen_a_range(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Billing',
            'started_on' => '2026-09-10',
            'completed_on' => null,
        ]);

        Livewire::test(Roadmaps::class)
            ->call('toggleComplete', $item->id)
            ->assertSee('18 Sep')
            ->assertDontSee('>pending<', false);

        $this->assertSame('2026-09-18', $item->fresh()->completed_on->toDateString());

        Livewire::test(Roadmaps::class)
            ->call('toggleComplete', $item->id);

        $this->assertNull($item->fresh()->completed_on);
    }

    public function test_can_delete_a_range(): void
    {
        $item = RoadmapItem::factory()->create([
            'title' => 'Drop me',
            'started_on' => now()->toDateString(),
        ]);

        Livewire::test(Roadmaps::class)
            ->call('deleteItem', $item->id);

        $this->assertModelMissing($item);
    }

    public function test_month_query_renders_that_month(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        Livewire::withQueryParams(['month' => '2026-03'])
            ->test(Roadmaps::class)
            ->assertSet('month', '2026-03')
            ->assertSee('MARCH 2026');
    }

    public function test_hides_ranges_that_do_not_overlap_the_month(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        RoadmapItem::factory()->create([
            'title' => 'August only',
            'started_on' => '2026-08-01',
            'completed_on' => '2026-08-10',
        ]);
        RoadmapItem::factory()->create([
            'title' => 'Crosses in',
            'started_on' => '2026-08-28',
            'completed_on' => '2026-09-04',
        ]);
        RoadmapItem::factory()->create([
            'title' => 'Still open',
            'started_on' => '2026-08-01',
            'completed_on' => null,
        ]);

        Livewire::test(Roadmaps::class)
            ->assertDontSee('August only')
            ->assertSee('Crosses in')
            ->assertSee('Still open')
            ->assertSee('pending');
    }

    public function test_completion_cannot_precede_start_date(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Future start',
            'started_on' => '2026-09-22',
            'completed_on' => null,
        ]);

        Livewire::test(Roadmaps::class)
            ->call('toggleComplete', $item->id);

        $this->assertSame('2026-09-22', $item->fresh()->completed_on->toDateString());
    }

    public function test_can_update_started_on_inline(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Shift me',
            'started_on' => '2026-09-10',
            'completed_on' => '2026-09-15',
        ]);

        Livewire::test(Roadmaps::class)
            ->call('updateStartedOn', $item->id, '2026-09-05')
            ->assertSee('5 Sep');

        $this->assertSame('2026-09-05', $item->fresh()->started_on->toDateString());
        $this->assertSame('2026-09-15', $item->fresh()->completed_on->toDateString());
    }

    public function test_moving_start_after_completion_clamps_completion(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Clamp me',
            'started_on' => '2026-09-01',
            'completed_on' => '2026-09-05',
        ]);

        Livewire::test(Roadmaps::class)
            ->call('updateStartedOn', $item->id, '2026-09-10');

        $fresh = $item->fresh();
        $this->assertSame('2026-09-10', $fresh->started_on->toDateString());
        $this->assertSame('2026-09-10', $fresh->completed_on->toDateString());
    }

    public function test_can_set_and_clear_completion_date_inline(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Finish me',
            'started_on' => '2026-09-10',
            'completed_on' => null,
        ]);

        Livewire::test(Roadmaps::class)
            ->call('updateCompletedOn', $item->id, '2026-09-12')
            ->assertSee('12 Sep')
            ->assertDontSee('>pending<', false);

        $this->assertSame('2026-09-12', $item->fresh()->completed_on->toDateString());

        Livewire::test(Roadmaps::class)
            ->call('updateCompletedOn', $item->id, '');

        $this->assertNull($item->fresh()->completed_on);
    }

    public function test_inline_completion_cannot_precede_start(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Order',
            'started_on' => '2026-09-10',
            'completed_on' => null,
        ]);

        Livewire::test(Roadmaps::class)
            ->call('updateCompletedOn', $item->id, '2026-09-01');

        $this->assertSame('2026-09-10', $item->fresh()->completed_on->toDateString());
    }

    public function test_calendar_shows_title_only_on_first_and_last_week_segments(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        RoadmapItem::factory()->create([
            'title' => 'Span many weeks',
            'started_on' => '2026-09-01',
            'completed_on' => '2026-09-20',
        ]);

        $html = Livewire::test(Roadmaps::class)->html();

        preg_match_all('/<span class="truncate">Span many weeks<\/span>/', $html, $matches);

        $this->assertCount(2, $matches[0]);
    }

    public function test_header_uses_subtle_month_toggle_like_week_board(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        Livewire::test(Roadmaps::class)
            ->assertSee('data-month-toggle', false)
            ->assertSee('SEPTEMBER 2026')
            ->assertDontSee('data-month-today', false)
            ->call('nextMonth')
            ->assertSet('month', '2026-10')
            ->assertSee('OCTOBER 2026')
            ->assertSee('data-month-today', false)
            ->call('goToThisMonth')
            ->assertSet('month', '2026-09');
    }

    public function test_can_add_item_with_scheduled_finish(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        Livewire::test(Roadmaps::class)
            ->call('openCreate')
            ->assertSet('finishOn', '')
            ->set('newTitle', 'Planned work')
            ->set('startedOn', '2026-09-03')
            ->set('finishOn', '2026-09-25')
            ->call('addItem')
            ->assertSee('25 Sep');

        $item = RoadmapItem::query()->where('title', 'Planned work')->firstOrFail();
        $this->assertSame('2026-09-25', $item->finish_on->toDateString());

        Livewire::test(Roadmaps::class)
            ->call('openCreate')
            ->set('newTitle', 'Backwards finish')
            ->set('startedOn', '2026-09-10')
            ->set('finishOn', '2026-09-01')
            ->call('addItem');

        $this->assertSame('2026-09-10', RoadmapItem::query()->where('title', 'Backwards finish')->firstOrFail()->finish_on->toDateString());
    }

    public function test_can_set_clamp_and_clear_scheduled_finish_inline(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Finish line',
            'started_on' => '2026-09-10',
        ]);

        Livewire::test(Roadmaps::class)->call('updateFinishOn', $item->id, '2026-09-24');
        $this->assertSame('2026-09-24', $item->fresh()->finish_on->toDateString());

        Livewire::test(Roadmaps::class)->call('updateFinishOn', $item->id, '2026-09-01');
        $this->assertSame('2026-09-10', $item->fresh()->finish_on->toDateString());

        Livewire::test(Roadmaps::class)->call('updateFinishOn', $item->id, '');
        $this->assertNull($item->fresh()->finish_on);
    }

    public function test_moving_start_after_finish_clamps_finish(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Clamp finish',
            'started_on' => '2026-09-01',
            'finish_on' => '2026-09-05',
        ]);

        Livewire::test(Roadmaps::class)->call('updateStartedOn', $item->id, '2026-09-10');

        $this->assertSame('2026-09-10', $item->fresh()->finish_on->toDateString());
    }

    public function test_pending_item_bar_runs_to_scheduled_finish_with_start_and_finish_markers(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        RoadmapItem::factory()->create([
            'title' => 'Future finish',
            'started_on' => '2026-09-15',
            'finish_on' => '2026-09-24',
        ]);

        $html = Livewire::test(Roadmaps::class)->html();

        $this->assertStringContainsString('data-marker="start"', $html);
        $this->assertStringContainsString('data-marker="finish"', $html);
        $this->assertStringContainsString('data-date="2026-09-15"', $html);
        $this->assertStringContainsString('data-date="2026-09-24"', $html);
        $this->assertStringNotContainsString('data-marker="done"', $html);
        // Thu 24 Sep is the 4th day (offset 3) of its Mon-Sun week segment.
        $this->assertMatchesRegularExpression('/style="--at: 3"\s+data-marker="finish"/', $html);
    }

    public function test_late_completion_extends_bar_and_keeps_finish_marker_on_scheduled_day(): void
    {
        $this->travelTo('2026-09-28 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Ran late',
            'started_on' => '2026-09-07',
            'finish_on' => '2026-09-16',
            'completed_on' => '2026-09-23',
        ]);

        $this->assertTrue($item->completedLate());
        $this->assertSame('2026-09-23', $item->rangeEnd()->toDateString());

        $html = Livewire::test(Roadmaps::class)->html();

        $this->assertStringContainsString('data-marker="finish"', $html);
        $this->assertStringContainsString('data-date="2026-09-16"', $html);
        $this->assertStringContainsString('data-marker="done"', $html);
        $this->assertStringContainsString('data-date="2026-09-23"', $html);
        // Bar continues into the week of 21-27 Sep, ending (with the checkmark) on Wed 23 Sep.
        $this->assertMatchesRegularExpression('/style="--at: 2"\s+data-marker="done"/', $html);
    }

    public function test_early_completion_shows_checkmark_inside_bar_up_to_finish_day(): void
    {
        $this->travelTo('2026-09-28 12:00:00');

        $item = RoadmapItem::factory()->create([
            'title' => 'Done early',
            'started_on' => '2026-09-14',
            'finish_on' => '2026-09-18',
            'completed_on' => '2026-09-16',
        ]);

        $this->assertFalse($item->completedLate());
        $this->assertSame('2026-09-18', $item->rangeEnd()->toDateString());

        $html = Livewire::test(Roadmaps::class)->html();

        $this->assertMatchesRegularExpression('/style="--at: 0"\s+data-marker="start"/', $html);
        $this->assertMatchesRegularExpression('/style="--at: 4"\s+data-marker="finish"/', $html);
        $this->assertMatchesRegularExpression('/style="--at: 2"\s+data-marker="done"/', $html);
    }

    public function test_completion_on_finish_day_shows_single_checkmark(): void
    {
        $this->travelTo('2026-09-28 12:00:00');

        RoadmapItem::factory()->create([
            'title' => 'On time',
            'started_on' => '2026-09-14',
            'finish_on' => '2026-09-17',
            'completed_on' => '2026-09-17',
        ]);

        $html = Livewire::test(Roadmaps::class)->html();

        $this->assertStringNotContainsString('data-marker="finish"', $html);
        $this->assertSame(1, substr_count($html, 'data-marker="done"'));
    }

    public function test_item_completed_early_still_shows_in_month_of_its_scheduled_finish(): void
    {
        $this->travelTo('2026-10-20 12:00:00');

        RoadmapItem::factory()->create([
            'title' => 'Wrapped up early',
            'started_on' => '2026-09-20',
            'finish_on' => '2026-10-10',
            'completed_on' => '2026-09-28',
        ]);

        Livewire::withQueryParams(['month' => '2026-10'])
            ->test(Roadmaps::class)
            ->assertSee('Wrapped up early');
    }
}
