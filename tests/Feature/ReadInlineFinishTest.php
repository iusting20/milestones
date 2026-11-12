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

class ReadInlineFinishTest extends TestCase
{
    use RefreshDatabase;

    private function book(): Book
    {
        return Book::query()->create(['title' => 'Inline', 'status' => Book::STATUS_READING, 'position' => 1]);
    }

    private function makeSession(Book $book, string $start, ?string $finish): ReadingSession
    {
        return ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse($start, Clock::timezone())->utc(),
            'finished_at' => $finish ? Carbon::parse($finish, Clock::timezone())->utc() : null,
        ]);
    }

    private function localFinish(ReadingSession $session): string
    {
        return $session->fresh()->finished_at->timezone(Clock::timezone())->format('Y-m-d H:i');
    }

    public function test_finish_time_is_updated_inline_and_totals_refresh(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', Clock::timezone()));
        $book = $this->book();
        $session = $this->makeSession($book, '2026-11-12 10:00', '2026-11-12 10:20');

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('updateSessionFinish', $session->id, '10:45')
            ->assertReturned(fn ($r) => $r['ok'] === true && $r['finish'] === '10:45' && $r['duration'] === '45m')
            ->assertDispatched('read-live-totals', fn ($name, $params) => $params['totals']['todayBase'] === 45
                && $params['totals']['weekBase'] === 45
                && $params['totals']['bookBase'] === 45)
            ->assertSee('45m')
            ->assertSee('10:45');

        $this->assertSame('2026-11-12 10:45', $this->localFinish($session));
        $this->assertSame(45, $session->fresh()->durationMinutes());
    }

    public function test_finish_not_after_start_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', Clock::timezone()));
        $book = $this->book();
        $session = $this->makeSession($book, '2026-11-12 10:00', '2026-11-12 10:20');

        foreach (['10:00', '09:30', '08:00'] as $time) {
            Livewire::test(Read::class, ['selectedBookId' => $book->id])
                ->call('updateSessionFinish', $session->id, $time)
                ->assertReturned(fn ($r) => $r['ok'] === false && str_contains($r['error'], 'after start'))
                ->assertNotDispatched('read-live-totals');
        }

        $this->assertSame('2026-11-12 10:20', $this->localFinish($session));
    }

    public function test_invalid_time_format_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', Clock::timezone()));
        $book = $this->book();
        $session = $this->makeSession($book, '2026-11-12 10:00', '2026-11-12 10:20');

        foreach (['', 'abc', '25:00', '10:60', '10:30:00'] as $time) {
            Livewire::test(Read::class, ['selectedBookId' => $book->id])
                ->call('updateSessionFinish', $session->id, $time)
                ->assertReturned(fn ($r) => $r['ok'] === false);
        }

        $this->assertSame('2026-11-12 10:20', $this->localFinish($session));
    }

    public function test_overnight_session_rolls_finish_to_next_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', Clock::timezone()));
        $book = $this->book();
        $session = $this->makeSession($book, '2026-11-11 23:30', '2026-11-11 23:50');

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('updateSessionFinish', $session->id, '00:15')
            ->assertReturned(fn ($r) => $r['ok'] === true && $r['duration'] === '45m');

        $this->assertSame('2026-11-12 00:15', $this->localFinish($session));

        // Already past midnight: editing again keeps it on the next day.
        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('updateSessionFinish', $session->id, '01:00')
            ->assertReturned(fn ($r) => $r['ok'] === true && $r['duration'] === '1h 30m');

        $this->assertSame('2026-11-12 01:00', $this->localFinish($session));

        // Back before midnight: same day as the start again.
        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('updateSessionFinish', $session->id, '23:55');

        $this->assertSame('2026-11-11 23:55', $this->localFinish($session));
    }

    public function test_finish_in_the_future_is_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 10:30:00', Clock::timezone()));
        $book = $this->book();
        $session = $this->makeSession($book, '2026-11-12 10:00', '2026-11-12 10:20');

        Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('updateSessionFinish', $session->id, '11:00')
            ->assertReturned(fn ($r) => $r['ok'] === false && str_contains($r['error'], 'future'));

        $this->assertSame('2026-11-12 10:20', $this->localFinish($session));
    }

    public function test_running_session_cannot_be_edited_inline(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', Clock::timezone()));
        $book = $this->book();
        $running = $this->makeSession($book, '2026-11-12 11:00', null);

        $component = Livewire::test(Read::class, ['selectedBookId' => $book->id])
            ->call('updateSessionFinish', $running->id, '11:30')
            ->assertReturned(fn ($r) => $r['ok'] === false);

        $this->assertNull($running->fresh()->finished_at);
        $this->assertStringNotContainsString('inlineFinish('.$running->id.',', $component->html());
    }

    public function test_finished_sessions_render_the_inline_editor(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', Clock::timezone()));
        $book = $this->book();
        $done = $this->makeSession($book, '2026-11-12 09:00', '2026-11-12 09:20');
        $this->makeSession($book, '2026-11-12 11:00', null);

        $html = Livewire::test(Read::class, ['selectedBookId' => $book->id])->html();

        $this->assertSame(1, substr_count($html, 'x-data="inlineFinish('));
        $this->assertStringContainsString('inlineFinish('.$done->id.',', $html);
        $this->assertStringContainsString('Double-click to edit finish time', $html);
    }

    public function test_unknown_session_returns_error(): void
    {
        Livewire::test(Read::class)
            ->call('updateSessionFinish', 999999, '10:00')
            ->assertReturned(fn ($r) => $r['ok'] === false);
    }

    public function test_livewire_endpoint_requires_login(): void
    {
        $this->authenticate = false;
        $this->flushSession();

        $book = $this->book();
        $session = $this->makeSession($book, '2026-11-12 10:00', '2026-11-12 10:20');

        $this->withHeaders(['X-Livewire' => '1'])
            ->postJson(route('default-livewire.update'), ['_token' => 'x', 'components' => []])
            ->assertRedirect(route('login'));

        $this->assertSame('2026-11-12 10:20', $this->localFinish($session));
    }
}
