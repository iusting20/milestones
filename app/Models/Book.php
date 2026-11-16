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
        'type',
        'kind',
        'started_on',
    ];

    protected function casts(): array
    {
        return [
            'total_pages' => 'integer',
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
}
