<?php

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'total_pages' => 'integer',
            'position' => 'integer',
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

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }
}
