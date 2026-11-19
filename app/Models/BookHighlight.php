<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookHighlight extends Model
{
    public const COLORS = ['yellow', 'green', 'blue', 'rose'];

    protected $fillable = [
        'book_id',
        'page_number',
        'quote',
        'note',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
