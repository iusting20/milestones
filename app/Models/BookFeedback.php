<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookFeedback extends Model
{
    protected $table = 'book_feedback';

    protected $fillable = [
        'book_id',
        'description',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(BookFeedbackEntry::class, 'book_id', 'book_id')
            ->orderBy('kind')
            ->orderBy('position')
            ->orderBy('id');
    }
}
