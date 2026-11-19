<?php

namespace App\Models;

use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    public const STATUS_READING = 'reading';

    public const STATUS_FINISHED = 'finished';

    public const KIND_BOOK = 'book';

    public const KIND_ARTICLE = 'article';

    protected $fillable = [
        'title',
        'author',
        'publisher',
        'total_pages',
        'current_page',
        'status',
        'position',
        'type',
        'kind',
        'started_on',
        'pdf_path',
        'cover_path',
        'spine_path',
    ];

    protected function casts(): array
    {
        return [
            'total_pages' => 'integer',
            'current_page' => 'integer',
            'position' => 'integer',
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

    public function latestSession(): HasOne
    {
        return $this->hasOne(ReadingSession::class)->latestOfMany('started_at');
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(BookHighlight::class);
    }

    public function isReading(): bool
    {
        return $this->status === self::STATUS_READING;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isArticle(): bool
    {
        return $this->kind === self::KIND_ARTICLE;
    }

    /**
     * True when started_on is the display-timezone calendar day of $now (Europe/Bucharest).
     */
    public function isStartedToday(?Carbon $now = null): bool
    {
        if ($this->started_on === null) {
            return false;
        }

        $today = ($now ?? Clock::now())->timezone(Clock::timezone())->toDateString();

        return $this->started_on->toDateString() === $today;
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function pdfUrl(): ?string
    {
        if (! $this->pdf_path) {
            return null;
        }

        return Storage::disk('public')->url($this->pdf_path);
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_path);
    }

    public function spineUrl(): ?string
    {
        if (! $this->spine_path) {
            return null;
        }

        return Storage::disk('public')->url($this->spine_path);
    }
}
