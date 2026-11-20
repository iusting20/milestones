<?php

namespace Tests\Feature;

use App\Livewire\Read;
use App\Livewire\ReadStats;
use App\Models\Book;
use App\Models\ReadingSession;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadStatusMarksTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $attrs = []): Book
    {
        return Book::query()->create(array_merge([
            'title' => 'Open Book',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ], $attrs));
    }

    public function test_item_page_has_started_today_and_finished_links(): void
    {
        $book = $this->book(['title' => "You're shit at halloween"]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSee("Mark as 'started today'", false)
            ->assertSee("Mark as 'finished'", false)
            ->assertDontSee('Mark as unfinished')
            ->assertDontSee("Clear 'started today'", false);
    }

    public function test_marking_finished_moves_item_into_collapsed_finished_list(): void
    {
        $open = $this->book(['title' => 'Still reading', 'position' => 1]);
        $done = $this->book(['title' => 'Done book', 'position' => 2]);

        $component = Livewire::test(Read::class, ['selectedBookId' => $done->id])
            ->assertSeeHtml('data-sort-item="'.$done->id.'"')
            ->assertDontSeeHtml('data-finished-item="'.$done->id.'"')
            ->assertDontSee('Finished (')
            ->call('markBookFinished', $done->id)
            ->assertSet('showFinished', false);

        $this->assertSame(Book::STATUS_FINISHED, $done->fresh()->status);

        $html = $component->html();
        $this->assertStringNotContainsString('data-sort-item="'.$done->id.'"', $html);
        $this->assertStringContainsString('data-sort-item="'.$open->id.'"', $html);
        $this->assertStringContainsString('Finished (1)', $html);
        $this->assertStringNotContainsString('data-finished-item="'.$done->id.'"', $html);
        $this->assertStringContainsString('Mark as unfinished', $html);
        $this->assertStringContainsString('aria-label="Finished"', $html);

        $component->set('showFinished', true)
            ->assertSeeHtml('data-finished-item="'.$done->id.'"')
            ->assertSee('Done book')
            ->call('markBookReading', $done->id)
            ->assertSeeHtml('data-sort-item="'.$done->id.'"')
            ->assertDontSeeHtml('data-finished-item="'.$done->id.'"')
            ->assertDontSee('Finished (');

        $this->assertSame(Book::STATUS_READING, $done->fresh()->status);
    }

    public function test_started_today_icon_is_an_svg_and_absent_otherwise(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', Clock::timezone()));
        $started = $this->book(['title' => 'Started SVG', 'started_on' => '2026-11-16', 'position' => 1]);

        $startedHtml = Livewire::test(Read::class, ['selectedBookId' => $started->id])->html();
        $this->assertStringContainsString('data-read-icon="started-today"', $startedHtml);
        $this->assertStringContainsString('<svg', $startedHtml);
        $this->assertStringNotContainsString('⏻', $startedHtml);
        $this->assertStringNotContainsString('data-read-icon="finished"', $startedHtml);

        $started->update(['started_on' => '2026-11-15']);
        $otherHtml = Livewire::test(Read::class, ['selectedBookId' => $started->id])->html();
        $this->assertStringNotContainsString('data-read-icon="started-today"', $otherHtml);
        $this->assertStringNotContainsString('⏻', $otherHtml);
    }

    public function test_mark_started_today_stores_bucharest_date_and_shows_glyph_only_that_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 23:30:00', Clock::timezone()));
        $book = $this->book(['title' => 'Started Book']);

        $html = Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('markStartedToday', $book->id)
            ->assertSee('aria-label="Started today"', false)
            ->assertSeeHtml('data-read-icon="started-today"')
            ->assertSeeHtml('<svg')
            ->assertDontSeeHtml('⏻')
            ->assertSee("Clear 'started today'", false)
            ->assertDontSee("Mark as 'started today'", false)
            ->html();

        $this->assertSame(2, substr_count($html, 'data-read-icon="started-today"')); // list + title
        $this->assertStringContainsString('stroke="currentColor"', $html);

        $this->assertSame('2026-11-16', $book->fresh()->started_on->toDateString());

        $this->travelTo(Carbon::parse('2026-11-17 00:30:00', Clock::timezone()));

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertDontSee('aria-label="Started today"', false)
            ->assertDontSeeHtml('data-read-icon="started-today"')
            ->assertSee("Mark as 'started today'", false)
            ->assertDontSee("Clear 'started today'", false);

        $this->assertSame('2026-11-16', $book->fresh()->started_on->toDateString());
    }

    public function test_clear_started_today_only_clears_when_the_date_is_today(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', Clock::timezone()));
        $book = $this->book(['started_on' => '2026-11-15']);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('clearStartedToday', $book->id);

        $this->assertSame('2026-11-15', $book->fresh()->started_on->toDateString());

        $book->update(['started_on' => '2026-11-16']);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('clearStartedToday', $book->id);

        $this->assertNull($book->fresh()->started_on);
    }

    public function test_articles_use_the_same_marks(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 15:00:00', Clock::timezone()));
        $article = $this->book([
            'title' => "You're shit at halloween",
            'kind' => Book::KIND_ARTICLE,
        ]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $article->id])
            ->assertSee("Mark as 'started today'", false)
            ->assertSee("Mark as 'finished'", false)
            ->call('markStartedToday', $article->id)
            ->call('markBookFinished', $article->id)
            ->assertSet('showFinished', false)
            ->assertDontSeeHtml('data-sort-item="'.$article->id.'"')
            ->assertSee('Finished (1)')
            ->set('showFinished', true)
            ->assertSeeHtml('data-finished-item="'.$article->id.'"')
            ->assertSeeHtml('data-read-icon="started-today"')
            ->assertSeeHtml('data-read-icon="finished"')
            ->assertSeeHtml('<svg')
            ->assertDontSeeHtml('⏻')
            ->assertSee('aria-label="Finished"', false);
    }

    public function test_reorder_ignores_finished_items(): void
    {
        $a = $this->book(['title' => 'A', 'position' => 1]);
        $b = $this->book(['title' => 'B', 'position' => 2, 'status' => Book::STATUS_FINISHED]);
        $c = $this->book(['title' => 'C', 'position' => 3]);

        Livewire::test(Read::class)->call('reorderBooks', [$c->id, $a->id]);
        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);

        Livewire::test(Read::class)->call('reorderBooks', [$b->id, $a->id]);
        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
    }

    public function test_stats_show_started_glyph_throughout_the_week_of_the_explicit_start(): void
    {
        $tz = Clock::timezone();
        $monday = Carbon::parse('2026-11-16 00:00', $tz);
        $now = $monday->copy()->setTime(18, 0);

        $book = $this->book([
            'title' => 'Glyph book',
            'type' => 'A',
            'started_on' => '2026-11-16',
            'status' => Book::STATUS_FINISHED,
        ]);
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => $monday->copy()->setTime(10, 0)->utc(),
            'finished_at' => $monday->copy()->setTime(10, 30)->utc(),
        ]);

        $today = ReadStats::weekBreakdown($now);
        $this->assertTrue($today['books'][0]['startedToday']);
        $this->assertTrue($today['books'][0]['finished']);
        $this->assertTrue($today['days'][0]['books']['A'][0]['startedToday']);
        $this->assertTrue($today['days'][0]['books']['A'][0]['finished']);

        $this->travelTo($now);
        $this->get(route('read.stats'))
            ->assertOk()
            ->assertSeeHtml('data-read-icon="started-today"')
            ->assertSeeHtml('data-read-icon="finished"')
            ->assertSeeHtml('<svg')
            ->assertDontSeeHtml('⏻')
            ->assertSee('aria-label="Started today"', false)
            ->assertSee('aria-label="Finished"', false)
            ->assertSee('Glyph book');

        $this->assertSame('Started today', $today['books'][0]['startedLabel']);

        $nextDay = ReadStats::weekBreakdown($monday->copy()->addDay()->setTime(10, 0));
        $this->assertTrue($nextDay['books'][0]['startedToday']);
        $this->assertTrue($nextDay['books'][0]['finished']);
        $this->assertTrue($nextDay['days'][0]['books']['A'][0]['startedToday']);
        $this->assertSame('Started this week', $nextDay['books'][0]['startedLabel']);

        $this->travelTo($monday->copy()->addDay()->setTime(10, 0));
        $this->get(route('read.stats'))
            ->assertOk()
            ->assertSeeHtml('data-read-icon="started-today"')
            ->assertSeeHtml('data-read-icon="finished"')
            ->assertSeeHtml('<svg')
            ->assertDontSeeHtml('⏻')
            ->assertSee('aria-label="Started this week"', false)
            ->assertDontSee('aria-label="Started today"', false)
            ->assertSee('aria-label="Finished"', false)
            ->assertSee('Glyph book');

        $previousWeek = ReadStats::weekBreakdown($now, $monday->copy()->subWeek());
        $this->assertSame([], $previousWeek['books']);
    }

    public function test_first_session_today_shows_glyph_on_list_title_and_session_without_explicit_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', Clock::timezone()));
        $book = $this->book(['title' => 'First session today']);
        $session = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-20 09:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-20 09:30:00', Clock::timezone())->utc(),
        ]);

        $html = Livewire::test(Read::class, ['selectedBookId' => $book->id])->html();
        $this->assertSame(3, substr_count($html, 'data-read-icon="started-today"')); // list + title + session
        $this->assertStringContainsString('aria-label="Started today"', $html);
        $this->assertStringContainsString('wire:key="session-'.$session->id.'"', $html);
        $blockStart = strpos($html, 'wire:key="session-'.$session->id.'"');
        $this->assertNotFalse($blockStart);
        $this->assertStringContainsString('data-read-icon="started-today"', substr($html, $blockStart, 1500));
        $this->assertStringContainsString('aria-label="Started"', substr($html, $blockStart, 1500));
    }

    public function test_first_session_on_a_past_day_shows_glyph_only_on_that_session_row(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', Clock::timezone()));
        $book = $this->book(['title' => 'Started last week']);
        $first = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-10 10:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-10 11:00:00', Clock::timezone())->utc(),
        ]);
        $later = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-18 10:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-18 10:45:00', Clock::timezone())->utc(),
        ]);

        $html = Livewire::test(Read::class, ['selectedBookId' => $book->id])->html();
        $this->assertSame(1, substr_count($html, 'data-read-icon="started-today"'));
        $this->assertStringNotContainsString('aria-label="Started today"', $html);

        $posLater = strpos($html, 'wire:key="session-'.$later->id.'"');
        $posFirst = strpos($html, 'wire:key="session-'.$first->id.'"');
        $this->assertNotFalse($posLater);
        $this->assertNotFalse($posFirst);
        $this->assertLessThan($posFirst, $posLater); // newest first
        $this->assertStringNotContainsString('data-read-icon="started-today"', substr($html, $posLater, $posFirst - $posLater));
        $this->assertStringContainsString('data-read-icon="started-today"', substr($html, $posFirst, 1500));
        $this->assertStringContainsString('aria-label="Started"', substr($html, $posFirst, 1500));
    }

    public function test_explicit_started_on_wins_over_an_earlier_first_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', Clock::timezone()));
        $book = $this->book(['title' => 'Marked start', 'started_on' => '2026-11-18']);
        $first = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-10 10:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-10 11:00:00', Clock::timezone())->utc(),
        ]);
        $onStart = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-18 09:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-18 09:20:00', Clock::timezone())->utc(),
        ]);
        $laterSameDay = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-18 15:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-18 15:10:00', Clock::timezone())->utc(),
        ]);

        $html = Livewire::test(Read::class, ['selectedBookId' => $book->id])->html();
        $this->assertSame(1, substr_count($html, 'data-read-icon="started-today"'));

        $posOnStart = strpos($html, 'wire:key="session-'.$onStart->id.'"');
        $posFirst = strpos($html, 'wire:key="session-'.$first->id.'"');
        $posLaterSameDay = strpos($html, 'wire:key="session-'.$laterSameDay->id.'"');
        $this->assertNotFalse($posOnStart);
        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posLaterSameDay);
        $this->assertStringContainsString('data-read-icon="started-today"', substr($html, $posOnStart, 1500));
        $this->assertStringNotContainsString('data-read-icon="started-today"', substr($html, $posFirst, 1500));
        $this->assertLessThan($posOnStart, $posLaterSameDay);
        $this->assertStringNotContainsString('data-read-icon="started-today"', substr($html, $posLaterSameDay, $posOnStart - $posLaterSameDay));
    }

    public function test_stats_show_started_glyph_in_the_week_of_the_first_session(): void
    {
        $tz = Clock::timezone();
        $lastMonday = Carbon::parse('2026-11-09 00:00', $tz);
        $thisMonday = Carbon::parse('2026-11-16 00:00', $tz);
        $friday = Carbon::parse('2026-11-20 12:00', $tz);

        $book = $this->book(['title' => 'First session book', 'type' => 'A']);
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => $lastMonday->copy()->setTime(10, 0)->utc(),
            'finished_at' => $lastMonday->copy()->setTime(11, 0)->utc(),
        ]);
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => $thisMonday->copy()->setTime(10, 0)->utc(),
            'finished_at' => $thisMonday->copy()->setTime(10, 30)->utc(),
        ]);

        $thisWeek = ReadStats::weekBreakdown($friday, $thisMonday);
        $this->assertSame(['First session book'], array_column($thisWeek['books'], 'title'));
        $this->assertFalse($thisWeek['books'][0]['startedToday']);

        $lastWeek = ReadStats::weekBreakdown($friday, $lastMonday);
        $this->assertTrue($lastWeek['books'][0]['startedToday']);
        $this->assertSame('Started this week', $lastWeek['books'][0]['startedLabel']);
        $this->assertTrue($lastWeek['days'][0]['books']['A'][0]['startedToday']);

        $this->travelTo($friday);
        $this->get(route('read.stats', ['week' => '2026-W46']))
            ->assertOk()
            ->assertSee('First session book')
            ->assertSeeHtml('data-read-icon="started-today"')
            ->assertSee('aria-label="Started this week"', false)
            ->assertDontSeeHtml('⏻');

        $this->get(route('read.stats'))
            ->assertOk()
            ->assertSee('First session book')
            ->assertDontSeeHtml('data-read-icon="started-today"');
    }

    public function test_articles_first_session_uses_the_same_start_glyph_rule(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', Clock::timezone()));
        $article = $this->book([
            'title' => 'First article',
            'kind' => Book::KIND_ARTICLE,
        ]);
        $session = ReadingSession::query()->create([
            'book_id' => $article->id,
            'started_at' => Carbon::parse('2026-11-10 10:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-10 10:20:00', Clock::timezone())->utc(),
        ]);

        $html = Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $article->id])->html();
        $this->assertSame(1, substr_count($html, 'data-read-icon="started-today"'));
        $pos = strpos($html, 'wire:key="session-'.$session->id.'"');
        $this->assertNotFalse($pos);
        $this->assertStringContainsString('data-read-icon="started-today"', substr($html, $pos, 1500));
    }
}
