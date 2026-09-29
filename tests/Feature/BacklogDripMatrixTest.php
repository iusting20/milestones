<?php

namespace Tests\Feature;

use App\Livewire\Backlog;
use App\Livewire\BacklogDripMatrix;
use App\Models\BacklogItem;
use App\Models\TodoItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BacklogDripMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_backlog_drip_matrix_page_renders(): void
    {
        $this->get(route('backlog.drip-matrix'))
            ->assertOk()
            ->assertSee('Backlog DRIP Matrix')
            ->assertSeeLivewire(BacklogDripMatrix::class);
    }

    public function test_backlog_links_to_its_own_drip_matrix(): void
    {
        $this->get(route('backlog'))
            ->assertOk()
            ->assertSee('DRIP Matrix')
            ->assertSee(route('backlog.drip-matrix', absolute: false), false);
    }

    public function test_backlog_matrix_groups_tagged_items_separately_from_milestones(): void
    {
        BacklogItem::query()->create([
            'title' => 'Backlog delegate',
            'drip_tag' => 'D',
            'position' => 1,
        ]);
        BacklogItem::query()->create([
            'title' => 'Backlog untagged',
            'drip_tag' => null,
            'position' => 2,
        ]);
        TodoItem::query()->create([
            'date' => now()->toDateString(),
            'title' => 'Milestone only',
            'drip_tag' => 'P',
            'position' => 1,
        ]);

        Livewire::test(BacklogDripMatrix::class)
            ->assertSee('Backlog delegate')
            ->assertSee('Backlog untagged')
            ->assertSee('Untagged')
            ->assertDontSee('Milestone only');
    }

    public function test_can_set_backlog_drip_tag_without_touching_todo_items(): void
    {
        $item = BacklogItem::query()->create([
            'title' => 'Tag me',
            'position' => 1,
        ]);

        Livewire::test(Backlog::class)
            ->set('showTags', true)
            ->call('setDripTag', $item->id, 'R');

        $this->assertSame('R', $item->fresh()->drip_tag?->value);
        $this->assertDatabaseCount('todo_items', 0);
    }

    public function test_separator_cannot_receive_drip_tag(): void
    {
        $item = BacklogItem::query()->create([
            'title' => null,
            'is_separator' => true,
            'position' => 1,
        ]);

        Livewire::test(Backlog::class)
            ->call('setDripTag', $item->id, 'D');

        $this->assertNull($item->fresh()->drip_tag);
    }

    public function test_move_to_this_week_does_not_copy_backlog_drip_tag(): void
    {
        $item = BacklogItem::query()->create([
            'title' => 'Ship queues',
            'drip_tag' => 'I',
            'position' => 1,
        ]);

        Livewire::test(Backlog::class)
            ->call('moveToThisWeek', $item->id);

        $todo = TodoItem::query()->where('title', 'Ship queues')->first();

        $this->assertNotNull($todo);
        $this->assertNull($todo->drip_tag);
        $this->assertNull($todo->date);
        $this->assertSame(now()->format('o-\WW'), $todo->week);
    }
}
