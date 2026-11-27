<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\BookFeedbackEntry;
use App\Models\BookFeedbackIdea;
use App\Models\ReadingSession;
use App\Support\Clock;
use App\Support\Onboarding;
use Carbon\Carbon;
use Livewire\Component;

class Read extends Component
{
    public string $kind = Book::KIND_BOOK;

    public ?int $selectedBookId = null;

    public bool $showFinished = false;

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

    public bool $feedbackOpen = false;

    public ?int $feedbackBookId = null;

    public string $feedbackBookTitle = '';

    public string $feedbackDescription = '';

    /** @var list<array{id: int|null, body: string}> */
    public array $feedbackLikes = [];

    /** @var list<array{id: int|null, body: string}> */
    public array $feedbackDislikes = [];

    public bool $feedbackDirty = false;

    /** @var list<array{id: int|null, key: string, title: string, ideas: list<array{id: int|null, body: string}>}> */
    public array $feedbackChapters = [];

    public string $feedbackChapterKey = 'general';

    public int $feedbackNewSectionSeq = 0;

    public bool $feedbackIdeasOnly = false;

    public function mount(?int $selectedBookId = null): void
    {
        if (request()->routeIs('read.articles')) {
            $this->kind = Book::KIND_ARTICLE;
        } elseif (! in_array($this->kind, [Book::KIND_BOOK, Book::KIND_ARTICLE], true)) {
            $this->kind = Book::KIND_BOOK;
        }

        // No book is selected on first load / refresh.
        $this->selectedBookId = $selectedBookId;
    }

    public function isArticles(): bool
    {
        return $this->kind === Book::KIND_ARTICLE;
    }

    public function selectBook(int $id): void
    {
        $book = $this->findBook($id);
        if ($book->isDelimiter()) {
            return;
        }

        $this->selectedBookId = $id;
        $this->cancelForms();
        $this->closeFeedback();
    }

    public function addDelimiter(): void
    {
        $open = $this->openItems();
        if ($open->isEmpty()) {
            return;
        }

        if ($open->count() === 1) {
            $insertAt = (int) $open->first()->position;
        } else {
            $selectedIndex = $open->values()->search(
                fn (Book $book) => $book->id === $this->selectedBookId && ! $book->isDelimiter()
            );
            if ($selectedIndex !== false && $selectedIndex < $open->count() - 1) {
                $insertAt = (int) $open[$selectedIndex]->position + 1;
            } else {
                $insertAt = (int) $open->last()->position;
            }
        }

        $this->catalog()->where('position', '>=', $insertAt)->increment('position');
        $this->createDelimiter($insertAt);

        $this->addingBook = false;
        $this->newBookTitle = '';
        $this->newBookAuthor = '';
        $this->newBookTotalPages = '';
        $this->pruneTrailingDelimiters();
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

        $pages = null;
        if (! $this->isArticles()) {
            $pages = $this->parseOptionalPages($this->newBookTotalPages, 'newBookTotalPages');
            if ($this->getErrorBag()->has('newBookTotalPages')) {
                return;
            }
        }

        $min = $this->catalog()->min('position');
        if ($min === null) {
            $position = 1;
        } else {
            $position = (int) $min;
            $this->catalog()->increment('position');
        }

        $book = $this->catalog()->create([
            'title' => $title,
            'author' => trim($this->newBookAuthor) !== '' ? trim($this->newBookAuthor) : null,
            'total_pages' => $this->isArticles() ? null : $pages,
            'status' => Book::STATUS_READING,
            'position' => $position,
            'kind' => $this->kind,
        ]);

        Onboarding::complete($this->isArticles() ? 'read.article' : 'read.book');

        $this->addingBook = false;
        $this->newBookTitle = '';
        $this->newBookAuthor = '';
        $this->newBookTotalPages = '';
        $this->selectedBookId = $book->id;
        $this->pruneTrailingDelimiters();
    }

