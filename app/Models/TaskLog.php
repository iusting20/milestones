<?php

namespace App\Models;

use Database\Factories\TaskLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskLog extends Model
{
    /** @use HasFactory<TaskLogFactory> */
    use HasFactory;

    protected $fillable = [
        'todo_item_id',
        'title',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function todoItem(): BelongsTo
    {
        return $this->belongsTo(TodoItem::class);
    }
}
