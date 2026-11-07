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
        'week',
    ];

    protected static function booted(): void
    {
        static::creating(function (WeekBoardItem $item): void {
            $item->week ??= \App\Support\Clock::currentWeek();
        });
    }

    /**
     * Cards of a week: everything created/finished in it, plus Planned/WIP cards
     * carried over from earlier weeks (unfinished work stays on the board).
     */
    public function scopeVisibleInWeek(\Illuminate\Database\Eloquent\Builder $query, string $week): void
    {
        $query->where(function ($q) use ($week): void {
            $q->where('week', $week)
                ->orWhere(fn ($carried) => $carried
                    ->where('week', '<', $week)
                    ->whereIn('status', [WeekBoardStatus::Planned, WeekBoardStatus::Wip]));
        });
    }

    /** "2026-W45" (also "2026-w5") to canonical "2026-W45"; null when not a real ISO week. */
    public static function normalizeWeek(?string $week): ?string
    {
        if ($week === null || ! preg_match('/^(\d{4})-W(\d{1,2})$/i', trim($week), $m)) {
            return null;
        }

        $year = (int) $m[1];
        $number = (int) $m[2];
        $weeksInYear = (int) \Carbon\Carbon::create($year, 12, 28)->isoWeek();

        if ($number < 1 || $number > $weeksInYear) {
            return null;
        }

        return sprintf('%04d-W%02d', $year, $number);
    }

    public static function weekMonday(string $week): \Carbon\Carbon
    {
        [$year, $number] = [(int) substr($week, 0, 4), (int) substr($week, -2)];

        return \Carbon\Carbon::now()->setISODate($year, $number)->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
    }

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
