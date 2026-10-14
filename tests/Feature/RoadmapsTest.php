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
            ->assertSee('Roadmaps')
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

    public function test_nav_shows_roadmaps_after_backlog(): void
    {
        $this->get(route('milestones'))
            ->assertSeeInOrder(['Backlog', 'Roadmaps', 'Links']);
    }

    public function test_can_add_pending_range_from_selected_day(): void
    {
        $this->travelTo('2026-09-18 12:00:00');

        Livewire::test(Roadmaps::class)
            ->call('selectDay', '2026-09-03')
            ->set('newTitle', 'Auth rewrite')
            ->call('addItem')
            ->assertSee('Auth rewrite')
            ->assertSee('pending')
            ->assertSee('Add from 3 Sep');

        $item = RoadmapItem::query()->where('title', 'Auth rewrite')->first();

        $this->assertNotNull($item);
        $this->assertSame('2026-09-03', $item->started_on->toDateString());
        $this->assertNull($item->completed_on);
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
}
