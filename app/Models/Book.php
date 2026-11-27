<?php

namespace App\Models;

use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Book extends Model
{
    public const STATUS_READING = 'reading';

    public const STATUS_FINISHED = 'finished';

    public const KIND_BOOK = 'book';

    public const KIND_ARTICLE = 'article';

    protected $fillable = [
        'title',
        'author',
        'total_pages',
        'status',
        'position',
        'is_delimiter',
        'type',
        'kind',
        'started_on',
    ];

    protected function casts(): array
    {
        return [
            'total_pages' => 'integer',
            'position' => 'integer',
            'is_delimiter' => 'boolean',
            'started_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Book $book): void {
            if ($book->kind === null || $book->kind === '') {
                $book->kind = self::KIND_BOOK;
            }
        });
    }

    public function readingSessions(): HasMany
    {
        return $this->hasMany(ReadingSession::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(BookFeedback::class);
    }

    public function feedbackEntries(): HasMany
    {
        return $this->hasMany(BookFeedbackEntry::class)->orderBy('kind')->orderBy('position')->orderBy('id');
    }

    public function feedbackSections(): HasMany
    {
        return $this->hasMany(BookFeedbackSection::class)->orderBy('position')->orderBy('id');
    }

    public function feedbackIdeas(): HasMany
    {
        return $this->hasMany(BookFeedbackIdea::class)->orderBy('position')->orderBy('id');
    }

    public function hasFeedback(): bool
    {
        $description = $this->relationLoaded('feedback')
            ? $this->feedback?->description
            : $this->feedback()->value('description');

        if (trim((string) $description) !== '') {
            return true;
        }

        if ($this->relationLoaded('feedbackEntries')) {
            if ($this->feedbackEntries->contains(
                fn (BookFeedbackEntry $entry) => trim($entry->body) !== ''
            )) {
                return true;
            }
        } elseif ($this->feedbackEntries()->where('body', '!=', '')->exists()) {
            return true;
        }

        if ($this->relationLoaded('feedbackIdeas')) {
            return $this->feedbackIdeas->contains(
                fn (BookFeedbackIdea $idea) => trim($idea->body) !== ''
            );
        }

        return $this->feedbackIdeas()->where('body', '!=', '')->exists();
    }

    public function latestSession(): HasOne
    {
        return $this->hasOne(ReadingSession::class)->latestOfMany('started_at');
    }

    public function earliestSession(): HasOne
    {
        return $this->hasOne(ReadingSession::class)->oldestOfMany('started_at');
    }

    public function isReading(): bool
    {
        return $this->status === self::STATUS_READING;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isDelimiter(): bool
    {
        return (bool) $this->is_delimiter;
    }

    public function isArticle(): bool
    {
        return $this->kind === self::KIND_ARTICLE;
    }

    /**
     * True when the explicit started_on mark is the display-timezone calendar day of $now.
     * Used for the "Mark/Clear started today" controls, not for the power-on glyph.
     */
    public function isStartedToday(?Carbon $now = null): bool
    {
        if ($this->started_on === null) {
            return false;
        }

        return $this->started_on->toDateString() === self::displayDate($now);
    }

    /**
     * The date this item is treated as having been started (display timezone).
     * An explicit started_on wins; otherwise the calendar day of the earliest session.
     */
    public function startDate(?Carbon $now = null): ?string
    {
        if ($this->started_on !== null) {
            return $this->started_on->toDateString();
        }

        $session = $this->relationLoaded('earliestSession')
            ? $this->earliestSession
            : $this->earliestSession()->first();

        if ($session?->started_at === null) {
            return null;
        }

        return $session->started_at->copy()->timezone(Clock::timezone())->toDateString();
    }

    /** True when the start date (explicit or first session) is the calendar day of $now. */
    public function showsStartedGlyphToday(?Carbon $now = null): bool
    {
        if ($this->isFinished()) {
            return false;
        }

        $start = $this->startDate();

        return $start !== null && $start === self::displayDate($now);
    }

    /** True when the start date falls on the Monday–Sunday week starting at $weekStart. */
    public function isStartedInWeek(Carbon $weekStart): bool
    {
        $start = $this->startDate();
        if ($start === null) {
            return false;
        }

        $monday = $weekStart->copy()->timezone(Clock::timezone())->startOfWeek(Carbon::MONDAY)->toDateString();
        $sunday = $weekStart->copy()->timezone(Clock::timezone())->startOfWeek(Carbon::MONDAY)->addDays(6)->toDateString();

        return $start >= $monday && $start <= $sunday;
    }

    /**
     * The earliest session whose display-timezone date equals the start date, if any.
     */
    public function startedSessionId($sessions): ?int
    {
        if ($this->isFinished()) {
            return null;
        }

        $start = $this->startDate();
        if ($start === null) {
            return null;
        }

        $tz = Clock::timezone();
        $match = collect($sessions)
            ->filter(fn (ReadingSession $session) => $session->started_at !== null
                && $session->started_at->copy()->timezone($tz)->toDateString() === $start)
            ->sortBy([
                fn (ReadingSession $session) => $session->started_at->timestamp,
                fn (ReadingSession $session) => $session->id,
            ])
            ->first();

        return $match?->id;
    }

    public static function displayDate(?Carbon $now = null): string
    {
        return ($now ?? Clock::now())->timezone(Clock::timezone())->toDateString();
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }
}
