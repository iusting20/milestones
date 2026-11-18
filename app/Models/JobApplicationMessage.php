<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationMessage extends Model
{
    public const DIRECTIONS = ['sent', 'received'];

    protected $fillable = [
        'job_application_id', 'direction', 'occurred_at', 'subject', 'body',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
