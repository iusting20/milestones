<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookFeedbackEntry extends Model
{
    public const KIND_LIKE = 'like';

    public const KIND_DISLIKE = 'dislike';

    protected $fillable = [
        'book_id',
        'kind',
        'body',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
