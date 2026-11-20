<?php

namespace Tests\Feature;

use App\Livewire\ReadStats;
use App\Models\Book;
use App\Models\ReadingSession;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
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

    public function test_week_breakdown_includes_reech_content_and_engagement(): void
    {
        $tz = Clock::timezone();
        $monday = Carbon::parse('2026-11-09 00:00', $tz);
        $now = $monday->copy()->setTime(23, 0);

        config([
            'reech_timelog.url' => 'https://reech.test/api/work-sessions',
            'reech_timelog.token' => 'test-reech-token',
        ]);

        Book::query()->create(['title' => 'The Hacker\'s Renaissance', 'status' => Book::STATUS_FINISHED, 'position' => 1, 'kind' => Book::KIND_ARTICLE]);

        Http::fake([
            'https://reech.test/api/work-sessions*' => Http::response([
                'sessions' => [
                    ['id' => 7, 'type' => 'content', 'article_title' => 'Product manager is a "tool builder"', 'started_at' => $monday->copy()->setTime(9, 0)->toIso8601String(), 'ended_at' => $monday->copy()->setTime(10, 15)->toIso8601String()],
                    ['id' => 8, 'type' => 'engagement', 'article_title' => 'Product manager is a "tool builder"', 'started_at' => $monday->copy()->setTime(11, 0)->toIso8601String(), 'ended_at' => $monday->copy()->setTime(11, 20)->toIso8601String()],
                ],
            ]),
        ]);

        $data = ReadStats::weekBreakdown($now);

        $this->assertSame(75, $data['days'][0]['minutes']['content']);
        $this->assertSame(20, $data['days'][0]['minutes']['engagement']);
        $this->assertSame(75, $data['totals']['content']);
        $this->assertSame(20, $data['totals']['engagement']);
        $this->assertSame('Product manager is a "tool builder"', $data['days'][0]['books']['content'][0]['title']);
        $this->assertSame('Product manager is a "tool builder"', $data['days'][0]['books']['engagement'][0]['title']);
    }

    public function test_reech_sessions_keep_the_article_title_they_were_logged_with(): void
    {
        $tz = Clock::timezone();
        $monday = Carbon::parse('2026-11-09 00:00', $tz);
        $tuesday = $monday->copy()->addDay();

        config([
            'reech_timelog.url' => 'https://reech.test/api/work-sessions',
            'reech_timelog.token' => 'test-reech-token',
        ]);

        Http::fake([
            'https://reech.test/api/work-sessions*' => Http::response([
                'sessions' => [
                    ['id' => 7, 'type' => 'content', 'article_title' => 'First piece', 'started_at' => $monday->copy()->setTime(9, 0)->toIso8601String(), 'ended_at' => $monday->copy()->setTime(10, 0)->toIso8601String()],
                    ['id' => 8, 'type' => 'engagement', 'article_title' => 'Second piece', 'started_at' => $tuesday->copy()->setTime(9, 0)->toIso8601String(), 'ended_at' => $tuesday->copy()->setTime(9, 30)->toIso8601String()],
                ],
            ]),
        ]);

        $data = ReadStats::weekBreakdown($tuesday->copy()->setTime(23, 0));

        $this->assertSame('First piece', $data['days'][0]['books']['content'][0]['title']);
        $this->assertSame('Second piece', $data['days'][1]['books']['engagement'][0]['title']);
    }

    public function test_week_breakdown_can_target_a_previous_week(): void
    {
        $tz = Clock::timezone();
        $lastMonday = Carbon::parse('2026-11-09 00:00', $tz);
        $thisMonday = Carbon::parse('2026-11-16 00:00', $tz);
        $now = Carbon::parse('2026-11-20 12:00', $tz);

        $last = Book::query()->create(['title' => 'Last week book', 'status' => 'reading', 'position' => 1, 'type' => 'A']);
        $current = Book::query()->create(['title' => 'This week book', 'status' => 'reading', 'position' => 2, 'type' => 'B']);

        ReadingSession::query()->create(['book_id' => $last->id, 'started_at' => $lastMonday->copy()->setTime(10, 0)->utc(), 'finished_at' => $lastMonday->copy()->setTime(11, 0)->utc()]);
        ReadingSession::query()->create(['book_id' => $current->id, 'started_at' => $thisMonday->copy()->setTime(10, 0)->utc(), 'finished_at' => $thisMonday->copy()->setTime(11, 30)->utc()]);

        $thisWeek = ReadStats::weekBreakdown($now, $thisMonday);
        $this->assertSame(['This week book'], array_column($thisWeek['books'], 'title'));
        $this->assertSame(90, $thisWeek['totals']['B']);
        $this->assertSame(0, $thisWeek['totals']['A']);
        $this->assertSame('2026-11-16', $thisWeek['days'][0]['ymd']);

        $previous = ReadStats::weekBreakdown($now, $lastMonday);
        $this->assertSame(['Last week book'], array_column($previous['books'], 'title'));
        $this->assertSame(60, $previous['totals']['A']);
        $this->assertSame(0, $previous['totals']['B']);
        $this->assertSame('2026-11-09', $previous['days'][0]['ymd']);
    }

    public function test_week_toggle_defaults_to_current_week_and_navigates_with_url(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00', 'Europe/Bucharest')->utc());

        $last = Book::query()->create(['title' => 'Atomic Habits last week', 'status' => 'reading', 'position' => 1, 'type' => 'A']);
        $current = Book::query()->create(['title' => 'Deep Work this week', 'status' => 'reading', 'position' => 2, 'type' => 'B']);

        $lastMonday = Carbon::parse('2026-11-09 00:00', Clock::timezone());
        $thisMonday = Carbon::parse('2026-11-16 00:00', Clock::timezone());
        ReadingSession::query()->create(['book_id' => $last->id, 'started_at' => $lastMonday->copy()->setTime(10, 0)->utc(), 'finished_at' => $lastMonday->copy()->setTime(11, 0)->utc()]);
        ReadingSession::query()->create(['book_id' => $current->id, 'started_at' => $thisMonday->copy()->setTime(10, 0)->utc(), 'finished_at' => $thisMonday->copy()->setTime(12, 0)->utc()]);

        Livewire::test(ReadStats::class)
            ->assertSeeHtml('data-week-toggle')
            ->assertSee('16-22 Nov')
            ->assertDontSeeHtml('data-week-today')
            ->assertDontSeeHtml('aria-label="Next week"')
            ->assertSee('This week, hours per day')
            ->assertSee('Deep Work this week')
            ->assertDontSee('Atomic Habits last week')
            ->call('previousWeek')
            ->assertSet('week', '2026-W46')
            ->assertSee('9-15 Nov')
            ->assertSeeHtml('data-week-today')
            ->assertSeeHtml('aria-label="Next week"')
            ->assertSee('Hours per day')
            ->assertDontSee('This week, hours per day')
            ->assertSee('Atomic Habits last week')
            ->assertDontSee('Deep Work this week')
            ->call('nextWeek')
            ->assertSet('week', '')
            ->assertSee('16-22 Nov')
            ->assertSee('Deep Work this week')
            ->call('previousWeek')
            ->call('previousWeek')
            ->assertSet('week', '2026-W45')
            ->assertSee('2-8 Nov')
            ->call('goToCurrentWeek')
            ->assertSet('week', '')
            ->assertSee('16-22 Nov')
            ->assertSee('Deep Work this week');

        Livewire::withQueryParams(['week' => 'garbage'])->test(ReadStats::class)
            ->assertSet('week', '')
            ->assertSee('16-22 Nov')
            ->assertSee('Deep Work this week');

        Livewire::withQueryParams(['week' => '2026-W46'])->test(ReadStats::class)
            ->assertSet('week', '2026-W46')
            ->assertSee('9-15 Nov')
            ->assertSee('Atomic Habits last week')
            ->assertDontSee('Deep Work this week');

        Livewire::withQueryParams(['week' => '2026-W99'])->test(ReadStats::class)
            ->assertSet('week', '')
            ->assertSee('16-22 Nov');

        Livewire::withQueryParams(['week' => '2026-W52'])->test(ReadStats::class)
            ->assertSet('week', '')
            ->assertSee('16-22 Nov');

        $this->get(route('read.stats', ['week' => '2026-W46']))
            ->assertOk()
            ->assertSee('9-15 Nov')
            ->assertSee('Atomic Habits last week')
            ->assertDontSee('Deep Work this week');
    }
}
