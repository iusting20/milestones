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
        'tags',
        'last_reviewed_at',
        'is_mastered',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'last_reviewed_at' => 'datetime',
            'is_mastered' => 'boolean',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return list<string>
     */
    public function tagList(): array
    {
        if (! $this->tags) {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $this->tags) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            $tag = strtolower(ltrim(trim($part), '#'));
            if ($tag !== '' && ! in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
        }

        return $clean;
    }

    public static function normalizeTags(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            $tag = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', ltrim(trim($part), '#')) ?? '');
            if ($tag !== '' && ! in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
        }

        return $clean === [] ? null : implode(', ', $clean);
    }
}
