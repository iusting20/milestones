<?php

namespace Tests\Feature;

use App\Livewire\ReadStats;
use App\Models\Book;
use App\Models\ReadingSession;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_page_renders(): void
    {
        $this->get(route('read.stats'))->assertOk()->assertSee('Stats');
    }

    public function test_week_breakdown_groups_minutes_by_day_and_type(): void
    {
        $tz = Clock::timezone();
        $monday = Carbon::parse('2026-11-09 00:00', $tz);
        $now = $monday->copy()->setTime(23, 0);

        $a = Book::query()->create(['title' => 'A book', 'status' => 'reading', 'position' => 1, 'type' => 'A']);
        $b = Book::query()->create(['title' => 'B book', 'status' => 'reading', 'position' => 2, 'type' => 'B']);

        ReadingSession::query()->create(['book_id' => $a->id, 'started_at' => $monday->copy()->setTime(10, 0)->utc(), 'finished_at' => $monday->copy()->setTime(11, 30)->utc()]);
        ReadingSession::query()->create(['book_id' => $b->id, 'started_at' => $monday->copy()->setTime(20, 0)->utc(), 'finished_at' => $monday->copy()->setTime(20, 45)->utc()]);
        // last week, ignored
        ReadingSession::query()->create(['book_id' => $b->id, 'started_at' => $monday->copy()->subDay()->setTime(10, 0)->utc(), 'finished_at' => $monday->copy()->subDay()->setTime(12, 0)->utc()]);

        $data = ReadStats::weekBreakdown($now);

        $this->assertSame(90, $data['days'][0]['minutes']['A']);
        $this->assertSame(45, $data['days'][0]['minutes']['B']);
        $this->assertSame(0, $data['days'][1]['minutes']['A']);
        $this->assertSame(90, $data['totals']['A']);
        $this->assertSame(45, $data['totals']['B']);
        $this->assertSame(0, $data['totals']['C']);
        $this->assertSame(['A book', 'B book'], array_column($data['books'], 'title'));
        $this->assertSame([90, 45], array_column($data['books'], 'minutes'));
        $this->assertSame([['title' => 'A book', 'minutes' => 90, 'startedToday' => false, 'finished' => false]], $data['days'][0]['books']['A']);
        $this->assertSame([], $data['days'][0]['books']['C']);
    }
}
