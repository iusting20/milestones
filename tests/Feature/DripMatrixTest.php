<?php

namespace Tests\Feature;

use App\Livewire\DripMatrix;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DripMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_drip_matrix_page_renders_for_week(): void
    {
        $this->get(route('milestones.drip-matrix', ['week' => '2026-W12']))
            ->assertOk()
            ->assertSee('DRIP Matrix')
            ->assertSeeLivewire(DripMatrix::class);
    }

    public function test_drip_matrix_groups_week_tasks_into_quadrants(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Delegate inbox',
            'drip_tag' => 'D',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->copy()->addDays(1)->toDateString(),
            'title' => 'Replace sales calls',
            'drip_tag' => 'R',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->copy()->addDays(2)->toDateString(),
            'title' => 'Invest in mastermind',
            'drip_tag' => 'I',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->copy()->addDays(3)->toDateString(),
            'title' => 'Produce vision',
            'drip_tag' => 'P',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'No tag yet',
            'drip_tag' => null,
            'position' => 2,
        ]);
        TodoItem::query()->create([
            'date' => $monday->copy()->subWeek()->toDateString(),
            'title' => 'Other week',
            'drip_tag' => 'P',
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->assertSee('Delegate inbox')
            ->assertSee('Replace sales calls')
            ->assertSee('Invest in mastermind')
            ->assertSee('Produce vision')
            ->assertSee('No tag yet')
            ->assertSee('Untagged')
            ->assertDontSee('Other week');
    }

    public function test_parent_tasks_are_hidden_from_the_matrix(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        $parent = TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Hidden parent',
            'drip_tag' => 'D',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Visible child',
            'parent_id' => $parent->id,
            'drip_tag' => 'P',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Standalone task',
            'drip_tag' => 'R',
            'position' => 2,
        ]);

        $component = Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->assertSee('Visible child')
            ->assertSee('Standalone task')
            ->assertSeeHtml('data-tooltip="from: Hidden parent"');

        $html = $component->html();

        $this->assertSame(2, substr_count($html, 'data-tooltip="from: Hidden parent"'));
        $this->assertSame(2, substr_count($html, 'Hidden parent'));
    }

    public function test_week_board_links_to_drip_matrix_for_current_week(): void
    {
        $this->get(route('milestones', ['week' => '2026-W12']))
            ->assertOk()
            ->assertSee('DRIP Matrix')
            ->assertSee(route('milestones.drip-matrix', ['week' => '2026-W12'], absolute: false), false);
    }

    public function test_today_only_filter_limits_to_todays_tasks(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 12:00:00')); // Wednesday, W39

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $week = now()->format('o-\WW');

        TodoItem::query()->create([
            'date' => $today,
            'title' => 'Today drip',
            'drip_tag' => 'P',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $yesterday,
            'title' => 'Yesterday drip',
            'drip_tag' => 'P',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => '2026-10-01',
            'title' => 'Next month drip',
            'drip_tag' => 'P',
            'position' => 1,
        ]);

        $component = Livewire::withQueryParams(['week' => $week])
            ->test(DripMatrix::class)
            ->assertSet('period', 'today')
            ->assertSee('Today drip')
            ->assertDontSee('Yesterday drip');

        $this->assertDoesNotMatchRegularExpression(
            '/tracking-wide text-muted">\s*Wed\s*</',
            $component->html(),
        );

        $component->set('period', 'week')
            ->assertSee('Today drip')
            ->assertSee('Yesterday drip');

        $this->assertMatchesRegularExpression(
            '/tracking-wide text-muted">\s*Wed\s*</',
            $component->html(),
        );
        $this->assertMatchesRegularExpression(
            '/tracking-wide text-muted">\s*Tue\s*</',
            $component->html(),
        );

        $component->set('period', 'month')
            ->assertSee('Today drip')
            ->assertSee('Yesterday drip')
            ->assertDontSee('Next month drip');
    }

    public function test_completed_tasks_are_prefixed_with_checkmark(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Finished drip',
            'drip_tag' => 'D',
            'is_completed' => true,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Open drip',
            'drip_tag' => 'D',
            'is_completed' => false,
            'position' => 2,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->assertSee('✔')
            ->assertSee('○')
            ->assertSee('Finished drip')
            ->assertSee('Open drip');
    }

    public function test_dividers_are_hidden_from_the_matrix(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Visible task',
            'drip_tag' => null,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => null,
            'is_separator' => true,
            'position' => 2,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->assertSee('Visible task')
            ->assertSee('Untagged WIP (1)');
    }

    public function test_untagged_items_expose_money_and_energy_chip_pickers(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Needs a tag',
            'drip_tag' => null,
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->assertSee('Needs a tag')
            ->assertDontSeeHtml('aria-label="Set drip tag"')
            ->assertSeeHtml('aria-label="Set money axis"')
            ->assertSeeHtml('aria-label="Set energy axis"');
    }

    public function test_untagged_items_split_into_wip_and_done_columns(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Open untagged',
            'drip_tag' => null,
            'is_completed' => false,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Finished untagged',
            'drip_tag' => null,
            'is_completed' => true,
            'position' => 2,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->assertSee('Untagged WIP (1)')
            ->assertSee('Untagged done (1)')
            ->assertSee('Open untagged')
            ->assertSee('Finished untagged');
    }

    public function test_can_tag_untagged_item_from_matrix(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        $item = TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Tag from matrix',
            'drip_tag' => null,
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->call('setDripMoney', $item->id, 'high')
            ->assertSee('Untagged WIP (1)')
            ->assertSee('Tag from matrix');

        $partial = $item->fresh();
        $this->assertNull($partial->drip_tag);
        $this->assertSame('high', $partial->drip_money?->value);
        $this->assertNull($partial->drip_energy);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->call('setDripEnergy', $item->id, 'lights')
            ->assertDontSee('Untagged WIP')
            ->assertDontSee('Untagged done')
            ->assertSee('Tag from matrix');

        $this->assertSame('P', $item->fresh()->drip_tag?->value);
    }

    public function test_single_energy_axis_keeps_item_in_untagged(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        $item = TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Only energy',
            'drip_tag' => null,
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('period', 'week')
            ->call('setDripEnergy', $item->id, 'lights')
            ->assertSee('Untagged WIP (1)')
            ->assertSee('Only energy');

        $fresh = $item->fresh();
        $this->assertNull($fresh->drip_tag);
        $this->assertNull($fresh->drip_money);
        $this->assertSame('lights', $fresh->drip_energy?->value);
    }
}
