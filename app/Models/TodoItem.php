<?php

namespace App\Models;

use App\Enums\DripTag;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TodoItem extends Model
{
    protected $fillable = [
        'date',
        'week',
        'parent_id',
        'title',
        'is_completed',
        'is_separator',
        'logging_started_at',
        'drip_tag',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_completed' => 'boolean',
            'is_separator' => 'boolean',
            'logging_started_at' => 'datetime',
            'drip_tag' => DripTag::class,
            'position' => 'integer',
        ];
    }

    public function links(): HasMany
    {
        return $this->hasMany(TodoItemLink::class)->orderBy('position')->orderBy('id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function taskLogs(): HasMany
    {
        return $this->hasMany(TaskLog::class);
    }

    public function isLogging(): bool
    {
        return $this->logging_started_at !== null;
    }

    /**
     * Clock times from the live timer, placed on this task's day.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function loggingWindow(): array
    {
        $timezone = 'Europe/Bucharest';
        $started = $this->logging_started_at->timezone($timezone);
        $finished = now()->timezone($timezone);

        if ($this->date === null) {
            return [$started->utc(), $finished->utc()];
        }

        $taskDay = $this->date->timezone($timezone);

        $startedOnDay = $started->copy()->setDateFrom($taskDay);
        $finishedOnDay = $finished->copy()->setDateFrom($taskDay);

        if ($finishedOnDay->lte($startedOnDay)) {
            if ($started->toDateString() < $taskDay->toDateString()) {
                // Genuine overnight session: started the evening before the task's day.
                $startedOnDay->subDay();
            } else {
                // Timer left running past the task's day: keep the real start, cap at end of day.
                $finishedOnDay = $taskDay->copy()->endOfDay()->startOfMinute();
            }
        }

        return [$startedOnDay->utc(), $finishedOnDay->utc()];
    }

    /**
     * Stop a running timer and write its task_log (no-op when not logging).
     */
    public function finishLogging(): void
    {
        if ($this->is_separator || ! $this->isLogging()) {
            return;
        }

        [$startedAt, $finishedAt] = $this->loggingWindow();

        TaskLog::query()->create([
            'todo_item_id' => $this->id,
            'title' => $this->title,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);

        $this->update(['logging_started_at' => null]);
    }

    /**
     * Tick/untick exactly like the checkbox on /milestones: completing also stops the timer.
     */
    public function setCompleted(bool $completed): void
    {
        if ($this->is_separator) {
            return;
        }

        $this->update(['is_completed' => $completed]);

        if ($completed) {
            $this->finishLogging();
        }
    }
}
