<?php

namespace App\Models;

use App\Support\Clock;
use Carbon\Carbon;
use Database\Factories\RoadmapItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapItem extends Model
{
    /** @use HasFactory<RoadmapItemFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'link_title',
        'link_url',
        'project_id',
        'started_on',
        'finish_on',
        'completed_on',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'finish_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function hasLink(): bool
    {
        return filled($this->link_url);
    }

    public function linkLabel(): string
    {
        $title = trim((string) $this->link_title);

        return $title !== '' ? $title : (string) $this->link_url;
    }

    public function isPending(): bool
    {
        return $this->completed_on === null;
    }

    public function rangeStart(): Carbon
    {
        return $this->started_on->copy()->startOfDay();
    }

    /** Scheduled finish day (optional), or null when none is set. */
    public function scheduledFinish(): ?Carbon
    {
        return $this->finish_on?->copy()->startOfDay();
    }

    /** True when the item was completed after its scheduled finish day. */
    public function completedLate(): bool
    {
        $finish = $this->scheduledFinish();

        return $finish !== null
            && $this->completed_on !== null
            && $this->completed_on->copy()->startOfDay()->gt($finish);
    }

    /**
     * Last day the bar covers: the later of the scheduled finish and the completion day
     * (or today while pending), so a late completion extends the bar past the finish day.
     */
    public function rangeEnd(?Carbon $today = null): Carbon
    {
        $start = $this->rangeStart();

        if ($this->completed_on !== null) {
            $end = $this->completed_on->copy()->startOfDay();
        } else {
            $today = ($today ?? Clock::today())->copy()->startOfDay();
            $end = $today->lt($start) ? $start : $today;
        }

        $finish = $this->scheduledFinish();

        if ($finish !== null && $finish->gt($end)) {
            $end = $finish;
        }

        return $end->lt($start) ? $start : $end;
    }
}
