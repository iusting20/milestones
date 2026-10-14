<?php

namespace App\Models;

use App\Enums\DripTag;
use Illuminate\Database\Eloquent\Model;

class BacklogItem extends Model
{
    protected $fillable = [
        'title',
        'is_completed',
        'completed_at',
        'is_separator',
        'drip_tag',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'is_separator' => 'boolean',
            'drip_tag' => DripTag::class,
            'position' => 'integer',
        ];
    }
}
