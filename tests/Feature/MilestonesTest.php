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

    public function test_typing_new_title_does_not_create_item_until_submit_or_blur(): void
    {
        $date = now()->toDateString();

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startAdding')
            ->set('newTitle', 'Still typing')
            ->assertSet('adding', true)
            ->assertSet('newTitle', 'Still typing');

        $this->assertDatabaseMissing('todo_items', [
            'title' => 'Still typing',
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startAdding')
            ->set('newTitle', 'Commit on blur')
            ->call('finishAdding')
            ->assertSet('adding', false)
            ->assertSet('newTitle', '');

        $this->assertDatabaseHas('todo_items', [
            'title' => 'Commit on blur',
            'is_completed' => false,
        ]);
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

    public function test_can_set_and_clear_todo_item_links(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Read docs',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startLinking', $item->id)
            ->set('editLinks', [
                ['title' => 'Docs', 'url' => 'example.com/docs'],
            ])
            ->call('finishLinking')
            ->assertSet('linkingId', null);

        $item->refresh()->load('links');
        $this->assertCount(1, $item->links);
        $this->assertSame('https://example.com/docs', $item->links->first()->url);
        $this->assertSame('Docs', $item->links->first()->title);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->assertSee('Read docs')
            ->assertSee('Docs')
            ->assertSeeHtml('href="https://example.com/docs"')
            ->assertDontSeeHtml('>Read docs</a>')
            ->call('startLinking', $item->id)
            ->set('editLinks', [
                ['title' => '', 'url' => ''],
            ])
            ->call('finishLinking');

        $this->assertCount(0, $item->fresh()->links);
    }

    public function test_can_add_multiple_links_to_todo_item(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Ship feature',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startLinking', $item->id)
            ->call('addLinkRow')
            ->set('editLinks', [
                ['title' => 'PR', 'url' => 'https://github.com/example/pr/1'],
                ['title' => 'Spec', 'url' => 'https://docs.example.com/spec'],
            ])
            ->call('finishLinking')
            ->assertSee('PR')
            ->assertSee('Spec')
            ->assertSeeHtml('href="https://github.com/example/pr/1"')
            ->assertSeeHtml('href="https://docs.example.com/spec"');

        $this->assertCount(2, $item->fresh()->links);
    }

    public function test_link_requires_both_title_and_url(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Partial link',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startLinking', $item->id)
            ->set('editLinks', [
                ['title' => 'Docs', 'url' => ''],
            ])
            ->call('finishLinking')
            ->assertSet('linkingId', $item->id);

        $this->assertCount(0, $item->fresh()->links);
    }

    public function test_invalid_todo_item_url_is_rejected(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Broken link',
            'position' => 1,
        ]);

        $item->links()->create([
            'title' => 'Example',
            'url' => 'https://example.com',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startLinking', $item->id)
            ->set('editLinks', [
                ['title' => 'Docs', 'url' => 'not a url'],
            ])
            ->call('finishLinking')
            ->assertSet('linkingId', $item->id);

        $this->assertSame('https://example.com', $item->fresh()->links->first()->url);
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

    public function test_can_add_divider(): void
    {
        $date = now()->toDateString();

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->assertSeeHtml('wire:click="addDivider"')
            ->assertSee('---')
            ->call('addDivider');

        $this->assertDatabaseHas('todo_items', [
            'is_separator' => true,
            'title' => null,
            'is_completed' => false,
        ]);

        $this->assertTrue(
            TodoItem::query()
                ->whereDate('date', $date)
                ->where('is_separator', true)
                ->exists()
        );
    }

    public function test_cannot_toggle_divider(): void
    {
        $date = now()->toDateString();

        $divider = TodoItem::query()->create([
            'date' => $date,
            'title' => null,
            'is_separator' => true,
            'is_completed' => false,
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('toggle', $divider->id);

        $this->assertFalse($divider->fresh()->is_completed);
    }

    public function test_can_reorder_including_divider(): void
    {
        $date = now()->toDateString();

        $a = TodoItem::query()->create([
            'date' => $date,
            'title' => 'First',
            'position' => 1,
        ]);
        $divider = TodoItem::query()->create([
            'date' => $date,
            'title' => null,
            'is_separator' => true,
            'position' => 2,
        ]);
        $b = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Second',
            'position' => 3,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('reorder', [$divider->id, $a->id, $b->id]);

        $this->assertSame(1, $divider->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(3, $b->fresh()->position);
    }

    public function test_cannot_nest_a_divider(): void
    {
        $date = now()->toDateString();

        $parent = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Parent',
            'position' => 1,
        ]);
        $divider = TodoItem::query()->create([
            'date' => $date,
            'title' => null,
            'is_separator' => true,
            'position' => 2,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])->call('nest', $divider->id, $parent->id);

        $this->assertNull($divider->fresh()->parent_id);
    }

    public function test_can_add_a_child_item_from_the_slot(): void
    {
        $date = now()->toDateString();

        $parent = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Parent',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('startAddingChild', $parent->id)
            ->set('newChildTitle', 'Nested task')
            ->call('addChild')
            ->assertSee('Nested task');

        $child = TodoItem::query()->where('title', 'Nested task')->first();

        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_can_nest_a_todo_under_another(): void
    {
        $date = now()->toDateString();

        $parent = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Parent',
            'position' => 1,
        ]);
        $child = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Child',
            'position' => 2,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('nest', $child->id, $parent->id)
            ->assertSee('Child')
            ->assertSeeHtml('data-parent-id="'.$parent->id.'"');

        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_cannot_nest_under_an_already_nested_item(): void
    {
        $date = now()->toDateString();

        $parent = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Parent',
            'position' => 1,
        ]);
        $child = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Child',
            'parent_id' => $parent->id,
            'position' => 1,
        ]);
        $other = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Other',
            'position' => 2,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])->call('nest', $other->id, $child->id);

        $this->assertNull($other->fresh()->parent_id);
    }

    public function test_week_board_accepts_iso_week_query(): void
    {
        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(WeekBoard::class)
            ->assertSet('week', '2026-W12')
            ->assertSee('Week: 16-22 Mar')
            ->assertDontSee('Week 12');
    }

    public function test_can_copy_unfinished_from_previous_day(): void
    {
        $today = now()->toDateString();
        $yesterday = Carbon::parse($today)->subDay()->toDateString();

        $carry = TodoItem::query()->create([
            'date' => $yesterday,
            'title' => 'Carry me',
            'is_completed' => false,
            'drip_tag' => 'D',
            'position' => 1,
        ]);
        $carry->links()->create([
            'title' => 'Example',
            'url' => 'https://example.com',
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
        TodoItem::query()->create([
            'date' => $yesterday,
            'title' => null,
            'is_separator' => true,
            'is_completed' => false,
            'position' => 4,
        ]);

        Livewire::test(DayList::class, [
            'date' => $today,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
        ])
            ->call('copyUnfinishedFromPreviousDay');

        $copied = TodoItem::query()
            ->whereDate('date', $today)
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
            TodoItem::query()->whereDate('date', $today)->where('title', 'Already here')->count()
        );
        $this->assertFalse(
            TodoItem::query()
                ->whereDate('date', $today)
                ->where('is_separator', true)
                ->exists()
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

    public function test_week_board_can_toggle_show_tags(): void
    {
        Livewire::test(WeekBoard::class)
            ->assertSet('showTags', false)
            ->assertSeeHtml('aria-label="Show task tags"')
            ->set('showTags', true)
            ->assertSet('showTags', true)
            ->assertSeeHtml('aria-label="Hide task tags"');
    }

    public function test_can_set_and_clear_drip_tag(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Tagged task',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
            'showTags' => true,
        ])
            ->call('setDripTag', $item->id, 'R')
            ->assertSee('R');

        $this->assertSame('R', $item->fresh()->drip_tag?->value);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
            'showTags' => true,
        ])
            ->call('setDripTag', $item->id, 'R');

        $this->assertNull($item->fresh()->drip_tag);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
            'showTags' => true,
        ])
            ->call('setDripTag', $item->id, 'P')
            ->call('setDripTag', $item->id, null);

        $this->assertNull($item->fresh()->drip_tag);
    }

    public function test_set_drip_tag_rejects_invalid_letter(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Tagged task',
            'drip_tag' => 'I',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
            'showTags' => true,
        ])
            ->call('setDripTag', $item->id, 'X');

        $this->assertSame('I', $item->fresh()->drip_tag?->value);
    }

    public function test_money_and_energy_axes_sync_with_drip_tag(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Matrix task',
            'position' => 1,
        ]);

        $component = Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
            'showTags' => true,
        ]);

        $component->call('setDripTag', $item->id, 'D');
        $this->assertSame('D', $item->fresh()->drip_tag?->value);

        $component->call('setDripMoney', $item->id, 'high');
        $this->assertSame('R', $item->fresh()->drip_tag?->value);

        $component->call('setDripEnergy', $item->id, 'lights');
        $this->assertSame('P', $item->fresh()->drip_tag?->value);

        $component->call('setDripMoney', $item->id, 'low');
        $this->assertSame('I', $item->fresh()->drip_tag?->value);

        $component->call('setDripEnergy', $item->id, 'lights');
        $this->assertNull($item->fresh()->drip_tag);
    }

    public function test_setting_money_without_drip_defaults_energy_to_drains(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Matrix task',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'MONDAY',
            'isToday' => true,
            'showTags' => true,
        ])
            ->call('setDripMoney', $item->id, 'high');

        $this->assertSame('R', $item->fresh()->drip_tag?->value);
    }
}
