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

    public function test_mark_started_today_stores_bucharest_date_and_shows_glyph_only_that_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 23:30:00', Clock::timezone()));
        $book = $this->book(['title' => 'Started Book']);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('markStartedToday', $book->id)
            ->assertSee('aria-label="Started today"', false)
            ->assertSee('⏻')
            ->assertSee("Clear 'started today'", false)
            ->assertDontSee("Mark as 'started today'", false);

        $this->assertSame('2026-11-16', $book->fresh()->started_on->toDateString());

        $this->travelTo(Carbon::parse('2026-11-17 00:30:00', Clock::timezone()));

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertDontSee('aria-label="Started today"', false)
            ->assertDontSee('⏻')
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
            ->assertSee('⏻')
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

    public function test_stats_show_started_today_glyph_only_on_that_day(): void
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
            ->assertSee('⏻')
            ->assertSee('aria-label="Started today"', false)
            ->assertSee('aria-label="Finished"', false)
            ->assertSee('Glyph book');

        $nextDay = ReadStats::weekBreakdown($monday->copy()->addDay()->setTime(10, 0));
        $this->assertFalse($nextDay['books'][0]['startedToday']);
        $this->assertTrue($nextDay['books'][0]['finished']);
        $this->assertFalse($nextDay['days'][0]['books']['A'][0]['startedToday']);

        $this->travelTo($monday->copy()->addDay()->setTime(10, 0));
        $this->get(route('read.stats'))
            ->assertOk()
            ->assertDontSee('⏻')
            ->assertDontSee('aria-label="Started today"', false)
            ->assertSee('aria-label="Finished"', false)
            ->assertSee('Glyph book');
    }
}
