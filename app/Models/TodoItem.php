<?php

namespace App\Models;

use App\Enums\DripEnergy;
use App\Enums\DripMoney;
use App\Enums\DripTag;
use App\Enums\WeekBoardStatus;
use App\Support\Clock;
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
        'drip_money',
        'drip_energy',
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
            'drip_money' => DripMoney::class,
            'drip_energy' => DripEnergy::class,
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

    /** Start a new timer session (the milestones page "Start" action). Returns false when not startable. */
    public function startLogging(): bool
    {
        if ($this->is_separator || $this->isLogging()) {
            return false;
        }

        $this->update(['logging_started_at' => now()]);

        return true;
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

        $this->syncWeekBoardCards($completed);
    }

    /**
     * Mirror of ClientWeekBoard::syncMilestone: ticking moves linked Week Board cards to the
     * bottom of their client's Done lane; unticking moves Done cards back to WIP (a linked card
     * was started, which is what puts it in WIP).
     */
    private function syncWeekBoardCards(bool $completed): void
    {
        $from = $completed ? [WeekBoardStatus::Planned, WeekBoardStatus::Wip] : [WeekBoardStatus::Done];
        $to = $completed ? WeekBoardStatus::Done : WeekBoardStatus::Wip;

        WeekBoardItem::query()
            ->where('todo_item_id', $this->id)
            ->whereIn('status', $from)
            ->get()
            ->each(function (WeekBoardItem $card) use ($to): void {
                $max = (int) WeekBoardItem::query()
                    ->where('project_id', $card->project_id)
                    ->where('status', $to)
                    ->max('position');

                $card->update(['status' => $to, 'position' => $max + 1]
                    + ($to === WeekBoardStatus::Done ? ['week' => Clock::currentWeek()] : []));
            });
    }
}
