<?php

namespace Tests\Feature;

use App\Livewire\Backlog;
use App\Models\BacklogItem;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BacklogTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_milestones(): void
    {
        $this->get('/')
            ->assertRedirect('/milestones');
    }

    public function test_backlog_page_renders(): void
    {
        BacklogItem::query()->create([
            'title' => 'Ship backlog',
            'position' => 1,
        ]);

        $this->get(route('backlog'))
            ->assertOk()
            ->assertSee('Backlog')
            ->assertSee('Ship backlog')
            ->assertSeeLivewire(Backlog::class)
            ->assertDontSee('>Home<', false);
    }

    public function test_nav_shows_backlog_not_home(): void
    {
        $this->get(route('milestones'))
            ->assertOk()
            ->assertSee('Backlog')
            ->assertDontSee('>Home</a>', false);
    }

    public function test_can_toggle_backlog_item(): void
    {
        $item = BacklogItem::query()->create([
            'title' => 'Do the thing',
            'is_completed' => false,
            'position' => 1,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-14 09:30:00', 'Europe/Bucharest'));

        Livewire::test(Backlog::class)
            ->call('toggle', $item->id)
            ->assertDontSee('Do the thing')
            ->set('display', 'finished')
            ->assertSee('Wed 14 Oct')
            ->assertSee('Do the thing');

        $item->refresh();

        $this->assertTrue($item->is_completed);
        $this->assertTrue($item->completed_at->equalTo(now()));

        Livewire::test(Backlog::class)
            ->call('toggle', $item->id)
            ->assertDontSee('Wed 14 Oct');

        $item->refresh();

        $this->assertFalse($item->is_completed);
        $this->assertNull($item->completed_at);
    }

    public function test_display_filter_hides_finished_tasks_until_selected(): void
    {
        BacklogItem::query()->create([
            'title' => 'Still open',
            'is_completed' => false,
            'position' => 1,
        ]);
        BacklogItem::query()->create([
            'title' => 'Already done',
            'is_completed' => true,
            'position' => 2,
        ]);

        Livewire::test(Backlog::class)
            ->assertSet('display', 'pending')
            ->assertSee('Still open')
            ->assertDontSee('Already done')
            ->set('display', 'finished')
            ->assertDontSee('Still open')
            ->assertSee('Already done')
            ->set('display', 'all')
            ->assertSee('Still open')
            ->assertSee('Already done');
    }

    public function test_can_add_and_delete_backlog_item(): void
    {
        Livewire::test(Backlog::class)
            ->assertSeeHtml('aria-label="Add backlog item"')
            ->assertSeeHtml('aria-label="Add divider"')
            ->call('startAdding')
            ->set('newTitle', 'New backlog task')
            ->call('addItem');

        $item = BacklogItem::query()->where('title', 'New backlog task')->first();
        $this->assertNotNull($item);

        Livewire::test(Backlog::class)
            ->call('deleteItem', $item->id);

        $this->assertDatabaseMissing('backlog_items', ['id' => $item->id]);
    }

    public function test_separator_cannot_be_toggled(): void
    {
        $item = BacklogItem::query()->create([
            'title' => null,
            'is_separator' => true,
            'is_completed' => false,
            'position' => 1,
        ]);

        Livewire::test(Backlog::class)
            ->call('toggle', $item->id);

        $this->assertFalse($item->fresh()->is_completed);
    }

    public function test_can_add_divider(): void
    {
        Livewire::test(Backlog::class)
            ->call('addDivider');

        $this->assertDatabaseHas('backlog_items', [
            'is_separator' => true,
            'title' => null,
        ]);
    }

    public function test_can_reorder_backlog_items(): void
    {
        $a = BacklogItem::query()->create(['title' => 'First', 'position' => 1]);
        $b = BacklogItem::query()->create(['title' => 'Second', 'position' => 2]);
        $c = BacklogItem::query()->create([
            'title' => null,
            'is_separator' => true,
            'position' => 3,
        ]);

        Livewire::test(Backlog::class)
            ->call('reorder', [$c->id, $a->id, $b->id]);

        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(3, $b->fresh()->position);
    }

    public function test_can_move_item_to_this_week(): void
    {
        $item = BacklogItem::query()->create([
            'title' => 'Ship cashflow queues',
            'position' => 1,
        ]);

        Livewire::test(Backlog::class)
            ->call('moveToThisWeek', $item->id);

        $this->assertDatabaseMissing('backlog_items', ['id' => $item->id]);
        $this->assertTrue(
            TodoItem::query()
                ->whereNull('date')
                ->where('week', now()->format('o-\WW'))
                ->where('title', 'Ship cashflow queues')
                ->where('is_completed', false)
                ->exists()
        );
    }

    public function test_cannot_move_separator_to_this_week(): void
    {
        $item = BacklogItem::query()->create([
            'title' => null,
            'is_separator' => true,
            'position' => 1,
        ]);

        Livewire::test(Backlog::class)
            ->call('moveToThisWeek', $item->id);

        $this->assertDatabaseHas('backlog_items', ['id' => $item->id]);
        $this->assertDatabaseCount('todo_items', 0);
    }
}