    public function startEditBook(int $id): void
    {
        $book = $this->findBook($id);
        if ($book->isDelimiter()) {
            return;
        }

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

        $pages = $book->total_pages;
        if (! $this->isArticles()) {
            $pages = $this->parseOptionalPages($this->editBookTotalPages, 'editBookTotalPages');
            if ($this->getErrorBag()->has('editBookTotalPages')) {
                return;
            }
        }

        $book->update([
            'title' => $title,
            'author' => trim($this->editBookAuthor) !== '' ? trim($this->editBookAuthor) : null,
            'total_pages' => $pages,
            'status' => $this->editBookStatus,
        ]);

        $this->editingBookId = null;
        $this->pruneTrailingDelimiters();
    }

    public function markBookFinished(int $id): void
    {
        $book = $this->findBook($id);
        if ($book->isDelimiter()) {
            return;
        }

        $book->update(['status' => Book::STATUS_FINISHED]);
        $this->pruneTrailingDelimiters();
    }

    public function markBookReading(int $id): void
    {
        $book = $this->findBook($id);
        if ($book->isDelimiter()) {
            return;
        }

        $book->update(['status' => Book::STATUS_READING]);
        $this->closeFeedback();
        $this->pruneTrailingDelimiters();
    }

    public function markStartedToday(int $id): void
    {
        $book = $this->findBook($id);
        if ($book->isDelimiter()) {
            return;
        }

        $book->update(['started_on' => Clock::todayString()]);
    }

    public function clearStartedToday(int $id): void
    {
        $book = $this->findBook($id);
        if ($book->isDelimiter() || ! $book->isStartedToday()) {
            return;
        }

        $book->update(['started_on' => null]);
    }

    public function deleteBook(int $id): void
    {
        $book = $this->findBook($id);
        $wasSelected = $this->selectedBookId === $book->id;
        $book->delete();

        if ($wasSelected) {
            $this->selectedBookId = $this->catalog()
                ->where('is_delimiter', false)
                ->orderBy('position')
                ->orderBy('id')
                ->value('id');
            $this->selectedBookId = $this->selectedBookId ? (int) $this->selectedBookId : null;
        }

        $this->cancelForms();
        $this->pruneTrailingDelimiters();
    }

