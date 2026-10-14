<?php

namespace Tests\Feature;

use App\Livewire\DayList;
use App\Livewire\WeekBoard;
use App\Livewire\WeekList;
use App\Models\BacklogItem;
use App\Models\TodoItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WeekListTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestones_page_renders_for_this_week_panel(): void
    {
        $this->get(route('milestones'))
            ->assertOk()
            ->assertSee('For this week')
            ->assertSeeLivewire(WeekList::class);
    }

    public function test_can_add_week_scoped_item(): void
    {
        $week = now()->format('o-\WW');

        Livewire::test(WeekList::class, ['week' => $week])
            ->set('newTitle', 'Plan the week')
            ->call('addItem')
            ->assertSet('newTitle', '');

        $this->assertDatabaseHas('todo_items', [
            'title' => 'Plan the week',
            'week' => $week,
            'date' => null,
            'is_completed' => false,
        ]);
    }

    public function test_arrow_move_assigns_item_to_day_incomplete(): void
    {
        $week = '2026-W39';
        $monday = '2026-09-21';

        $item = TodoItem::query()->create([
            'date' => null,
            'week' => $week,
            'title' => 'Ship feature',
            'is_completed' => false,
            'position' => 1,
        ]);

        Livewire::test(WeekList::class, ['week' => $week])
            ->call('openMoveModal', $item->id, false)
            ->assertSet('showMoveModal', true)
            ->assertSet('moveCompleted', false)
            ->call('moveToDay', $monday)
            ->assertSet('showMoveModal', false)
            ->assertDispatched('todo-assigned-to-day');

        $item->refresh();

        $this->assertSame($monday, $item->date?->toDateString());
        $this->assertNull($item->week);
        $this->assertFalse($item->is_completed);
        $this->assertSame(
            0,
            TodoItem::query()->whereNull('date')->where('week', $week)->count()
        );
    }

    public function test_checkmark_move_assigns_item_to_day_completed(): void
    {
        $week = '2026-W39';
        $tuesday = '2026-09-22';

        $item = TodoItem::query()->create([
            'date' => null,
            'week' => $week,
            'title' => 'Done task',
            'is_completed' => false,
            'position' => 1,
        ]);

        Livewire::test(WeekList::class, ['week' => $week])
            ->call('openMoveModal', $item->id, true)
            ->assertSet('moveCompleted', true)
            ->call('moveToDay', $tuesday);

        $item->refresh();

        $this->assertSame($tuesday, $item->date?->toDateString());
        $this->assertNull($item->week);
        $this->assertTrue($item->is_completed);
    }

    public function test_children_follow_parent_when_moved_to_day(): void
    {
        $week = '2026-W39';
        $wednesday = '2026-09-23';

        $parent = TodoItem::query()->create([
            'date' => null,
            'week' => $week,
            'title' => 'Parent',
            'position' => 1,
        ]);
        $child = TodoItem::query()->create([
            'date' => null,
            'week' => $week,
            'parent_id' => $parent->id,
            'title' => 'Child',
            'position' => 1,
        ]);

        Livewire::test(WeekList::class, ['week' => $week])
            ->call('openMoveModal', $parent->id, false)
            ->call('moveToDay', $wednesday);

        $parent->refresh();
        $child->refresh();

        $this->assertSame($wednesday, $parent->date?->toDateString());
        $this->assertSame($wednesday, $child->date?->toDateString());
        $this->assertNull($parent->week);
        $this->assertNull($child->week);
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_day_list_ignores_week_bucketed_items(): void
    {
        $week = now()->format('o-\WW');
        $today = now()->toDateString();

        TodoItem::query()->create([
            'date' => null,
            'week' => $week,
            'title' => 'Week only',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $today,
            'title' => 'Day item',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $today,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->assertSee('Day item')
            ->assertDontSee('Week only');
    }

    public function test_week_board_includes_week_list(): void
    {
        Livewire::test(WeekBoard::class)
            ->assertSee('For this week')
            ->assertSeeLivewire(WeekList::class);
    }

    public function test_copy_unfinished_from_last_week_copies_only_that_weeks_open_items(): void
    {
        $item = TodoItem::query()->create([
            'date' => null,
            'week' => '2026-W38',
            'title' => 'Carry me',
            'is_completed' => false,
            'drip_tag' => 'D',
            'position' => 1,
        ]);
        $item->links()->create([
            'title' => 'Example',
            'url' => 'https://example.com',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => null,
            'week' => '2026-W38',
            'title' => 'Already done',
            'is_completed' => true,
            'position' => 2,
        ]);
        TodoItem::query()->create([
            'date' => null,
            'week' => '2026-W39',
            'title' => 'Already here',
            'is_completed' => false,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => null,
            'week' => '2026-W38',
            'title' => 'Already here',
            'is_completed' => false,
            'position' => 3,
        ]);
        TodoItem::query()->create([
            'date' => null,
            'week' => '2026-W38',
            'title' => null,
            'is_separator' => true,
            'is_completed' => false,
            'position' => 4,
        ]);
        TodoItem::query()->create([
            'date' => '2026-09-14',
            'week' => null,
            'title' => 'Day task',
            'is_completed' => false,
            'position' => 1,
        ]);

        Livewire::test(WeekList::class, ['week' => '2026-W39'])
            ->assertSee('copy unfinished milestones from last week')
            ->call('copyUnfinishedFromPreviousWeek');

        $copied = TodoItem::query()
            ->whereNull('date')
            ->where('week', '2026-W39')
            ->where('title', 'Carry me')
            ->where('is_completed', false)
            ->where('drip_tag', 'D')
            ->first();

        $this->assertNotNull($copied);
        $this->assertTrue(
            $copied->links()
                ->where('url', 'https://example.com')
                ->where('title', 'Example')
                ->exists()
        );
        $this->assertSame(
            1,
            TodoItem::query()->whereNull('date')->where('week', '2026-W39')->where('title', 'Already here')->count()
        );
        $this->assertFalse(
            TodoItem::query()
                ->whereNull('date')
                ->where('week', '2026-W39')
                ->where('is_separator', true)
                ->exists()
        );
        $this->assertFalse(
            TodoItem::query()
                ->whereNull('date')
                ->where('week', '2026-W39')
                ->where('title', 'Already done')
                ->exists()
        );
        $this->assertFalse(
            TodoItem::query()
                ->whereNull('date')
                ->where('week', '2026-W39')
                ->where('title', 'Day task')
                ->exists()
        );
        $this->assertTrue(
            TodoItem::query()
                ->whereNull('date')
                ->where('week', '2026-W38')
                ->where('title', 'Carry me')
                ->exists()
        );
    }

    public function test_can_move_week_item_to_backlog(): void
    {
        BacklogItem::query()->create([
            'title' => 'Already parked',
            'position' => 1,
        ]);

        $item = TodoItem::query()->create([
            'date' => null,
            'week' => '2026-W39',
            'title' => 'Park this',
            'drip_tag' => 'D',
            'position' => 1,
        ]);

        Livewire::test(WeekList::class, ['week' => '2026-W39'])
            ->assertSeeHtml('title="Move to backlog"')
            ->call('moveToBacklog', $item->id);

        $this->assertDatabaseMissing('todo_items', ['id' => $item->id]);

        $backlog = BacklogItem::query()->where('title', 'Park this')->first();

        $this->assertNotNull($backlog);
        $this->assertNull($backlog->drip_tag);
        $this->assertSame(1, $backlog->position);
        $this->assertSame(2, BacklogItem::query()->where('title', 'Already parked')->value('position'));
    }

    public function test_rejects_move_to_date_outside_week(): void
    {
        $week = '2026-W39';

        $item = TodoItem::query()->create([
            'date' => null,
            'week' => $week,
            'title' => 'Stay put',
            'position' => 1,
        ]);

        Livewire::test(WeekList::class, ['week' => $week])
            ->call('openMoveModal', $item->id, false)
            ->call('moveToDay', '2026-09-28');

        $item->refresh();

        $this->assertNull($item->date);
        $this->assertSame($week, $item->week);
    }
}
