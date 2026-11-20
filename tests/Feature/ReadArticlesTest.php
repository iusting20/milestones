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

class ReadArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_articles_page_renders_and_is_not_indexed(): void
    {
        $this->get(route('read.articles'))
            ->assertOk()
            ->assertSee('Articles')
            ->assertSeeLivewire(Read::class)
            ->assertSee('>books</a>', false)
            ->assertSee('+ Article')
            ->assertDontSee('+ Book')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_read_page_has_subtle_articles_link(): void
    {
        $this->get(route('read'))
            ->assertOk()
            ->assertSee('>articles</a>', false)
            ->assertSee('Books')
            ->assertDontSee('+ Article');
    }

    public function test_unauthenticated_articles_page_redirects_to_login(): void
    {
        $this->authenticate = false;
        $this->flushSession();

        $this->get(route('read.articles'))
            ->assertRedirect(route('login'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_can_add_article_and_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 15:00:00', Clock::timezone()));

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])
            ->assertSet('kind', Book::KIND_ARTICLE)
            ->call('startAddBook')
            ->set('newBookTitle', 'Why We Sleep')
            ->set('newBookAuthor', 'Matthew Walker')
            ->set('newBookTotalPages', '999')
            ->call('addBook')
            ->assertSee('Why We Sleep')
            ->assertSee('Matthew Walker');

        $article = Book::query()->where('title', 'Why We Sleep')->first();
        $this->assertNotNull($article);
        $this->assertSame(Book::KIND_ARTICLE, $article->kind);
        $this->assertSame('Matthew Walker', $article->author);
        $this->assertNull($article->total_pages);
        $this->assertSame(Book::STATUS_READING, $article->status);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $article->id])
            ->call('startAddSession')
            ->set('newSessionStartedAt', '2026-11-16T14:00')
            ->set('newSessionFinishedAt', '2026-11-16T14:25')
            ->set('newSessionNote', 'intro')
            ->call('addSession')
            ->assertSee('25m')
            ->assertSee('intro')
            ->assertSeeHtml('x-data="inlineFinish(');

        $session = ReadingSession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame($article->id, $session->book_id);
        $this->assertNull($session->pages_read);
        $this->assertSame('intro', $session->note);
        $this->assertSame(25, $session->durationMinutes());
    }

    public function test_articles_and_books_are_listed_separately(): void
    {
        $book = Book::query()->create([
            'title' => 'Deep Work',
            'author' => 'Cal Newport',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ]);
        $article = Book::query()->create([
            'title' => 'A Modest Proposal',
            'author' => 'Jonathan Swift',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_ARTICLE,
        ]);

        Livewire::test(Read::class)
            ->assertSee('Deep Work')
            ->assertDontSee('A Modest Proposal');

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])
            ->assertSee('A Modest Proposal')
            ->assertDontSee('Deep Work');

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $book->id])
            ->assertSet('selectedBookId', $book->id)
            ->assertDontSeeHtml('>'.$book->title.'</h2>');

        $this->get(route('read.articles'))
            ->assertOk()
            ->assertSee('A Modest Proposal')
            ->assertDontSee('Deep Work')
            ->assertSee('>books</a>', false);
    }

    public function test_books_page_totals_ignore_article_sessions(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 18:00:00', Clock::timezone()));

        $book = Book::query()->create([
            'title' => 'Book Time',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_BOOK,
        ]);
        $article = Book::query()->create([
            'title' => 'Article Time',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_ARTICLE,
        ]);

        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-16 10:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-16 10:30:00', Clock::timezone())->utc(),
        ]);
        ReadingSession::query()->create([
            'book_id' => $article->id,
            'started_at' => Carbon::parse('2026-11-16 11:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-16 12:00:00', Clock::timezone())->utc(),
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSee('30m')
            ->assertDontSee('1h 30m');

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $article->id])
            ->assertSee('1h');
    }

    public function test_inline_finish_edit_works_for_article_sessions(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', Clock::timezone()));
        $article = Book::query()->create([
            'title' => 'Inline Article',
            'status' => Book::STATUS_READING,
            'position' => 1,
            'kind' => Book::KIND_ARTICLE,
        ]);
        $session = ReadingSession::query()->create([
            'book_id' => $article->id,
            'started_at' => Carbon::parse('2026-11-16 10:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-16 10:20', Clock::timezone())->utc(),
        ]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE, 'selectedBookId' => $article->id])
            ->call('updateSessionFinish', $session->id, '10:45')
            ->assertReturned(fn ($r) => $r['ok'] === true && $r['finish'] === '10:45' && $r['duration'] === '45m')
            ->assertSee('45m');

        $this->assertSame(
            '2026-11-16 10:45',
            $session->fresh()->finished_at->timezone(Clock::timezone())->format('Y-m-d H:i')
        );
    }

    public function test_stats_keep_book_totals_and_show_articles_category(): void
    {
        $tz = Clock::timezone();
        $monday = Carbon::parse('2026-11-09 00:00', $tz);
        $now = $monday->copy()->setTime(23, 0);

        $a = Book::query()->create(['title' => 'A book', 'status' => 'reading', 'position' => 1, 'type' => 'A', 'kind' => Book::KIND_BOOK]);
        $article = Book::query()->create(['title' => 'An article', 'status' => 'reading', 'position' => 1, 'type' => 'A', 'kind' => Book::KIND_ARTICLE]);

        ReadingSession::query()->create([
            'book_id' => $a->id,
            'started_at' => $monday->copy()->setTime(10, 0)->utc(),
            'finished_at' => $monday->copy()->setTime(11, 30)->utc(),
        ]);
        ReadingSession::query()->create([
            'book_id' => $article->id,
            'started_at' => $monday->copy()->setTime(14, 0)->utc(),
            'finished_at' => $monday->copy()->setTime(14, 40)->utc(),
        ]);

        $data = ReadStats::weekBreakdown($now);

        $this->assertSame(90, $data['days'][0]['minutes']['A']);
        $this->assertSame(40, $data['days'][0]['minutes']['articles']);
        $this->assertSame(90, $data['totals']['A']);
        $this->assertSame(40, $data['totals']['articles']);
        $this->assertSame(0, $data['totals']['B']);
        $this->assertSame(['A book'], array_column($data['books'], 'title'));
        $this->assertSame(['An article'], array_column($data['articles'], 'title'));
        $this->assertSame([40], array_column($data['articles'], 'minutes'));
        $this->assertSame([['title' => 'An article', 'minutes' => 40, 'startedToday' => true, 'startedLabel' => 'Started today', 'finished' => false]], $data['days'][0]['books']['articles']);

        $this->travelTo($now);
        $this->get(route('read.stats'))
            ->assertOk()
            ->assertSee('Articles')
            ->assertSee('An article')
            ->assertSee('A book')
            ->assertSee('Articles read this week');
    }

    public function test_new_article_is_added_at_the_top_without_changing_relative_order(): void
    {
        $a = Book::query()->create(['title' => 'Older article', 'status' => Book::STATUS_READING, 'position' => 1, 'kind' => Book::KIND_ARTICLE]);
        $b = Book::query()->create(['title' => 'Newer article', 'status' => Book::STATUS_READING, 'position' => 2, 'kind' => Book::KIND_ARTICLE]);
        $book = Book::query()->create(['title' => 'A book', 'status' => Book::STATUS_READING, 'position' => 1, 'kind' => Book::KIND_BOOK]);

        Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])
            ->call('startAddBook')
            ->set('newBookTitle', 'Brand new article')
            ->call('addBook');

        $new = Book::query()->where('title', 'Brand new article')->first();
        $this->assertNotNull($new);
        $this->assertSame(Book::KIND_ARTICLE, $new->kind);
        $this->assertSame(1, $new->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(3, $b->fresh()->position);
        $this->assertSame(1, $book->fresh()->position);

        $html = Livewire::test(Read::class, ['kind' => Book::KIND_ARTICLE])->html();
        $posNew = strpos($html, 'data-sort-item="'.$new->id.'"');
        $posA = strpos($html, 'data-sort-item="'.$a->id.'"');
        $posB = strpos($html, 'data-sort-item="'.$b->id.'"');
        $this->assertLessThan($posA, $posNew);
        $this->assertLessThan($posB, $posA);
    }
}