    public function startAddSession(): void
    {
        if (! $this->selectedBookId) {
            return;
        }

        $book = $this->findBook($this->selectedBookId);
        if ($book->isDelimiter()) {
            return;
        }

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
        if ($book->isDelimiter()) {
            return;
        }

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
        if ($book->isDelimiter()) {
            return;
        }

        if ($this->runningSessionFor($book->id)) {
            return;
        }

        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Clock::now()->seconds(0)->utc(),
            'finished_at' => null,
        ]);

        Onboarding::complete('read.start');
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
        Onboarding::complete('read.stop');
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

    /**
     * Inline edit of a finished session's finish time (double-click / double-tap "→ HH:MM" on /read).
     *
     * $time is "HH:MM" in the display timezone (Europe/Bucharest). The finish keeps the session's
     * start date. If that lands at or before the start, it is read as "after midnight" and moved to
     * the next day, but only when the session then lasts at most 12 hours (e.g. 23:30 → 00:15 = 45m).
     * Anything else at or before the start is rejected. Running sessions cannot be edited here,
     * and a finish in the future is rejected.
     *
     * @return array{ok: bool, error?: string, finish?: string, duration?: string}
     */
    public function updateSessionFinish(int $id, string $time): array
    {
        $session = ReadingSession::query()
            ->whereKey($id)
            ->whereHas('book', fn ($q) => $q->where('kind', $this->kind))
            ->first();
        if (! $session) {
            return ['ok' => false, 'error' => 'Session not found.'];
        }

        if ($session->isRunning()) {
            return ['ok' => false, 'error' => 'Stop the running session first.'];
        }

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($time), $m)) {
            return ['ok' => false, 'error' => 'Use HH:MM.'];
        }

        $finished = self::resolveInlineFinish($session->started_at, (int) $m[1], (int) $m[2]);
        $startLocal = $session->started_at->copy()->timezone(Clock::timezone());

        if ($finished === null) {
            return ['ok' => false, 'error' => 'Finish must be after start ('.$startLocal->format('H:i').').'];
        }

        if ($finished->greaterThan(Carbon::now())) {
            return ['ok' => false, 'error' => 'Finish can\'t be in the future.'];
        }

        $session->update(['finished_at' => $finished->copy()->utc()]);

        $this->dispatch('read-live-totals', totals: $this->liveTotals());

        return [
            'ok' => true,
            'finish' => $finished->copy()->timezone(Clock::timezone())->format('H:i'),
            'duration' => $this->formatDuration($session->durationMinutes()),
        ];
    }

    /**
     * Finish moment for an inline "HH:MM" edit, or null when it can't be after the start.
     * Same calendar day as the start (display timezone); rolls to the next day only for an
     * overnight session of at most 12 hours.
     */
    public static function resolveInlineFinish(Carbon $startedAt, int $hour, int $minute): ?Carbon
    {
        $start = $startedAt->copy()->timezone(Clock::timezone());
        $candidate = $start->copy()->setTime($hour, $minute, 0);

        if ($candidate->greaterThan($start)) {
            return $candidate;
        }

        $nextDay = $start->copy()->addDay()->setTime($hour, $minute, 0);
        if ($nextDay->greaterThan($start) && $start->diffInMinutes($nextDay) <= 12 * 60) {
            return $nextDay;
        }

        return null;
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

    public function openFeedback(int $id): void
    {
        $book = $this->findBook($id);
        $ideasOnly = ! $book->isFinished();
        if ($ideasOnly && $book->isArticle()) {
            return;
        }

        $this->feedbackOpen = true;
        $this->feedbackIdeasOnly = $ideasOnly;
        $this->feedbackBookId = $book->id;
        $this->feedbackBookTitle = $book->title;
        $this->feedbackDescription = (string) ($book->feedback?->description ?? '');
        $this->feedbackLikes = $this->feedbackEntryPayloads($book, BookFeedbackEntry::KIND_LIKE);
        $this->feedbackDislikes = $this->feedbackEntryPayloads($book, BookFeedbackEntry::KIND_DISLIKE);
        $this->feedbackChapters = $this->feedbackChapterPayloads($book);
        $this->feedbackChapterKey = 'general';
        $this->feedbackNewSectionSeq = 0;
        $this->feedbackDirty = false;
    }

    public function closeFeedback(): void
    {
        $this->feedbackOpen = false;
        $this->feedbackIdeasOnly = false;
        $this->feedbackBookId = null;
        $this->feedbackBookTitle = '';
        $this->feedbackDescription = '';
        $this->feedbackLikes = [];
        $this->feedbackDislikes = [];
        $this->feedbackChapters = [];
        $this->feedbackChapterKey = 'general';
        $this->feedbackNewSectionSeq = 0;
        $this->feedbackDirty = false;
    }

    public function addFeedbackEntry(string $kind): void
    {
        if (! $this->feedbackOpen || $this->feedbackIdeasOnly) {
            return;
        }

        if ($kind === BookFeedbackEntry::KIND_LIKE) {
            $this->feedbackLikes[] = ['id' => null, 'body' => ''];
        } elseif ($kind === BookFeedbackEntry::KIND_DISLIKE) {
            $this->feedbackDislikes[] = ['id' => null, 'body' => ''];
        } else {
            return;
        }

        $this->feedbackDirty = true;
    }

    public function removeFeedbackEntry(string $kind, int $index): void
    {
        if (! $this->feedbackOpen || $this->feedbackIdeasOnly) {
            return;
        }

        if ($kind === BookFeedbackEntry::KIND_LIKE) {
            if (! array_key_exists($index, $this->feedbackLikes)) {
                return;
            }
            array_splice($this->feedbackLikes, $index, 1);
            $this->feedbackLikes = array_values($this->feedbackLikes);
        } elseif ($kind === BookFeedbackEntry::KIND_DISLIKE) {
            if (! array_key_exists($index, $this->feedbackDislikes)) {
                return;
            }
            array_splice($this->feedbackDislikes, $index, 1);
            $this->feedbackDislikes = array_values($this->feedbackDislikes);
        } else {
            return;
        }

        $this->feedbackDirty = true;
    }

    public function addFeedbackSection(string $title = ''): void
    {
        if (! $this->feedbackOpen) {
            return;
        }

        $title = trim($title);
        if ($title === '') {
            return;
        }

        $this->feedbackNewSectionSeq++;
        $key = 'new-'.$this->feedbackNewSectionSeq;
        $chapters = $this->feedbackChapters;
        $chapters[] = [
            'id' => null,
            'key' => $key,
            'title' => $title,
            'ideas' => [],
        ];
        $this->feedbackChapters = $chapters;
        $this->feedbackChapterKey = $key;
        $this->feedbackDirty = true;
    }

    public function removeFeedbackSection(string $key): void
    {
        if (! $this->feedbackOpen || $key === 'general') {
            return;
        }

        $index = $this->feedbackChapterIndex($key);
        if ($index === null) {
            return;
        }

        $chapters = $this->feedbackChapters;
        array_splice($chapters, $index, 1);
        $this->feedbackChapters = array_values($chapters);
        if ($this->feedbackChapterKey === $key) {
            $this->feedbackChapterKey = 'general';
        }
        $this->feedbackDirty = true;
    }

    public function addFeedbackIdea(?string $key = null): void
    {
        if (! $this->feedbackOpen) {
            return;
        }

        $key = $key ?: $this->feedbackChapterKey;
        $index = $this->feedbackChapterIndex($key);
        if ($index === null) {
            return;
        }

        $chapters = $this->feedbackChapters;
        $chapters[$index]['ideas'][] = ['id' => null, 'body' => ''];
        $this->feedbackChapters = $chapters;
        $this->feedbackDirty = true;
    }

    public function removeFeedbackIdea(string $key, int $ideaIndex): void
    {
        if (! $this->feedbackOpen) {
            return;
        }

        $index = $this->feedbackChapterIndex($key);
        if ($index === null || ! array_key_exists($ideaIndex, $this->feedbackChapters[$index]['ideas'] ?? [])) {
            return;
        }

        $chapters = $this->feedbackChapters;
        array_splice($chapters[$index]['ideas'], $ideaIndex, 1);
        $chapters[$index]['ideas'] = array_values($chapters[$index]['ideas']);
        $this->feedbackChapters = $chapters;
        $this->feedbackDirty = true;
    }

    public function saveFeedback(): void
    {
        if ($this->feedbackBookId === null) {
            return;
        }

        $book = $this->findBook($this->feedbackBookId);
        if ($this->feedbackIdeasOnly) {
            if ($book->isArticle()) {
                return;
            }

            $chapters = $this->normalizedFeedbackChapters();
            $hasIdeas = collect($chapters)->contains(
                fn (array $chapter) => $chapter['ideas'] !== []
            );
            $hasSections = collect($chapters)->contains(
                fn (array $chapter) => $chapter['key'] !== 'general'
            );

            if (! $hasIdeas && ! $hasSections) {
                $book->feedbackIdeas()->delete();
                $book->feedbackSections()->delete();
            } else {
                $this->syncFeedbackChapters($book, $chapters);
            }

            $previousKey = $this->feedbackChapterKey;
            $previousTitle = $this->feedbackChapterTitle($previousKey);
            $book->unsetRelation('feedbackSections');
            $book->unsetRelation('feedbackIdeas');
            $this->feedbackChapters = $this->feedbackChapterPayloads($book);
            $this->feedbackChapterKey = $this->resolvedFeedbackChapterKey($previousKey, $previousTitle);
            $this->feedbackDirty = false;
            $this->dispatch('book-feedback-saved');

            return;
        }

        if (! $book->isFinished()) {
            return;
        }

        $description = trim($this->feedbackDescription);
        $likes = $this->normalizedFeedbackEntries($this->feedbackLikes);
        $dislikes = $this->normalizedFeedbackEntries($this->feedbackDislikes);
        $chapters = $this->normalizedFeedbackChapters();
        $hasIdeas = collect($chapters)->contains(
            fn (array $chapter) => $chapter['ideas'] !== []
        );
        $hasSections = collect($chapters)->contains(
            fn (array $chapter) => $chapter['key'] !== 'general'
        );

        if ($description === '' && $likes === [] && $dislikes === [] && ! $hasIdeas && ! $hasSections) {
            $book->feedbackIdeas()->delete();
            $book->feedbackSections()->delete();
            $book->feedbackEntries()->delete();
            $book->feedback()->delete();
        } else {
            $book->feedback()->updateOrCreate(
                ['book_id' => $book->id],
                ['description' => $description === '' ? null : $description],
            );
            $this->syncFeedbackEntries($book, BookFeedbackEntry::KIND_LIKE, $likes);
            $this->syncFeedbackEntries($book, BookFeedbackEntry::KIND_DISLIKE, $dislikes);
            $this->syncFeedbackChapters($book, $chapters);
        }

        $previousKey = $this->feedbackChapterKey;
        $previousTitle = $this->feedbackChapterTitle($previousKey);
        $book->unsetRelation('feedback');
        $book->unsetRelation('feedbackEntries');
        $book->unsetRelation('feedbackSections');
        $book->unsetRelation('feedbackIdeas');
        $this->feedbackDescription = (string) ($book->feedback?->description ?? '');
        $this->feedbackLikes = $this->feedbackEntryPayloads($book, BookFeedbackEntry::KIND_LIKE);
        $this->feedbackDislikes = $this->feedbackEntryPayloads($book, BookFeedbackEntry::KIND_DISLIKE);
        $this->feedbackChapters = $this->feedbackChapterPayloads($book);
        $this->feedbackChapterKey = $this->resolvedFeedbackChapterKey($previousKey, $previousTitle);
        $this->feedbackDirty = false;
        $this->dispatch('book-feedback-saved');
    }

    public function updatedFeedbackDescription(): void
    {
        $this->feedbackDirty = true;
    }

    public function updatedFeedbackLikes(): void
    {
        $this->feedbackDirty = true;
    }

    public function updatedFeedbackDislikes(): void
    {
        $this->feedbackDirty = true;
    }

    public function updatedFeedbackChapters(): void
    {
        $this->feedbackDirty = true;
    }

    /**
     * @return list<array{id: int|null, body: string}>
     */
    private function feedbackEntryPayloads(Book $book, string $kind): array
    {
        return $book->feedbackEntries
            ->where('kind', $kind)
            ->values()
            ->map(fn (BookFeedbackEntry $entry) => [
                'id' => $entry->id,
                'body' => $entry->body,
            ])
            ->all();
    }

    /**
     * @param  list<array{id?: int|null, body?: mixed}>  $entries
     * @return list<array{id: int|null, body: string}>
     */
    private function normalizedFeedbackEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            $body = trim((string) ($entry['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            $id = $entry['id'] ?? null;
            $normalized[] = [
                'id' => $id !== null && $id !== '' ? (int) $id : null,
                'body' => $body,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{id: int|null, body: string}>  $entries
     */
    private function syncFeedbackEntries(Book $book, string $kind, array $entries): void
    {
        $keepIds = [];

        foreach (array_values($entries) as $index => $entry) {
            $model = null;
            if ($entry['id'] !== null) {
                $model = $book->feedbackEntries()->whereKey($entry['id'])->where('kind', $kind)->first();
            }

            if ($model) {
                $model->update([
                    'body' => $entry['body'],
                    'position' => $index + 1,
                ]);
            } else {
                $model = $book->feedbackEntries()->create([
                    'kind' => $kind,
                    'body' => $entry['body'],
                    'position' => $index + 1,
                ]);
            }

            $keepIds[] = $model->id;
        }

        $query = $book->feedbackEntries()->where('kind', $kind);
        if ($keepIds !== []) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();
    }

    /**
     * @return list<array{id: int|null, key: string, title: string, ideas: list<array{id: int|null, body: string}>}>
     */
    private function feedbackChapterPayloads(Book $book): array
    {
        $book->loadMissing(['feedbackSections', 'feedbackIdeas']);

        $ideas = $book->feedbackIdeas->groupBy(
            fn (BookFeedbackIdea $idea) => $idea->section_id === null ? 'general' : 'sec-'.$idea->section_id
        );

        $chapters = [[
            'id' => null,
            'key' => 'general',
            'title' => 'General',
            'ideas' => $this->feedbackIdeaPayloads($ideas->get('general', collect())),
        ]];

        foreach ($book->feedbackSections as $section) {
            $key = 'sec-'.$section->id;
            $chapters[] = [
                'id' => $section->id,
                'key' => $key,
                'title' => $section->title,
                'ideas' => $this->feedbackIdeaPayloads($ideas->get($key, collect())),
            ];
        }

        return $chapters;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BookFeedbackIdea>  $ideas
     * @return list<array{id: int|null, body: string}>
     */
    private function feedbackIdeaPayloads($ideas): array
    {
        return $ideas
            ->sortBy([
                fn (BookFeedbackIdea $idea) => $idea->position,
                fn (BookFeedbackIdea $idea) => $idea->id,
            ])
            ->values()
            ->map(fn (BookFeedbackIdea $idea) => [
                'id' => $idea->id,
                'body' => $idea->body,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int|null, key: string, title: string, ideas: list<array{id: int|null, body: string}>}>
     */
    private function normalizedFeedbackChapters(): array
    {
        $normalized = [];

        foreach ($this->feedbackChapters as $chapter) {
            $key = (string) ($chapter['key'] ?? '');
            $ideas = $this->normalizedFeedbackEntries($chapter['ideas'] ?? []);
            if ($key === 'general') {
                $normalized[] = [
                    'id' => null,
                    'key' => 'general',
                    'title' => 'General',
                    'ideas' => $ideas,
                ];
                continue;
            }

            $title = trim((string) ($chapter['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $id = $chapter['id'] ?? null;
            $normalized[] = [
                'id' => $id !== null && $id !== '' ? (int) $id : null,
                'key' => $key,
                'title' => $title,
                'ideas' => $ideas,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{id: int|null, key: string, title: string, ideas: list<array{id: int|null, body: string}>}>  $chapters
     */
    private function syncFeedbackChapters(Book $book, array $chapters): void
    {
        $keepSectionIds = [];
        $keepIdeaIds = [];
        $sectionPosition = 1;

        foreach ($chapters as $chapter) {
            $sectionId = null;
            if ($chapter['key'] !== 'general') {
                $section = null;
                if ($chapter['id'] !== null) {
                    $section = $book->feedbackSections()->whereKey($chapter['id'])->first();
                }
                if ($section) {
                    $section->update([
                        'title' => $chapter['title'],
                        'position' => $sectionPosition,
                    ]);
                } else {
                    $section = $book->feedbackSections()->create([
                        'title' => $chapter['title'],
                        'position' => $sectionPosition,
                    ]);
                }
                $keepSectionIds[] = $section->id;
                $sectionId = $section->id;
                $sectionPosition++;
            }

            foreach (array_values($chapter['ideas']) as $index => $idea) {
                $model = null;
                if ($idea['id'] !== null) {
                    $model = $book->feedbackIdeas()->whereKey($idea['id'])->first();
                }
                $payload = [
                    'section_id' => $sectionId,
                    'body' => $idea['body'],
                    'position' => $index + 1,
                ];
                if ($model) {
                    $model->update($payload);
                } else {
                    $model = $book->feedbackIdeas()->create($payload);
                }
                $keepIdeaIds[] = $model->id;
            }
        }

        $ideaQuery = $book->feedbackIdeas();
        if ($keepIdeaIds !== []) {
            $ideaQuery->whereNotIn('id', $keepIdeaIds);
        }
        $ideaQuery->delete();

        $sectionQuery = $book->feedbackSections();
        if ($keepSectionIds !== []) {
            $sectionQuery->whereNotIn('id', $keepSectionIds);
        }
        $sectionQuery->delete();
    }

    private function feedbackChapterIndex(string $key): ?int
    {
        foreach ($this->feedbackChapters as $index => $chapter) {
            if (($chapter['key'] ?? null) === $key) {
                return $index;
            }
        }

        return null;
    }

    private function feedbackChapterTitle(string $key): ?string
    {
        $index = $this->feedbackChapterIndex($key);
        if ($index === null) {
            return null;
        }

        $title = trim((string) ($this->feedbackChapters[$index]['title'] ?? ''));

        return $title !== '' ? $title : null;
    }

    private function resolvedFeedbackChapterKey(string $previousKey, ?string $previousTitle): string
    {
        foreach ($this->feedbackChapters as $chapter) {
            if (($chapter['key'] ?? null) === $previousKey) {
                return $previousKey;
            }
        }

        if ($previousKey !== 'general' && $previousTitle !== null) {
            foreach ($this->feedbackChapters as $chapter) {
                if (($chapter['key'] ?? null) !== 'general' && ($chapter['title'] ?? null) === $previousTitle) {
                    return (string) $chapter['key'];
                }
            }
        }

        return 'general';
    }

    public function openBookStats(int $id): void
    {
        $book = $this->findBook($id);
        if ($book->isDelimiter()) {
            return;
        }

        $this->dispatch('open-book-stats', id: $id);
    }

    public function render()
    {
        $all = $this->catalog()
            ->withCount('readingSessions')
            ->with(['latestSession', 'earliestSession', 'feedback', 'feedbackEntries', 'feedbackSections', 'feedbackIdeas', 'readingSessions' => fn ($q) => $q->whereNull('finished_at')])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $books = $all->reject(fn (Book $book) => $book->isFinished() && ! $book->isDelimiter())->values();
        $finishedBooks = $all->filter(fn (Book $book) => $book->isFinished() && ! $book->isDelimiter())->values();
        $selected = $all->first(fn (Book $book) => $book->id === $this->selectedBookId && ! $book->isDelimiter());

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

        $liveTotals = $this->liveTotals($selected?->id);

        $startedSessionId = $selected?->startedSessionId($sessions);

        return view('livewire.read', [
            'books' => $books,
            'finishedBooks' => $finishedBooks,
            'selected' => $selected,
            'sessions' => $sessions,
            'startedSessionId' => $startedSessionId,
            'bookTotals' => $bookTotals,
            'pageTotals' => $pageTotals,
            'liveTotals' => $liveTotals,
            'runningSession' => $selected ? $this->runningSessionFor($selected->id) : null,
            'timezone' => Clock::timezone(),
            'isArticles' => $this->isArticles(),
        ])->layout('layouts.app')->title($this->isArticles() ? 'Articles' : 'Books');
    }

    /**
     * Base minutes (finished sessions) + running-session clip starts for the live Alpine counters.
     *
     * @return array{todayBase: int, weekBase: int, bookBase: int, todayRuns: list<int>, weekRuns: list<int>, bookRuns: list<int>}
     */
    private function liveTotals(?int $bookId = null): array
    {
        if ($bookId === null && $this->selectedBookId) {
            $bookId = $this->catalog()->whereKey($this->selectedBookId)->exists() ? $this->selectedBookId : null;
        }

        $weekStart = Clock::now()->startOfWeek(Carbon::MONDAY)->utc();
        $todayStart = Clock::now()->startOfDay()->utc();
        $nowUtc = Clock::now()->utc();
        $epochStart = Carbon::createFromTimestampUTC(0);

        return [
            'todayBase' => $this->overlapMinutes($todayStart, $nowUtc, finishedOnly: true),
            'weekBase' => $this->overlapMinutes($weekStart, $nowUtc, finishedOnly: true),
            'bookBase' => $bookId
                ? $this->overlapMinutes($epochStart, $nowUtc, $bookId, finishedOnly: true)
                : 0,
            'todayRuns' => $this->runningClipStartsMs($todayStart),
            'weekRuns' => $this->runningClipStartsMs($weekStart),
            'bookRuns' => $bookId
                ? $this->runningClipStartsMs($epochStart, $bookId)
                : [],
        ];
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

    public const BOOK_TYPES = ['A', 'B', 'C'];

    public function setBookType(int $id, ?string $type): void
    {
        if ($this->isArticles()) {
            return;
        }

        $book = $this->findBook($id);
        if ($book->isDelimiter()) {
            return;
        }

        $type = $type === null || $type === '' ? null : strtoupper($type);
        if ($type !== null && ! in_array($type, self::BOOK_TYPES, true)) {
            return;
        }
        $book->update(['type' => $type]);
    }

    public function reorderBooks(array $ids): void
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        if ($ids === [] || $this->openCatalog()->whereIn('id', $ids)->count() !== count($ids)) {
            return;
        }
        foreach ($ids as $i => $id) {
            $this->catalog()->whereKey($id)->update(['position' => $i + 1]);
        }

        $this->pruneTrailingDelimiters();
    }

    public function sortBooksByType(): void
    {
        if ($this->isArticles()) {
            return;
        }

        $open = $this->openItems();
        $delimiters = $open->filter(fn (Book $book) => $book->isDelimiter())->values();
        $items = $open->reject(fn (Book $book) => $book->isDelimiter())->values();

        $groups = [];
        foreach (self::BOOK_TYPES as $type) {
            $groups[$type] = $items->filter(fn (Book $book) => $book->type === $type)->values();
        }
        $ungrouped = $items->reject(
            fn (Book $book) => in_array($book->type, self::BOOK_TYPES, true)
        )->values();

        $ordered = collect();
        $typedGroups = array_values(array_filter($groups, fn ($group) => $group->isNotEmpty()));
        foreach ($typedGroups as $index => $group) {
            if ($index > 0) {
                $ordered->push(null);
            }
            foreach ($group as $book) {
                $ordered->push($book);
            }
        }
        foreach ($ungrouped as $book) {
            $ordered->push($book);
        }

        $dividerCount = $ordered->filter(fn ($item) => $item === null)->count();
        while ($delimiters->count() < $dividerCount) {
            $delimiters->push($this->createDelimiter(0));
        }
        foreach ($delimiters->slice($dividerCount) as $extra) {
            $extra->delete();
        }
        $delimiters = $delimiters->take($dividerCount)->values();

        $dividerIndex = 0;
        $position = 1;
        foreach ($ordered as $item) {
            if ($item === null) {
                $delimiters[$dividerIndex]->update(['position' => $position]);
                $dividerIndex++;
            } else {
                $item->update(['position' => $position]);
            }
            $position++;
        }

        $this->pruneTrailingDelimiters();
    }

    private function openCatalog()
    {
        return $this->catalog()->where(function ($query): void {
            $query->where('is_delimiter', true)
                ->orWhere('status', '!=', Book::STATUS_FINISHED);
        });
    }

    private function openItems()
    {
        return $this->openCatalog()->orderBy('position')->orderBy('id')->get()->values();
    }

    private function createDelimiter(int $position): Book
    {
        return $this->catalog()->create([
            'title' => '',
            'author' => null,
            'total_pages' => null,
            'status' => Book::STATUS_READING,
            'position' => $position,
            'is_delimiter' => true,
            'kind' => $this->kind,
        ]);
    }

    private function pruneTrailingDelimiters(): void
    {
        $open = $this->openItems();
        while ($open->isNotEmpty() && $open->last()->isDelimiter()) {
            $open->pop()->delete();
        }
    }

    private function catalog()
    {
        return Book::query()->ofKind($this->kind);
    }

    private function findBook(int $id): Book
    {
        return $this->catalog()->whereKey($id)->firstOrFail();
    }

    private function findSession(int $id): ReadingSession
    {
        return ReadingSession::query()
            ->whereKey($id)
            ->whereHas('book', fn ($q) => $q->where('kind', $this->kind))
            ->firstOrFail();
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
        } else {
            $query->whereHas('book', fn ($q) => $q->where('kind', $this->kind));
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
        } else {
            $query->whereHas('book', fn ($q) => $q->where('kind', $this->kind));
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
