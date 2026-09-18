<?php

namespace Tests\Feature;

use App\Livewire\DayList;
use App\Livewire\WeekBoard;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MilestonesTest extends TestCase
{
    use RefreshDatabase;

    public function test_milestones_page_renders_week_board(): void
    {
        $this->get(route('milestones'))
            ->assertOk()
            ->assertSee('Productivity Log')
            ->assertSeeLivewire(WeekBoard::class);
    }

    public function test_can_add_and_complete_todo_item(): void
    {
        $date = now()->toDateString();

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->set('newTitle', 'Ship milestones')
            ->call('addItem')
            ->assertSet('newTitle', '');

        $this->assertDatabaseHas('todo_items', [
            'title' => 'Ship milestones',
            'is_completed' => false,
        ]);

        $item = TodoItem::query()->first();

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('toggle', $item->id);

        $this->assertTrue($item->fresh()->is_completed);
    }

    public function test_can_edit_todo_item_title(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Old title',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startEditing', $item->id)
            ->set('editTitle', 'New title')
            ->call('finishEditing');

        $this->assertSame('New title', $item->fresh()->title);
    }

    public function test_can_reorder_todo_items(): void
    {
        $date = now()->toDateString();

        $a = TodoItem::query()->create([
            'date' => $date,
            'title' => 'First',
            'position' => 1,
        ]);
        $b = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Second',
            'position' => 2,
        ]);
        $c = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Third',
            'position' => 3,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('reorder', [$c->id, $a->id, $b->id]);

        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(3, $b->fresh()->position);
    }

    public function test_week_board_accepts_iso_week_query(): void
    {
        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(WeekBoard::class)
            ->assertSet('week', '2026-W12')
            ->assertSee('Week 12');
    }

    public function test_can_copy_unfinished_from_previous_day(): void
    {
        $today = now()->toDateString();
        $yesterday = Carbon::parse($today)->subDay()->toDateString();

        TodoItem::query()->create([
            'date' => $yesterday,
            'title' => 'Carry me',
            'url' => 'https://example.com',
            'is_completed' => false,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $yesterday,
            'title' => 'Already done',
            'is_completed' => true,
            'position' => 2,
        ]);
        TodoItem::query()->create([
            'date' => $today,
            'title' => 'Already here',
            'is_completed' => false,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $yesterday,
            'title' => 'Already here',
            'is_completed' => false,
            'position' => 3,
        ]);

        Livewire::test(DayList::class, [
            'date' => $today,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('copyUnfinishedFromPreviousDay');

        $this->assertTrue(
            TodoItem::query()
                ->whereDate('date', $today)
                ->where('title', 'Carry me')
                ->where('url', 'https://example.com')
                ->where('is_completed', false)
                ->exists()
        );
        $this->assertSame(
            1,
            TodoItem::query()->whereDate('date', $today)->where('title', 'Already here')->count()
        );
        $this->assertFalse(
            TodoItem::query()
                ->whereDate('date', $today)
                ->where('title', 'Already done')
                ->exists()
        );
        $this->assertTrue(
            TodoItem::query()
                ->whereDate('date', $yesterday)
                ->where('title', 'Carry me')
                ->exists()
        );
    }
}
