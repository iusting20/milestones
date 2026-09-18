<?php

namespace Tests\Feature;

use App\Livewire\DayList;
use App\Livewire\WeekBoard;
use App\Models\TodoItem;
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
}
