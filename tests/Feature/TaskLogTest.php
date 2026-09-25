<?php

namespace Tests\Feature;

use App\Livewire\DayList;
use App\Livewire\TaskLogBoard;
use App\Livewire\WeekBoard;
use App\Models\TaskLog;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_page_renders(): void
    {
        $this->get(route('milestones.log', ['week' => '2026-W12']))
            ->assertOk()
            ->assertSee('Log')
            ->assertSeeLivewire(TaskLogBoard::class);
    }

    public function test_week_board_links_to_log_page_and_toggles_log_controls(): void
    {
        Livewire::test(WeekBoard::class)
            ->assertSet('showLogControls', false)
            ->assertSee('Log')
            ->assertSee(route('milestones.log', absolute: false), false)
            ->assertSeeHtml('aria-label="Show log controls"')
            ->set('showLogControls', true)
            ->assertSet('showLogControls', true)
            ->assertSeeHtml('aria-label="Hide log controls"');
    }

    public function test_start_logging_prefixes_emoji_and_stores_started_at(): void
    {
        Carbon::setTestNow('2026-03-18 10:15:00');

        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Ship feature',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
            'showLogControls' => true,
        ])
            ->call('startLogging', $item->id)
            ->assertSeeHtml('aria-label="Logging since 12:15"')
            ->assertSee('12:15')
            ->assertSee('Ship feature');

        $item->refresh();

        $this->assertNotNull($item->logging_started_at);
        $this->assertTrue($item->logging_started_at->equalTo(now()));
    }

    public function test_finish_logging_clears_emoji_and_creates_task_log(): void
    {
        Carbon::setTestNow('2026-03-18 10:15:00');

        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Ship feature',
            'logging_started_at' => now(),
            'position' => 1,
        ]);

        Carbon::setTestNow('2026-03-18 10:40:00');

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
            'showLogControls' => true,
        ])
            ->call('finishLogging', $item->id)
            ->assertDontSeeHtml('aria-label="Logging since');

        $item->refresh();

        $this->assertNull($item->logging_started_at);

        $log = TaskLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->todo_item_id);
        $this->assertSame('Ship feature', $log->title);
        $this->assertTrue($log->started_at->equalTo(Carbon::parse('2026-03-18 10:15:00')));
        $this->assertTrue($log->finished_at->equalTo(Carbon::parse('2026-03-18 10:40:00')));
    }

    public function test_finish_logging_without_start_is_a_noop(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Not started',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
            'showLogControls' => true,
        ])
            ->call('finishLogging', $item->id);

        $this->assertDatabaseCount('task_logs', 0);
        $this->assertNull($item->fresh()->logging_started_at);
    }

    public function test_completing_an_active_log_item_also_finishes_logging(): void
    {
        Carbon::setTestNow('2026-03-18 10:15:00');

        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Ship feature',
            'logging_started_at' => now(),
            'is_completed' => false,
            'position' => 1,
        ]);

        Carbon::setTestNow('2026-03-18 10:40:00');

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
            'showLogControls' => true,
        ])
            ->call('toggle', $item->id)
            ->assertDontSeeHtml('aria-label="Logging since');

        $item->refresh();

        $this->assertTrue($item->is_completed);
        $this->assertNull($item->logging_started_at);

        $log = TaskLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->todo_item_id);
        $this->assertTrue($log->started_at->equalTo(Carbon::parse('2026-03-18 10:15:00')));
        $this->assertTrue($log->finished_at->equalTo(Carbon::parse('2026-03-18 10:40:00')));
    }

    public function test_uncompleting_does_not_create_a_task_log(): void
    {
        $date = now()->toDateString();

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Already done',
            'is_completed' => true,
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
        ])
            ->call('toggle', $item->id);

        $this->assertFalse($item->fresh()->is_completed);
        $this->assertDatabaseCount('task_logs', 0);
    }

    public function test_log_page_lists_finished_entries_for_week(): void
    {
        $inWeek = TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'In week task',
            'started_at' => Carbon::parse('2026-03-18 09:00:00'),
            'finished_at' => Carbon::parse('2026-03-18 10:00:00'),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Other week task',
            'started_at' => Carbon::parse('2026-03-10 09:00:00'),
            'finished_at' => Carbon::parse('2026-03-10 10:00:00'),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSee('In week task')
            ->assertSee('WED')
            ->assertSee('11:00')
            ->assertSee('12:00')
            ->assertSee('1h')
            ->assertDontSee('Other week task');

        $this->assertSame($inWeek->title, 'In week task');
    }

    public function test_log_page_groups_entries_into_day_columns(): void
    {
        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Monday deep work',
            'started_at' => Carbon::parse('2026-03-16 08:00:00'),
            'finished_at' => Carbon::parse('2026-03-16 10:00:00'),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Friday wrap',
            'started_at' => Carbon::parse('2026-03-20 14:00:00'),
            'finished_at' => Carbon::parse('2026-03-20 14:45:00'),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSee('MON')
            ->assertSee('FRI')
            ->assertSee('Monday deep work')
            ->assertSee('Friday wrap')
            ->assertSee('2h')
            ->assertSee('45m')
            ->assertSeeHtml('role="img"')
            ->assertSeeHtml('Time split for MON')
            ->assertSeeHtml('Time split for FRI')
            ->assertSee('Total: 2h')
            ->assertSee('Total: 45m');
    }

    public function test_day_pie_chart_renders_slices_for_multiple_logs(): void
    {
        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Slice A',
            'started_at' => Carbon::parse('2026-03-16 08:00:00'),
            'finished_at' => Carbon::parse('2026-03-16 09:00:00'),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Slice B',
            'started_at' => Carbon::parse('2026-03-16 10:00:00'),
            'finished_at' => Carbon::parse('2026-03-16 12:00:00'),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSee('Slice A')
            ->assertSee('Slice B')
            ->assertSeeHtml('<path d="')
            ->assertSeeHtml('Time split for MON');
    }

    public function test_week_stats_group_sessions_by_title(): void
    {
        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Prognoze',
            'started_at' => Carbon::parse('2026-03-16 08:00:00'),
            'finished_at' => Carbon::parse('2026-03-16 09:00:00'),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Prognoze',
            'started_at' => Carbon::parse('2026-03-18 12:00:00'),
            'finished_at' => Carbon::parse('2026-03-18 13:30:00'),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Cosmic',
            'started_at' => Carbon::parse('2026-03-17 10:00:00'),
            'finished_at' => Carbon::parse('2026-03-17 10:30:00'),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSee('Week totals')
            ->assertSee('2h 30m')
            ->assertSee('3h')
            ->assertSee('Prognoze')
            ->assertSee('Cosmic')
            ->assertSee('MON 16 Mar')
            ->assertSee('WED 18 Mar')
            ->assertSee('TUE 17 Mar');
    }

    public function test_each_task_title_gets_a_unique_stable_color(): void
    {
        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Alpha task',
            'started_at' => Carbon::parse('2026-03-16 08:00:00'),
            'finished_at' => Carbon::parse('2026-03-16 09:00:00'),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Beta task',
            'started_at' => Carbon::parse('2026-03-16 10:00:00'),
            'finished_at' => Carbon::parse('2026-03-16 11:00:00'),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Alpha task',
            'started_at' => Carbon::parse('2026-03-18 12:00:00'),
            'finished_at' => Carbon::parse('2026-03-18 13:00:00'),
        ]);

        $html = Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->html();

        preg_match_all('/(?:background-color:\s*|fill=")(#[0-9a-f]{6})/', $html, $matches);

        $uniqueColors = array_values(array_unique($matches[1]));

        $this->assertContains('#58a6ff', $uniqueColors);
        $this->assertContains('#ff9800', $uniqueColors);
        $this->assertSame(['#58a6ff', '#ff9800'], $uniqueColors);
        $this->assertGreaterThanOrEqual(2, substr_count($html, '#58a6ff'));
    }

    public function test_start_logging_is_idempotent_when_already_logging(): void
    {
        Carbon::setTestNow('2026-03-18 10:15:00');

        $date = now()->toDateString();
        $startedAt = now()->subMinutes(5);

        $item = TodoItem::query()->create([
            'date' => $date,
            'title' => 'Already logging',
            'logging_started_at' => $startedAt,
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
            'showLogControls' => true,
        ])
            ->call('startLogging', $item->id);

        $this->assertTrue($item->fresh()->logging_started_at->equalTo($startedAt));
    }
}
