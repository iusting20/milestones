<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoItem extends Model
{
    protected $fillable = [
        'date',
        'title',
        'url',
        'is_completed',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_completed' => 'boolean',
            'position' => 'integer',
        ];
    }
}
