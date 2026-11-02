<?php

namespace Tests\Feature;

use App\Livewire\ClientWeekBoard;
use App\Models\Project;
use App\Models\TodoItem;
use App\Models\WeekBoardItem;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_uses_bucharest_date_just_after_local_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 22:30:00', 'UTC'));

        $this->assertSame('2026-11-03', Clock::todayString());
        $this->assertSame('2026-11-03 00:00:00', Clock::today()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-W45', Clock::currentWeek());
    }

    public function test_week_rolls_over_on_bucharest_monday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 22:30:00', 'UTC'));

        $this->assertSame('2026-W46', Clock::currentWeek());
        $this->assertSame('2026-11', Clock::currentMonth());
    }

    public function test_week_board_start_lands_on_bucharest_today_and_stores_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 22:30:00', 'UTC'));

        $client = Project::query()->create(['hub' => 'partners', 'name' => 'Acme', 'slug' => 'acme', 'position' => 1]);
        $item = WeekBoardItem::factory()->create(['project_id' => $client->id, 'title' => 'Late task']);

        Livewire::test(ClientWeekBoard::class)->call('startCard', $item->id);

        $todo = TodoItem::query()->sole();
        $this->assertSame('2026-11-03', $todo->date->toDateString());
        $this->assertSame('2026-11-02 22:30:00', $todo->getRawOriginal('logging_started_at'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
