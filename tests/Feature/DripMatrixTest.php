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
            ->set('todayOnly', false)
            ->assertSee('Delegate inbox')
            ->assertSee('Replace sales calls')
            ->assertSee('Invest in mastermind')
            ->assertSee('Produce vision')
            ->assertSee('No tag yet')
            ->assertSee('Untagged')
            ->assertDontSee('Other week');
    }

    public function test_week_board_links_to_drip_matrix_for_current_week(): void
    {
        $this->get(route('milestones', ['week' => '2026-W12']))
            ->assertOk()
            ->assertSee('DRIP Matrix')
            ->assertSee(route('milestones.drip-matrix', ['week' => '2026-W12'], absolute: false), false);
    }

    public function test_status_filter_limits_to_pending_or_completed(): void
    {
        $monday = Carbon::now()->setISODate(2026, 12)->startOfWeek(Carbon::MONDAY);

        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Open task',
            'drip_tag' => 'D',
            'is_completed' => false,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => $monday->toDateString(),
            'title' => 'Done task',
            'drip_tag' => 'D',
            'is_completed' => true,
            'position' => 2,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(DripMatrix::class)
            ->set('todayOnly', false)
            ->assertSee('Open task')
            ->assertSee('Done task')
            ->set('status', 'pending')
            ->assertSee('Open task')
            ->assertDontSee('Done task')
            ->set('status', 'completed')
            ->assertSee('Done task')
            ->assertDontSee('Open task');
    }

    public function test_today_only_filter_limits_to_todays_tasks(): void
    {
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

        Livewire::withQueryParams(['week' => $week])
            ->test(DripMatrix::class)
            ->assertSet('todayOnly', true)
            ->assertSee('Today drip')
            ->assertDontSee('Yesterday drip')
            ->set('todayOnly', false)
            ->assertSee('Today drip')
            ->assertSee('Yesterday drip');
    }
}
