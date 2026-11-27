<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookFeedbackIdea extends Model
{
    protected $fillable = [
        'book_id',
        'section_id',
        'body',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'section_id' => 'integer',
            'position' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(BookFeedbackSection::class, 'section_id');
    }
}
