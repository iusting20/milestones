<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Book extends Model
{
    public const STATUS_READING = 'reading';

    public const STATUS_FINISHED = 'finished';

    protected $fillable = [
        'title',
        'author',
        'total_pages',
        'status',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'total_pages' => 'integer',
            'position' => 'integer',
        ];
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
}
