<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationMessage extends Model
{
    public const DIRECTIONS = ['sent', 'received'];

    protected $attributes = [
        'has_time' => true,
    ];

    protected $fillable = [
        'job_application_id', 'direction', 'occurred_at', 'has_time', 'subject', 'body',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'has_time' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function occurredLabel(string $tz): string
    {
        $local = $this->occurred_at->copy()->timezone($tz);
        if ($this->has_time) {
            return $local->format('D j M Y, H:i').' ('.$tz.')';
        }

        return $local->format('D j M Y');
    }
}
