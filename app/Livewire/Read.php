<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\ReadingSession;
use App\Support\Clock;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Read')]
class Read extends Component
{
    public ?int $selectedBookId = null;

    public bool $editMode = false;

    public bool $addingBook = false;

    public string $newBookTitle = '';

    public string $newBookAuthor = '';

    public string $newBookTotalPages = '';

    public ?int $editingBookId = null;

    public string $editBookTitle = '';

    public string $editBookAuthor = '';

    public string $editBookTotalPages = '';

    public string $editBookStatus = Book::STATUS_READING;

    public bool $addingSession = false;

    public string $newSessionStartedAt = '';

    public string $newSessionFinishedAt = '';

    public string $newSessionNote = '';

    public string $newSessionPagesRead = '';

    public ?int $editingSessionId = null;

    public string $editSessionStartedAt = '';

    public string $editSessionFinishedAt = '';

    public string $editSessionNote = '';

    public string $editSessionPagesRead = '';

    public function mount(): void
    {
        $first = Book::query()->orderBy('position')->orderBy('id')->value('id');
        $this->selectedBookId = $first ? (int) $first : null;
    }

    public function selectBook(int $id): void
    {
        $this->findBook($id);
        $this->selectedBookId = $id;
        $this->cancelForms();
    }

    public function startAddBook(): void
    {
        $this->cancelForms();
        $this->addingBook = true;
        $this->newBookTitle = '';
        $this->newBookAuthor = '';
        $this->newBookTotalPages = '';
    }

    public function addBook(): void
    {
        $title = trim($this->newBookTitle);
        if ($title === '') {
            $this->addError('newBookTitle', 'Title is required.');

            return;
        }

        $pages = $this->parseOptionalPages($this->newBookTotalPages, 'newBookTotalPages');
        if ($this->getErrorBag()->has('newBookTotalPages')) {
            return;
        }

        $max = (int) Book::query()->max('position');

        $book = Book::query()->create([
            'title' => $title,
            'author' => trim($this->newBookAuthor) !== '' ? trim($this->newBookAuthor) : null,
            'total_pages' => $pages,
            'status' => Book::STATUS_READING,
            'position' => $max + 1,
        ]);

        $this->addingBook = false;
        $this->newBookTitle = '';
        $this->newBookAuthor = '';
        $this->newBookTotalPages = '';
        $this->selectedBookId = $book->id;
    }

    public function startEditBook(int $id): void
    {
        $book = $this->findBook($id);
        $this->cancelForms();
        $this->editingBookId = $book->id;
        $this->editBookTitle = $book->title;
        $this->editBookAuthor = $book->author ?? '';
        $this->editBookTotalPages = $book->total_pages !== null ? (string) $book->total_pages : '';
        $this->editBookStatus = $book->status;
    }

    public function saveEditBook(): void
    {
        if (! $this->editingBookId) {
            return;
        }

        $book = $this->findBook($this->editingBookId);
        $title = trim($this->editBookTitle);
        if ($title === '') {
            $this->addError('editBookTitle', 'Title is required.');

            return;
        }

        if (! in_array($this->editBookStatus, [Book::STATUS_READING, Book::STATUS_FINISHED], true)) {
            $this->addError('editBookStatus', 'Invalid status.');

            return;
        }

        $pages = $this->parseOptionalPages($this->editBookTotalPages, 'editBookTotalPages');
        if ($this->getErrorBag()->has('editBookTotalPages')) {
            return;
        }

        $book->update([
            'title' => $title,
            'author' => trim($this->editBookAuthor) !== '' ? trim($this->editBookAuthor) : null,
            'total_pages' => $pages,
            'status' => $this->editBookStatus,
        ]);

        $this->editingBookId = null;
    }

    public function markBookFinished(int $id): void
    {
        $this->findBook($id)->update(['status' => Book::STATUS_FINISHED]);
    }

    public function markBookReading(int $id): void
    {
        $this->findBook($id)->update(['status' => Book::STATUS_READING]);
    }

    public function deleteBook(int $id): void
    {
        $book = $this->findBook($id);
        $wasSelected = $this->selectedBookId === $book->id;
        $book->delete();

        if ($wasSelected) {
            $this->selectedBookId = Book::query()->orderBy('position')->orderBy('id')->value('id');
            $this->selectedBookId = $this->selectedBookId ? (int) $this->selectedBookId : null;
        }

        $this->cancelForms();
    }

