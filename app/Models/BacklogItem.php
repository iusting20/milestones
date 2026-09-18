<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BacklogItem extends Model
{
    protected $fillable = [
        'title',
        'is_completed',
        'is_separator',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'is_separator' => 'boolean',
            'position' => 'integer',
        ];
    }
}
