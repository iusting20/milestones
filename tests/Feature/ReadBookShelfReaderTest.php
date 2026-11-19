<?php

namespace Tests\Feature;

use App\Livewire\Read;
use App\Models\Book;
use App\Models\BookHighlight;
use App\Models\ReadingSession;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReadBookShelfReaderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Minimal valid 1x1 PNG encoded as a data URL (simulating client-side PDF.js Page 1 render).
     */
    private function sampleCoverDataUrl(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    public function test_books_render_in_vertical_stack_with_typographic_fallback_and_drag_handle(): void
    {
        $a = Book::query()->create([
            'title' => 'Designing Data-Intensive Applications',
            'author' => 'Martin Kleppmann',
            'total_pages' => 820,
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);
        $b = Book::query()->create([
            'title' => 'Refactoring',
            'author' => 'Martin Fowler',
            'total_pages' => 240,
            'status' => Book::STATUS_READING,
            'position' => 2,
        ]);

        $component = Livewire::test(Read::class);
        $html = $component->html();

        $this->assertStringContainsString('flex w-full flex-col items-center gap-[1px]', $html);
        $this->assertStringContainsString('h-[88px] sm:h-[96px]', $html);
        $this->assertStringContainsString('h-[56px] sm:h-[62px]', $html);
        $this->assertStringContainsString('data-drag-handle', $html);
        $this->assertStringContainsString('x-on:click.stop', $html);
        $this->assertStringContainsString('Designing Data-Intensive Applications', $html);
        $this->assertStringContainsString('Martin Kleppmann', $html);
        $this->assertStringNotContainsString('<img src=', $html);

        $component->call('reorderBooks', [$b->id, $a->id]);

        $this->assertSame(1, $b->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
    }

    public function test_adding_book_with_pdf_and_auto_extracted_cover_saves_files_and_renders_reader(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->create('pragmatic.pdf', 120, 'application/pdf');

        $component = Livewire::test(Read::class)
            ->call('startAddBook')
            ->set('newBookTitle', 'The Pragmatic Programmer')
            ->set('newBookAuthor', 'Andrew Hunt')
            ->set('newBookPdf', $pdf)
            ->set('newBookGeneratedCover', $this->sampleCoverDataUrl())
            ->call('addBook');

        $book = Book::query()->where('title', 'The Pragmatic Programmer')->first();
        $this->assertNotNull($book);
        $this->assertNotNull($book->pdf_path);
        $this->assertNotNull($book->cover_path);
        Storage::disk('public')->assertExists($book->pdf_path);
        Storage::disk('public')->assertExists($book->cover_path);

        $html = $component->html();
        $this->assertStringContainsString('<img src="'.$book->coverUrl().'"', $html);
        $this->assertStringContainsString('src="'.$book->pdfUrl().'#page=1&toolbar=0&navpanes=0&view=FitH"', $html);
        $this->assertStringContainsString('Total time read', $html);
        $this->assertStringContainsString('Start session now', $html);
        $this->assertStringContainsString('+ Log session', $html);
        $this->assertStringContainsString('Close Reader', $html);
    }

    public function test_manual_cover_upload_overrides_auto_extracted_cover(): void
    {
        Storage::fake('public');

        $manualCover = UploadedFile::fake()->image('manual-cover.jpg', 200, 300);

        Livewire::test(Read::class)
            ->call('startAddBook')
            ->set('newBookTitle', 'Clean Architecture')
            ->set('newBookCover', $manualCover)
            ->set('newBookGeneratedCover', $this->sampleCoverDataUrl())
            ->call('addBook');

        $book = Book::query()->where('title', 'Clean Architecture')->first();
        $this->assertNotNull($book);
        $this->assertNotNull($book->cover_path);
        $this->assertStringEndsWith('.jpg', $book->cover_path);
        Storage::disk('public')->assertExists($book->cover_path);
    }

    public function test_selecting_book_opens_focused_reader_and_auto_starts_and_stops_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 14:00:00', Clock::timezone()));

        $book = Book::query()->create([
            'title' => 'Domain-Driven Design',
            'author' => 'Eric Evans',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        $component = Livewire::test(Read::class)
            ->assertSet('selectedBookId', null)
            ->assertSet('readerOpen', false)
            ->assertDontSee('Close Reader')
            ->assertSeeHtml('data-sort-item="'.$book->id.'"')
            ->call('selectBook', $book->id)
            ->assertSet('selectedBookId', $book->id)
            ->assertSet('readerOpen', true)
            ->assertDispatched('read-live-totals')
            ->assertDispatched('book-reader-opened')
            ->assertDontSeeHtml('data-sort-item="'.$book->id.'"')
            ->assertSee('Close Reader')
            ->assertSee('Stop session')
            ->assertSee('No PDF attached to this book yet.')
            ->assertSee('Attach PDF');

        $session = ReadingSession::query()->where('book_id', $book->id)->first();
        $this->assertNotNull($session);
        $this->assertNull($session->finished_at);

        $this->travelTo(Carbon::parse('2026-11-19 14:25:00', Clock::timezone()));

        $component->call('closeReader')
            ->assertSet('selectedBookId', null)
            ->assertSet('readerOpen', false)
            ->assertDispatched('read-live-totals')
            ->assertSeeHtml('data-sort-item="'.$book->id.'"')
            ->assertDontSee('Close Reader');

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertSame(25, $session->durationMinutes());
    }

    public function test_switching_books_auto_stops_previous_session_and_starts_next(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 15:00:00', Clock::timezone()));

        $first = Book::query()->create(['title' => 'First Book', 'status' => Book::STATUS_READING, 'position' => 1]);
        $second = Book::query()->create(['title' => 'Second Book', 'status' => Book::STATUS_READING, 'position' => 2]);

        $component = Livewire::test(Read::class)->call('selectBook', $first->id);

        $firstSession = ReadingSession::query()->where('book_id', $first->id)->first();
        $this->assertNotNull($firstSession);
        $this->assertNull($firstSession->finished_at);

        $this->travelTo(Carbon::parse('2026-11-19 15:15:00', Clock::timezone()));

        $component->call('selectBook', $second->id)
            ->assertSet('selectedBookId', $second->id)
            ->assertSet('readerOpen', true);

        $firstSession->refresh();
        $this->assertNotNull($firstSession->finished_at);
        $this->assertSame(15, $firstSession->durationMinutes());

        $secondSession = ReadingSession::query()->where('book_id', $second->id)->first();
        $this->assertNotNull($secondSession);
        $this->assertNull($secondSession->finished_at);
    }

    public function test_editing_book_attaches_pdf_and_cover_and_deleting_book_cleans_up_files(): void
    {
        Storage::fake('public');

        $book = Book::query()->create([
            'title' => 'Working Effectively with Legacy Code',
            'author' => 'Michael Feathers',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        $pdf = UploadedFile::fake()->create('legacy-code.pdf', 80, 'application/pdf');

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('openPdfUpload')
            ->assertSet('editingBookId', $book->id)
            ->set('editBookPdf', $pdf)
            ->set('editBookGeneratedCover', $this->sampleCoverDataUrl())
            ->call('saveEditBook');

        $book->refresh();
        $this->assertNotNull($book->pdf_path);
        $this->assertNotNull($book->cover_path);
        Storage::disk('public')->assertExists($book->pdf_path);
        Storage::disk('public')->assertExists($book->cover_path);

        $pdfPath = $book->pdf_path;
        $coverPath = $book->cover_path;

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('deleteBook', $book->id);

        Storage::disk('public')->assertMissing($pdfPath);
        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_publisher_marks_match_actual_publisher_and_live_badge_is_removed(): void
    {
        $penguin = Book::query()->create([
            'title' => 'Penguin Book',
            'author' => 'Author A',
            'publisher' => 'Penguin Classics',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);
        $oxford = Book::query()->create([
            'title' => 'Oxford Book',
            'author' => 'Author B',
            'publisher' => "Oxford World's Classics",
            'status' => Book::STATUS_READING,
            'position' => 2,
        ]);
        $unbranded = Book::query()->create([
            'title' => 'Indie Book',
            'author' => 'Author C',
            'status' => Book::STATUS_READING,
            'position' => 3,
        ]);

        ReadingSession::query()->create([
            'book_id' => $penguin->id,
            'started_at' => now()->subMinutes(5),
            'finished_at' => null,
        ]);

        $html = Livewire::test(Read::class)->html();

        $this->assertStringContainsString('aria-label="Penguin Classics"', $html);
        $this->assertStringContainsString('OXFORD', $html);
        $this->assertSame(1, substr_count($html, 'aria-label="Penguin Classics"'));
        $this->assertStringNotContainsString('● live', $html);
        $this->assertStringContainsString('data-silk-ribbon', $html);
    }

    public function test_current_page_bookmark_persists_caps_at_total_pages_and_resumes_pdf(): void
    {
        Storage::fake('public');

        $book = Book::query()->create([
            'title' => 'Bookmarked Classic',
            'author' => 'Seneca',
            'total_pages' => 120,
            'pdf_path' => 'books/pdfs/sample.pdf',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->set('readerCurrentPage', '42')
            ->call('saveCurrentPage')
            ->assertSeeHtml('#page=42&toolbar=0&navpanes=0&view=FitH')
            ->set('showPdfToolbar', true)
            ->assertSeeHtml('#page=42&toolbar=1&navpanes=0&view=FitH')
            ->set('readerCurrentPage', '999')
            ->call('saveCurrentPage')
            ->assertSet('readerCurrentPage', '120');

        $this->assertSame(120, $book->fresh()->current_page);
    }

    public function test_stop_active_beacon_endpoint_stops_running_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 16:00:00', Clock::timezone()));

        $book = Book::query()->create([
            'title' => 'Beacon Book',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        $session = ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-11-19 15:40:00', Clock::timezone())->utc(),
            'finished_at' => null,
        ]);

        $this->post(route('read.sessions.stop-active'), ['book_id' => $book->id])
            ->assertNoContent();

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertSame(20, $session->durationMinutes());
    }

    public function test_dark_light_toggle_read_mode_and_persistent_highlights_work(): void
    {
        Storage::fake('public');

        $book = Book::query()->create([
            'title' => 'Meditations',
            'author' => 'Marcus Aurelius',
            'total_pages' => 91,
            'pdf_path' => 'books/pdfs/marcus-aurelius-meditations.pdf',
            'status' => Book::STATUS_READING,
            'position' => 1,
        ]);

        $component = Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->assertSeeHtml('data-theme-toggle')
            ->assertSeeHtml('data-read-mode-toggle')
            ->assertSeeHtml('data-highlights-toggle')
            ->call('addHighlight', 'The impediment to action advances action.', 14, 'yellow', 'Core Stoic principle')
            ->set('showHighlights', true)
            ->assertSee('Highlights (1)')
            ->assertSee('The impediment to action advances action.')
            ->assertSee('Core Stoic principle')
            ->assertSee('p. 14');

        $highlight = BookHighlight::query()->where('book_id', $book->id)->first();
        $this->assertNotNull($highlight);
        $this->assertSame(14, $highlight->page_number);
        $this->assertSame('yellow', $highlight->color);
        $this->assertSame('Core Stoic principle', $highlight->note);

        $component->call('jumpToPage', 14)
            ->assertDispatched('reader-jump-page', page: 14)
            ->assertSet('readerCurrentPage', '14');
        $this->assertSame(14, $book->fresh()->current_page);

        $component->call('deleteHighlight', $highlight->id)
            ->assertDontSee('The impediment to action advances action.');

        $this->assertDatabaseMissing('book_highlights', ['id' => $highlight->id]);
    }
}
