<?php

namespace App\Models;

use App\Enums\DripTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TodoItem extends Model
{
    protected $fillable = [
        'date',
        'title',
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

    public function links(): HasMany
    {
        return $this->hasMany(TodoItemLink::class)->orderBy('position')->orderBy('id');
    }
}
