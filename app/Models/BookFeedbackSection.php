<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookFeedbackSection extends Model
{
    protected $fillable = [
        'book_id',
        'title',
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

    public function ideas(): HasMany
    {
        return $this->hasMany(BookFeedbackIdea::class, 'section_id')->orderBy('position')->orderBy('id');
    }
}
