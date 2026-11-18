<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    protected $attributes = [
        'category' => 'software',
    ];

    protected $fillable = [
        'key', 'date_added', 'status', 'date_applied', 'company', 'role', 'type', 'rate', 'ir35',
        'category', 'location', 'source', 'link', 'contact', 'next_step', 'follow_up_date', 'notes', 'sheet_row',
        'first_seen_at', 'last_seen_at', 'removed_at', 'origin',
        'cover_letter', 'cover_letter_not_needed', 'cover_letter_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'date_added' => 'date',
            'date_applied' => 'date',
            'follow_up_date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'removed_at' => 'datetime',
            'sheet_row' => 'integer',
            'cover_letter_not_needed' => 'boolean',
            'cover_letter_updated_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(JobApplicationEvent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(JobApplicationMessage::class);
    }

    public function label(): string
    {
        return trim(($this->role ?: 'Role').' · '.($this->company ?: 'Company'));
    }

    public function hasCoverLetter(): bool
    {
        return filled($this->cover_letter);
    }
}
