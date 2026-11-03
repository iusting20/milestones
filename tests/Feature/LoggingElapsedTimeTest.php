<?php

namespace Tests\Feature;

use App\Livewire\DayList;
use App\Models\TodoItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoggingElapsedTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_timer_renders_elapsed_element_with_start_epoch(): void
    {
        Carbon::setTestNow('2026-03-18 10:15:00');
        $date = now()->toDateString();
        $item = TodoItem::query()->create(['date' => $date, 'title' => 'Ship feature', 'position' => 1]);

        $component = Livewire::test(DayList::class, [
            'date' => $date,
            'dayLabel' => 'WEDNESDAY',
            'isToday' => true,
            'showLogControls' => true,
        ])->assertDontSeeHtml('data-elapsed-since=');

        $component->call('startLogging', $item->id);
        $item->refresh();

        $component->assertSeeHtml('data-elapsed-since="'.$item->logging_started_at->getTimestampMs().'"');
    }
}