    public function startAddSession(): void
    {
        if (! $this->selectedBookId) {
            return;
        }

        $this->findBook($this->selectedBookId);
        $this->cancelForms();
        $this->addingSession = true;

        $now = Clock::now()->seconds(0);
        $this->newSessionStartedAt = $now->copy()->subMinutes(30)->format('Y-m-d\TH:i');
        $this->newSessionFinishedAt = $now->format('Y-m-d\TH:i');
        $this->newSessionNote = '';
        $this->newSessionPagesRead = '';
    }

    public function addSession(): void
    {
        if (! $this->selectedBookId) {
            return;
        }

        $book = $this->findBook($this->selectedBookId);
        $started = $this->parseDateTimeLocal($this->newSessionStartedAt, 'newSessionStartedAt');
        $finished = $this->parseDateTimeLocal($this->newSessionFinishedAt, 'newSessionFinishedAt');

        if (! $started || ! $finished) {
            return;
        }

        if ($finished->lte($started)) {
            $this->addError('newSessionFinishedAt', 'Finish time must be after start time.');

            return;
        }

        $pages = $this->parseOptionalPages($this->newSessionPagesRead, 'newSessionPagesRead');
        if ($this->getErrorBag()->has('newSessionPagesRead')) {
            return;
        }

        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => $started->clone()->utc(),
            'finished_at' => $finished->clone()->utc(),
            'note' => trim($this->newSessionNote) !== '' ? trim($this->newSessionNote) : null,
            'pages_read' => $pages,
        ]);

        $this->addingSession = false;
        $this->resetSessionForm();
    }

    public function startSessionNow(): void
    {
        if (! $this->selectedBookId) {
            return;
        }

        $book = $this->findBook($this->selectedBookId);

        if ($this->runningSessionFor($book->id)) {
            return;
        }

        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Clock::now()->seconds(0)->utc(),
            'finished_at' => null,
        ]);
    }

    public function stopSession(int $id): void
    {
        $session = $this->findSession($id);

        if (! $session->isRunning()) {
            return;
        }

        $finished = Clock::now()->seconds(0)->utc();
        if ($finished->lte($session->started_at)) {
            $finished = $session->started_at->clone()->addMinute();
        }

        $session->update(['finished_at' => $finished]);
    }

    public function startEditSession(int $id): void
    {
        $session = $this->findSession($id);
        $this->cancelForms();
        $this->editingSessionId = $session->id;
        $tz = Clock::timezone();
        $this->editSessionStartedAt = $session->started_at->timezone($tz)->format('Y-m-d\TH:i');
        $this->editSessionFinishedAt = $session->finished_at
            ? $session->finished_at->timezone($tz)->format('Y-m-d\TH:i')
            : '';
        $this->editSessionNote = $session->note ?? '';
        $this->editSessionPagesRead = $session->pages_read !== null ? (string) $session->pages_read : '';
    }

    public function saveEditSession(): void
    {
        if (! $this->editingSessionId) {
            return;
        }

        $session = $this->findSession($this->editingSessionId);
        $started = $this->parseDateTimeLocal($this->editSessionStartedAt, 'editSessionStartedAt');

        if (! $started) {
            return;
        }

        $finished = null;
        if (trim($this->editSessionFinishedAt) !== '') {
            $finished = $this->parseDateTimeLocal($this->editSessionFinishedAt, 'editSessionFinishedAt');
            if (! $finished) {
                return;
            }

            if ($finished->lte($started)) {
                $this->addError('editSessionFinishedAt', 'Finish time must be after start time.');

                return;
            }
        }

        $pages = $this->parseOptionalPages($this->editSessionPagesRead, 'editSessionPagesRead');
        if ($this->getErrorBag()->has('editSessionPagesRead')) {
            return;
        }

        $session->update([
            'started_at' => $started->clone()->utc(),
            'finished_at' => $finished?->clone()->utc(),
            'note' => trim($this->editSessionNote) !== '' ? trim($this->editSessionNote) : null,
            'pages_read' => $pages,
        ]);

        $this->editingSessionId = null;
    }

    public function deleteSession(int $id): void
    {
        $this->findSession($id)->delete();
        $this->cancelForms();
    }

    public function cancelForms(): void
    {
        $this->resetErrorBag();
        $this->addingBook = false;
        $this->newBookTitle = '';
        $this->newBookAuthor = '';
        $this->newBookTotalPages = '';
        $this->editingBookId = null;
        $this->addingSession = false;
        $this->editingSessionId = null;
        $this->resetSessionForm();
    }

    public function render()
    {
        $books = Book::query()
            ->withCount('readingSessions')
            ->with(['latestSession', 'readingSessions' => fn ($q) => $q->whereNull('finished_at')])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $selected = $books->firstWhere('id', $this->selectedBookId);

        $sessions = collect();
        $bookTotals = [
            'sessionCount' => 0,
            'totalMinutes' => 0,
            'totalDuration' => '0m',
            'lastRead' => null,
            'pagesRead' => 0,
            'weekDays' => '',
        ];

        $weekStart = Clock::now()->startOfWeek(Carbon::MONDAY)->utc();
        $todayStart = Clock::now()->startOfDay()->utc();
        $nowUtc = Clock::now()->utc();
        $epochStart = Carbon::createFromTimestampUTC(0);

        if ($selected) {
            $sessions = ReadingSession::query()
                ->where('book_id', $selected->id)
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->get();

            $completed = $sessions->filter(fn (ReadingSession $s) => ! $s->isRunning());
            $totalMinutes = $this->overlapMinutes($epochStart, $nowUtc, $selected->id);
            $bookTotals = [
                'sessionCount' => $sessions->count(),
                'totalMinutes' => $totalMinutes,
                'totalDuration' => $this->formatDuration($totalMinutes),
                'lastRead' => $sessions->first()?->started_at,
                'pagesRead' => (int) $completed->sum(fn (ReadingSession $s) => (int) ($s->pages_read ?? 0)),
                'weekDays' => $this->weekDaysRead($sessions, $weekStart),
            ];
        }

        $pageTotals = [
            'weekMinutes' => $this->overlapMinutes($weekStart, $nowUtc),
            'todayMinutes' => $this->overlapMinutes($todayStart, $nowUtc),
        ];
        $pageTotals['weekDuration'] = $this->formatDuration($pageTotals['weekMinutes']);
        $pageTotals['todayDuration'] = $this->formatDuration($pageTotals['todayMinutes']);

        $liveTotals = [
            'todayBase' => $this->overlapMinutes($todayStart, $nowUtc, finishedOnly: true),
            'weekBase' => $this->overlapMinutes($weekStart, $nowUtc, finishedOnly: true),
            'bookBase' => $selected
                ? $this->overlapMinutes($epochStart, $nowUtc, $selected->id, finishedOnly: true)
                : 0,
            'todayRuns' => $this->runningClipStartsMs($todayStart),
            'weekRuns' => $this->runningClipStartsMs($weekStart),
            'bookRuns' => $selected
                ? $this->runningClipStartsMs($epochStart, $selected->id)
                : [],
        ];

        return view('livewire.read', [
            'books' => $books,
            'selected' => $selected,
            'sessions' => $sessions,
            'bookTotals' => $bookTotals,
            'pageTotals' => $pageTotals,
            'liveTotals' => $liveTotals,
            'runningSession' => $selected ? $this->runningSessionFor($selected->id) : null,
            'timezone' => Clock::timezone(),
        ])->layout('layouts.app');
    }

    /**
     * Weekdays (Mon..Sun order) of this week on which a session was logged, e.g. "Mon, Wed, Sat".
     */
    private function weekDaysRead($sessions, Carbon $weekStart): string
    {
        $tz = Clock::timezone();

        return $sessions
            ->filter(fn (ReadingSession $s) => $s->started_at->greaterThanOrEqualTo($weekStart))
            ->map(fn (ReadingSession $s) => $s->started_at->copy()->timezone($tz))
            ->sortBy(fn (Carbon $d) => $d->dayOfWeekIso)
            ->map(fn (Carbon $d) => $d->format('D'))
            ->unique()
            ->implode(', ');
    }

    /**
     * Relative "last read" label by calendar day: today, yesterday, N days ago, last week, N weeks ago.
     */
    public static function relativeDay(?Carbon $when): string
    {
        if ($when === null) {
            return '—';
        }
        $tz = Clock::timezone();
        $days = (int) $when->copy()->timezone($tz)->startOfDay()
            ->diffInDays(Clock::now()->timezone($tz)->startOfDay(), false);
        if ($days <= 0) {
            return 'today';
        }
        if ($days === 1) {
            return 'yesterday';
        }
        if ($days < 7) {
            return $days.' days ago';
        }
        if ($days < 14) {
            return 'last week';
        }

        return intdiv($days, 7).' weeks ago';
    }

    public function reorderBooks(array $ids): void
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        if ($ids === [] || Book::query()->whereIn('id', $ids)->count() !== count($ids)) {
            return;
        }
        foreach ($ids as $i => $id) {
            Book::query()->whereKey($id)->update(['position' => $i + 1]);
        }
    }

    private function findBook(int $id): Book
    {
        return Book::query()->whereKey($id)->firstOrFail();
    }

    private function findSession(int $id): ReadingSession
    {
        return ReadingSession::query()->whereKey($id)->firstOrFail();
    }

    private function runningSessionFor(int $bookId): ?ReadingSession
    {
        return ReadingSession::query()
            ->where('book_id', $bookId)
            ->whereNull('finished_at')
            ->orderByDesc('started_at')
            ->first();
    }

    private function parseDateTimeLocal(string $value, string $field): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            $this->addError($field, 'Required.');

            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d\TH:i', $value, Clock::timezone());
        } catch (\Throwable) {
            $this->addError($field, 'Use a valid date and time.');

            return null;
        }
    }

    private function parseOptionalPages(string $value, string $field): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! ctype_digit($value) || (int) $value < 0) {
            $this->addError($field, 'Pages must be a non-negative number.');

            return null;
        }

        return (int) $value;
    }

    private function resetSessionForm(): void
    {
        $this->newSessionStartedAt = '';
        $this->newSessionFinishedAt = '';
        $this->newSessionNote = '';
        $this->newSessionPagesRead = '';
        $this->editSessionStartedAt = '';
        $this->editSessionFinishedAt = '';
        $this->editSessionNote = '';
        $this->editSessionPagesRead = '';
    }

    /**
     * Minutes of reading overlapping [fromUtc, toUtc], optionally for one book.
     * Running sessions count through $toUtc. Finished-only skips open sessions.
     */
    private function overlapMinutes(
        Carbon $fromUtc,
        Carbon $toUtc,
        ?int $bookId = null,
        bool $finishedOnly = false,
    ): int {
        if ($toUtc->lte($fromUtc)) {
            return 0;
        }

        $query = ReadingSession::query()
            ->where('started_at', '<', $toUtc)
            ->where(function ($q) use ($fromUtc) {
                $q->whereNull('finished_at')->orWhere('finished_at', '>', $fromUtc);
            });

        if ($bookId !== null) {
            $query->where('book_id', $bookId);
        }

        if ($finishedOnly) {
            $query->whereNotNull('finished_at');
        }

        $total = 0;

        foreach ($query->get() as $session) {
            $start = $session->started_at->greaterThan($fromUtc) ? $session->started_at->copy() : $fromUtc->copy();
            $end = $session->finished_at?->copy() ?? $toUtc->copy();
            if ($end->greaterThan($toUtc)) {
                $end = $toUtc->copy();
            }

            if ($end->greaterThan($start)) {
                $total += (int) $start->diffInMinutes($end);
            }
        }

        return $total;
    }

    /**
     * @return list<int>
     */
    private function runningClipStartsMs(Carbon $windowStartUtc, ?int $bookId = null): array
    {
        $query = ReadingSession::query()->whereNull('finished_at');

        if ($bookId !== null) {
            $query->where('book_id', $bookId);
        }

        $starts = [];

        foreach ($query->get() as $session) {
            $clipStart = $session->started_at->greaterThan($windowStartUtc)
                ? $session->started_at
                : $windowStartUtc;

            $starts[] = (int) ($clipStart->getTimestampMs());
        }

        return $starts;
    }

    public function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return $remaining.'m';
        }

        if ($remaining === 0) {
            return $hours.'h';
        }

        return $hours.'h '.$remaining.'m';
    }
}
