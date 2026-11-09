<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingSession extends Model
{
    protected $fillable = [
        'book_id',
        'started_at',
        'finished_at',
        'note',
        'pages_read',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'pages_read' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function isRunning(): bool
    {
        return $this->finished_at === null;
    }

    public function durationMinutes(): int
    {
        if ($this->finished_at === null) {
            return 0;
        }

        return max(0, (int) $this->started_at->diffInMinutes($this->finished_at));
    }
}
