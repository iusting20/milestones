<?php

namespace App\Models;

use App\Enums\WeekBoardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeekBoardItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'todo_item_id',
        'title',
        'status',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'status' => WeekBoardStatus::class,
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function todoItem(): BelongsTo
    {
        return $this->belongsTo(TodoItem::class);
    }
}
