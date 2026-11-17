<?php

namespace Tests\Feature;

use App\Livewire\Read;
use App\Models\Book;
use App\Models\ReadingSession;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_page_renders(): void
    {
        $this->get(route('read'))
            ->assertOk()
            ->assertSee('Read')
            ->assertSeeLivewire(Read::class)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_read_link_is_in_nav(): void
    {
        $html = $this->get(route('milestones'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]+href="[^"]*/read"[^>]*>\s*Read\s*</a>#',
            $html,
        );
    }

    public function test_unauthenticated_read_redirects_to_login(): void
    {
        $this->authenticate = false;
        $this->flushSession();

        $this->get(route('read'))
            ->assertRedirect(route('login'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_can_add_book_and_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 15:00:00', Clock::timezone()));

        Livewire::test(Read::class)
            ->call('startAddBook')
            ->set('newBookTitle', 'Atomic Habits')
            ->set('newBookAuthor', 'James Clear')
            ->set('newBookTotalPages', '320')
            ->call('addBook')
            ->assertSee('Atomic Habits');

        $book = Book::query()->where('title', 'Atomic Habits')->first();
        $this->assertNotNull($book);
        $this->assertSame('James Clear', $book->author);
        $this->assertSame(320, $book->total_pages);
        $this->assertSame(Book::STATUS_READING, $book->status);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('startAddSession')
            ->set('newSessionStartedAt', '2026-11-09T14:00')
            ->set('newSessionFinishedAt', '2026-11-09T14:45')
            ->set('newSessionPagesRead', '12')
            ->set('newSessionNote', 'chapter 1')
            ->call('addSession')
            ->assertSee('45m')
            ->assertSee('chapter 1');

        $session = ReadingSession::query()->first();
        $this->assertNotNull($session);
        $this->assertSame(12, $session->pages_read);
        $this->assertSame('chapter 1', $session->note);
        $this->assertSame(45, $session->durationMinutes());
        $this->assertSame(
            '2026-11-09 14:00',
            $session->started_at->timezone(Clock::timezone())->format('Y-m-d H:i')
        );
    }

    public function test_rejects_session_when_finish_is_not_after_start(): void
    {
        $book = Book::query()->create([
            'title' => 'Deep Work',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('startAddSession')
            ->set('newSessionStartedAt', '2026-11-09T15:00')
            ->set('newSessionFinishedAt', '2026-11-09T14:00')
            ->call('addSession')
            ->assertHasErrors('newSessionFinishedAt');

        $this->assertSame(0, ReadingSession::query()->count());
    }

    public function test_start_and_stop_session_now(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 16:30:00', Clock::timezone()));

        $book = Book::query()->create([
            'title' => 'Sapiens',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('startSessionNow')
            ->assertSee('Stop session')
            ->assertSee('running');

        $session = ReadingSession::query()->first();
        $this->assertNotNull($session);
        $this->assertNull($session->finished_at);

        $this->travelTo(Carbon::parse('2026-11-09 17:10:00', Clock::timezone()));

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('stopSession', $session->id)
            ->assertDontSee('running');

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertSame(40, $session->durationMinutes());
    }

    public function test_can_edit_and_delete_session_and_book(): void
    {
        $book = Book::query()->create([
            'title' => 'Old Title',
            'author' => 'A',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);
        $session = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-08 10:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-08 10:20:00', Clock::timezone())->utc(),
            'pages_read' => 5,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('startEditBook', $book->id)
            ->set('editBookTitle', 'New Title')
            ->set('editBookAuthor', 'B')
            ->set('editBookStatus', Book::STATUS_FINISHED)
            ->call('saveEditBook');

        $book->refresh();
        $this->assertSame('New Title', $book->title);
        $this->assertSame('B', $book->author);
        $this->assertTrue($book->isFinished());

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('startEditSession', $session->id)
            ->set('editSessionStartedAt', '2026-11-08T10:00')
            ->set('editSessionFinishedAt', '2026-11-08T10:35')
            ->set('editSessionPagesRead', '8')
            ->set('editSessionNote', 'revised')
            ->call('saveEditSession');

        $session->refresh();
        $this->assertSame(35, $session->durationMinutes());
        $this->assertSame(8, $session->pages_read);
        $this->assertSame('revised', $session->note);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('deleteSession', $session->id);

        $this->assertDatabaseMissing('reading_sessions', ['id' => $session->id]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('deleteBook', $book->id);

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_today_and_week_totals_count_completed_sessions(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 12:00:00', Clock::timezone()));

        $book = Book::query()->create([
            'title' => 'Stats',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-09 09:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-09 10:00:00', Clock::timezone())->utc(),
        ]);
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-08 09:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-08 09:30:00', Clock::timezone())->utc(),
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSee('Today')
            ->assertSee('This week')
            ->assertSee('1h')
            ->assertDontSee('This month');
    }

    public function test_running_session_counts_in_totals_with_day_clipping(): void
    {
        // Monday 09 Nov 2026 10:00 Bucharest — week started Mon 09 Nov 00:00
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', Clock::timezone()));

        $book = Book::query()->create([
            'title' => 'Live Book',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        // Finished 30m today
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-09 08:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-09 08:30:00', Clock::timezone())->utc(),
        ]);

        // Running since yesterday 23:00 — today should only count 00:00→10:00 = 10h
        // Book total should count yesterday 23:00→10:00 = 11h, plus finished 30m = 11h 30m
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-08 23:00:00', Clock::timezone())->utc(),
            'finished_at' => null,
        ]);

        $html = Livewire::test(Read::class, ['selectedBookId' => $book->id])->html();

        // Today: 30m finished + 10h running = 10h 30m
        $this->assertStringContainsString('10h 30m', $html);
        // Live timer markup present
        $this->assertStringContainsString('elapsedClock', $html);
        $this->assertStringContainsString('running', $html);

        // Book total includes full running span (11h) + 30m = 11h 30m
        $this->assertStringContainsString('11h 30m', $html);
    }
    public function test_edit_controls_hidden_until_cog_toggled(): void
    {
        $book = Book::query()->create([
            'title' => 'Cog Book',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-09 09:00:00', Clock::timezone())->utc(),
            'finished_at' => Carbon::parse('2026-11-09 09:20:00', Clock::timezone())->utc(),
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSet('editMode', false)
            ->assertDontSee('Mark finished')
            ->assertDontSeeHtml('wire:click="startEditBook')
            ->assertDontSeeHtml('wire:click="deleteBook')
            ->assertDontSeeHtml('wire:click="startEditSession')
            ->assertDontSeeHtml('wire:click="deleteSession')
            ->assertSeeHtml('Show edit controls')
            ->assertSee('Start session now')
            ->assertSee('+ Log session')
            ->set('editMode', true)
            ->assertSee('Mark finished')
            ->assertSeeHtml('wire:click="startEditBook')
            ->assertSeeHtml('wire:click="deleteBook')
            ->assertSeeHtml('wire:click="startEditSession')
            ->assertSeeHtml('wire:click="deleteSession')
            ->assertSeeHtml('Hide edit controls')
            ->assertSee('Start session now')
            ->assertSee('+ Log session');
    }

    public function test_books_can_be_reordered(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $a = \App\Models\Book::query()->create(['title' => 'A', 'status' => 'reading', 'position' => 1]);
        $b = \App\Models\Book::query()->create(['title' => 'B', 'status' => 'reading', 'position' => 2]);
        \Livewire\Livewire::test(\App\Livewire\Read::class)->call('reorderBooks', [$b->id, $a->id]);
        $this->assertSame(1, $b->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
    }

    public function test_book_type_can_be_set_to_a_b_or_c(): void
    {
        $book = \App\Models\Book::query()->create(['title' => 'T', 'status' => 'reading', 'position' => 1]);
        $c = \Livewire\Livewire::test(\App\Livewire\Read::class);
        $c->call('setBookType', $book->id, 'B');
        $this->assertSame('B', $book->fresh()->type);
        $c->call('setBookType', $book->id, 'Z');
        $this->assertSame('B', $book->fresh()->type);
        $c->call('setBookType', $book->id, null);
        $this->assertNull($book->fresh()->type);
    }

    public function test_no_book_is_selected_on_first_load(): void
    {
        \App\Models\Book::query()->create(['title' => 'First', 'status' => 'reading', 'position' => 1]);
        \Livewire\Livewire::test(\App\Livewire\Read::class)->assertSet('selectedBookId', null);
    }
    public function test_new_book_is_added_at_the_top_without_changing_relative_order(): void
    {
        $a = Book::query()->create(['title' => 'Older A', 'status' => Book::STATUS_READING, 'position' => 2, 'kind' => Book::KIND_BOOK]);
        $b = Book::query()->create(['title' => 'Older B', 'status' => Book::STATUS_READING, 'position' => 5, 'kind' => Book::KIND_BOOK]);
        $finished = Book::query()->create(['title' => 'Done', 'status' => Book::STATUS_FINISHED, 'position' => 3, 'kind' => Book::KIND_BOOK]);
        $article = Book::query()->create(['title' => 'An article', 'status' => Book::STATUS_READING, 'position' => 2, 'kind' => Book::KIND_ARTICLE]);

        Livewire::test(Read::class)
            ->call('startAddBook')
            ->set('newBookTitle', 'Newest')
            ->call('addBook');

        $new = Book::query()->where('title', 'Newest')->first();
        $this->assertNotNull($new);
        $this->assertSame(2, $new->position);
        $this->assertSame(3, $a->fresh()->position);
        $this->assertSame(6, $b->fresh()->position);
        $this->assertSame(4, $finished->fresh()->position);
        $this->assertSame(2, $article->fresh()->position);

        $html = Livewire::test(Read::class)->html();
        $posNew = strpos($html, 'data-sort-item="'.$new->id.'"');
        $posA = strpos($html, 'data-sort-item="'.$a->id.'"');
        $posB = strpos($html, 'data-sort-item="'.$b->id.'"');
        $this->assertNotFalse($posNew);
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertLessThan($posA, $posNew);
        $this->assertLessThan($posB, $posA);
        $this->assertStringNotContainsString('data-sort-item="'.$finished->id.'"', $html);
    }

    public function test_first_book_gets_position_one(): void
    {
        Livewire::test(Read::class)
            ->call('startAddBook')
            ->set('newBookTitle', 'Only')
            ->call('addBook');

        $this->assertSame(1, Book::query()->where('title', 'Only')->value('position'));
    }
}
