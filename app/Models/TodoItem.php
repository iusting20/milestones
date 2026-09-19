<?php

namespace App\Models;

use App\Enums\DripTag;
use Illuminate\Database\Eloquent\Model;

class TodoItem extends Model
{
    protected $fillable = [
        'date',
        'title',
        'url',
        'is_completed',
        'drip_tag',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_completed' => 'boolean',
            'drip_tag' => DripTag::class,
            'position' => 'integer',
        ];
    }
}
