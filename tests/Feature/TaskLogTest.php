<?php

namespace Tests\Feature;

use App\Livewire\DayList;
use App\Livewire\TaskLogBoard;
use App\Livewire\WeekBoard;
use App\Models\TaskLog;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            ->assertSeeHtml('aria-label="Edit logging start 12:15"')
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

    public function test_finish_logging_on_another_day_is_stored_on_that_day(): void
    {
        Carbon::setTestNow('2026-03-18 10:15:00');

        $item = TodoItem::query()->create([
            'date' => '2026-03-16',
            'title' => 'Monday task',
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => '2026-03-16',
            'dayLabel' => 'MONDAY',
            'isToday' => false,
            'showLogControls' => true,
        ])->call('startLogging', $item->id);

        Carbon::setTestNow('2026-03-18 10:40:00');

        Livewire::test(DayList::class, [
            'date' => '2026-03-16',
            'dayLabel' => 'MONDAY',
            'isToday' => false,
            'showLogControls' => true,
        ])->call('finishLogging', $item->id);

        $log = TaskLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame(
            '2026-03-16 12:15',
            $log->started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
        $this->assertSame(
            '2026-03-16 12:40',
            $log->finished_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSee('Monday task')
            ->assertSee('MON');
    }

    public function test_timer_left_running_past_task_day_keeps_start_and_caps_at_end_of_day(): void
    {
        Carbon::setTestNow('2026-10-30 11:30:00');

        $item = TodoItem::query()->create([
            'date' => '2026-10-30',
            'title' => 'Forgot to stop',
            'position' => 1,
        ]);

        $props = ['date' => '2026-10-30', 'dayLabel' => 'FRIDAY', 'isToday' => false, 'showLogControls' => true];

        Livewire::test(DayList::class, $props)->call('startLogging', $item->id);

        Carbon::setTestNow('2026-10-31 10:28:00');

        Livewire::test(DayList::class, $props)->call('finishLogging', $item->id);

        $log = TaskLog::query()->sole();

        $this->assertSame('2026-10-30 13:30', $log->started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-30 23:59', $log->finished_at->timezone('Europe/Bucharest')->format('Y-m-d H:i'));
    }

    public function test_overnight_session_started_evening_before_task_day_is_kept(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-29 23:00:00', 'Europe/Bucharest')->utc());

        $item = TodoItem::query()->create([
            'date' => '2026-10-30',
            'title' => 'Late night',
            'position' => 1,
        ]);

        $props = ['date' => '2026-10-30', 'dayLabel' => 'FRIDAY', 'isToday' => true, 'showLogControls' => true];

        Livewire::test(DayList::class, $props)->call('startLogging', $item->id);

        Carbon::setTestNow(Carbon::parse('2026-10-30 01:00:00', 'Europe/Bucharest')->utc());

        Livewire::test(DayList::class, $props)->call('finishLogging', $item->id);

        $log = TaskLog::query()->sole();

        $this->assertSame('2026-10-29 23:00', $log->started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-30 01:00', $log->finished_at->timezone('Europe/Bucharest')->format('Y-m-d H:i'));
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

    public function test_week_stats_are_separated_by_day(): void
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
            ->assertSee('3h')
            ->assertSee('Prognoze')
            ->assertSee('Cosmic')
            ->assertSee('MON 16 Mar')
            ->assertSee('WED 18 Mar')
            ->assertSee('TUE 17 Mar')
            ->assertSeeHtml('stat-day-2026-03-16')
            ->assertSeeHtml('stat-day-2026-03-17')
            ->assertSeeHtml('stat-day-2026-03-18');
    }

    public function test_log_page_shows_completed_todos_without_times_in_day_and_week_totals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        TodoItem::query()->create([
            'date' => '2026-03-16',
            'title' => 'Unchecked no times',
            'is_completed' => false,
            'position' => 1,
        ]);

        TodoItem::query()->create([
            'date' => '2026-03-16',
            'title' => 'SPRINGS - menu design',
            'is_completed' => true,
            'position' => 2,
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Timed session',
            'started_at' => Carbon::parse('2026-03-16 08:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-16 09:00:00', 'Europe/Bucharest')->utc(),
        ]);

        $html = Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSee('Timed session')
            ->assertSee('SPRINGS - menu design')
            ->assertSee('N/A')
            ->assertSeeHtml('data-untimed-bar')
            ->assertDontSee('Unchecked no times')
            ->html();

        $mondayColumn = Str::between($html, 'wire:key="day-2026-03-16"', 'wire:key="day-2026-03-17"');
        $this->assertStringContainsString('Timed session', $mondayColumn);
        $this->assertStringContainsString('SPRINGS - menu design', $mondayColumn);
        $this->assertStringContainsString('data-untimed-bar', $mondayColumn);
        $this->assertStringContainsString('repeating-linear-gradient', $mondayColumn);
    }

    public function test_can_create_log_by_editing_completed_untimed_na_times(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $item = TodoItem::query()->create([
            'date' => '2026-03-16',
            'title' => 'SPRINGS - menu design',
            'is_completed' => true,
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('startEditingUntimedTime', $item->id, 'started', 'stats')
            ->assertSet('editingTodoItemId', $item->id)
            ->set('editStartedAt', '09:15')
            ->set('editFinishedAt', '10:45')
            ->call('finishEditingTime')
            ->assertSet('editingTodoItemId', null)
            ->assertSee('09:15')
            ->assertSee('10:45')
            ->assertDontSeeHtml('data-untimed-bar');

        $log = TaskLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->todo_item_id);
        $this->assertSame(
            '2026-03-16 09:15',
            $log->started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
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

    public function test_log_page_can_open_add_modal(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSet('showAddModal', false)
            ->assertSeeHtml('aria-label="Add log entry"')
            ->call('openAddModal')
            ->assertSet('showAddModal', true)
            ->assertSet('logDate', '2026-03-18')
            ->assertSee('Add log entry')
            ->assertSee('Select a task')
            ->assertSee('WED')
            ->assertDontSeeHtml("wire:click=\"selectLogDate('2026-03-19')\"");
    }

    public function test_add_modal_filters_tasks_by_selected_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        TodoItem::query()->create([
            'date' => '2026-03-17',
            'title' => 'Tuesday task',
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => '2026-03-18',
            'title' => 'Wednesday task',
            'position' => 1,
        ]);

        $component = Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('openAddModal')
            ->assertSet('logDate', '2026-03-18');

        preg_match('/id="add-log-task".*?<\/select>/s', $component->html(), $wednesdaySelect);
        $this->assertNotEmpty($wednesdaySelect);
        $this->assertStringContainsString('Wednesday task', $wednesdaySelect[0]);
        $this->assertStringNotContainsString('Tuesday task', $wednesdaySelect[0]);

        $component
            ->call('selectLogDate', '2026-03-17')
            ->assertSet('logDate', '2026-03-17')
            ->assertSet('todoItemId', '');

        preg_match('/id="add-log-task".*?<\/select>/s', $component->html(), $tuesdaySelect);
        $this->assertNotEmpty($tuesdaySelect);
        $this->assertStringContainsString('Tuesday task', $tuesdaySelect[0]);
        $this->assertStringNotContainsString('Wednesday task', $tuesdaySelect[0]);
    }

    public function test_can_add_log_entry_from_modal(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $item = TodoItem::query()->create([
            'date' => '2026-03-18',
            'title' => 'Manual log task',
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('openAddModal')
            ->assertSet('logDate', '2026-03-18')
            ->set('todoItemId', (string) $item->id)
            ->set('startedAt', '13:50')
            ->set('finishedAt', '15:00')
            ->call('addLog')
            ->assertSet('showAddModal', false)
            ->assertSee('Manual log task')
            ->assertSee('13:50')
            ->assertSee('15:00');

        $log = TaskLog::query()->first();

        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->todo_item_id);
        $this->assertSame('Manual log task', $log->title);
        $this->assertSame(
            '2026-03-18 13:50',
            $log->started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
        $this->assertSame(
            '2026-03-18 15:00',
            $log->finished_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
    }

    public function test_add_log_requires_finish_after_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $item = TodoItem::query()->create([
            'date' => '2026-03-18',
            'title' => 'Bad times',
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('openAddModal')
            ->set('todoItemId', (string) $item->id)
            ->set('startedAt', '15:00')
            ->set('finishedAt', '13:50')
            ->call('addLog')
            ->assertHasErrors(['finishedAt'])
            ->assertSet('showAddModal', true);

        $this->assertDatabaseCount('task_logs', 0);
    }

    public function test_add_log_rejects_divider_tasks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $divider = TodoItem::query()->create([
            'date' => '2026-03-18',
            'title' => null,
            'is_separator' => true,
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('openAddModal')
            ->set('todoItemId', (string) $divider->id)
            ->set('startedAt', '13:50')
            ->set('finishedAt', '15:00')
            ->call('addLog')
            ->assertHasErrors(['todoItemId']);

        $this->assertDatabaseCount('task_logs', 0);
    }

    public function test_add_log_rejects_tasks_from_other_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $item = TodoItem::query()->create([
            'date' => '2026-03-17',
            'title' => 'Tuesday only',
            'position' => 1,
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('openAddModal')
            ->assertSet('logDate', '2026-03-18')
            ->set('todoItemId', (string) $item->id)
            ->set('startedAt', '13:50')
            ->set('finishedAt', '15:00')
            ->call('addLog')
            ->assertHasErrors(['todoItemId']);

        $this->assertDatabaseCount('task_logs', 0);
    }

    public function test_can_edit_log_start_time_inline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $log = TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Editable session',
            'started_at' => Carbon::parse('2026-03-18 09:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-18 10:00:00', 'Europe/Bucharest')->utc(),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('startEditingTime', $log->id, 'started')
            ->assertSet('editingLogId', $log->id)
            ->assertSet('editingField', 'started')
            ->assertSet('editTime', '09:00')
            ->set('editTime', '09:30')
            ->call('finishEditingTime')
            ->assertSet('editingLogId', null)
            ->assertSee('09:30')
            ->assertSee('10:00')
            ->assertSee('30m');

        $log->refresh();

        $this->assertSame(
            '2026-03-18 09:30',
            $log->started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
        $this->assertSame(
            '2026-03-18 10:00',
            $log->finished_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
    }

    public function test_can_edit_log_finish_time_inline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $log = TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Editable session',
            'started_at' => Carbon::parse('2026-03-18 09:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-18 10:00:00', 'Europe/Bucharest')->utc(),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('startEditingTime', $log->id, 'finished')
            ->assertSet('editingField', 'finished')
            ->assertSet('editTime', '10:00')
            ->set('editTime', '11:15')
            ->call('finishEditingTime')
            ->assertSet('editingLogId', null)
            ->assertSee('09:00')
            ->assertSee('11:15')
            ->assertSee('2h 15m');

        $log->refresh();

        $this->assertSame(
            '2026-03-18 11:15',
            $log->finished_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
    }

    public function test_edit_log_time_requires_finish_after_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $log = TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Bad edit',
            'started_at' => Carbon::parse('2026-03-18 09:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-18 10:00:00', 'Europe/Bucharest')->utc(),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('startEditingTime', $log->id, 'finished')
            ->set('editTime', '08:00')
            ->call('finishEditingTime')
            ->assertHasErrors(['editTime'])
            ->assertSet('editingLogId', $log->id);

        $log->refresh();

        $this->assertSame(
            '2026-03-18 10:00',
            $log->finished_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
    }

    public function test_cancel_edit_log_time_discards_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $log = TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Cancel edit',
            'started_at' => Carbon::parse('2026-03-18 09:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-18 10:00:00', 'Europe/Bucharest')->utc(),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->call('startEditingTime', $log->id, 'started')
            ->set('editTime', '08:00')
            ->call('cancelEditingTime')
            ->assertSet('editingLogId', null)
            ->assertSee('09:00');

        $log->refresh();

        $this->assertSame(
            '2026-03-18 09:00',
            $log->started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
    }

    public function test_consecutive_same_title_logs_that_touch_on_the_minute_render_as_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $first = TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'SPRINGS - menu work',
            'started_at' => Carbon::parse('2026-03-16 10:30:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-16 11:07:00', 'Europe/Bucharest')->utc(),
        ]);

        $second = TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'SPRINGS - menu work',
            'started_at' => Carbon::parse('2026-03-16 11:07:20', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-16 11:55:00', 'Europe/Bucharest')->utc(),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'SPRINGS - menu work',
            'started_at' => Carbon::parse('2026-03-16 12:10:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-16 12:40:00', 'Europe/Bucharest')->utc(),
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Other task',
            'started_at' => Carbon::parse('2026-03-16 12:40:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-16 13:00:00', 'Europe/Bucharest')->utc(),
        ]);

        $html = Livewire::withQueryParams(['week' => '2026-W12'])
            ->test(TaskLogBoard::class)
            ->assertSeeHtml('wire:key="log-'.$first->id.'-'.$second->id.'"')
            ->html();

        $mondayColumn = Str::between($html, 'wire:key="day-2026-03-16"', 'wire:key="day-2026-03-17"');

        $this->assertSame(1, substr_count($mondayColumn, 'SPRINGS - menu work · 10:30'));
        $this->assertSame(1, substr_count($mondayColumn, 'SPRINGS - menu work · 12:10'));
        $this->assertStringContainsString('10:30', $mondayColumn);
        $this->assertStringContainsString('11:55', $mondayColumn);
        $this->assertStringContainsString('1h 24m', $mondayColumn);
        $this->assertStringNotContainsString('11:07', $mondayColumn);
        $this->assertStringContainsString('Other task · 12:40', $mondayColumn);
        $this->assertStringContainsString('12:10', $mondayColumn);
    }

    public function test_in_progress_timer_start_can_be_edited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00', 'Europe/Bucharest'));

        $item = TodoItem::query()->create([
            'date' => '2026-03-18',
            'title' => 'In progress',
            'logging_started_at' => Carbon::parse('2026-03-18 10:15:00', 'Europe/Bucharest')->utc(),
            'position' => 1,
        ]);

        Livewire::test(DayList::class, [
            'date' => '2026-03-18',
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
            'showLogControls' => true,
        ])
            ->assertSee('10:15')
            ->call('startEditingLoggingStart', $item->id)
            ->assertSet('editingLoggingId', $item->id)
            ->set('editLoggingStartedAt', '09:40')
            ->call('finishEditingLoggingStart')
            ->assertSet('editingLoggingId', null)
            ->assertSee('09:40');

        $this->assertSame(
            '2026-03-18 09:40',
            $item->fresh()->logging_started_at->timezone('Europe/Bucharest')->format('Y-m-d H:i')
        );
    }
}
