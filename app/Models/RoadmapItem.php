<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\RoadmapItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoadmapItem extends Model
{
    /** @use HasFactory<RoadmapItemFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'started_on',
        'completed_on',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function isPending(): bool
    {
        return $this->completed_on === null;
    }

    public function rangeStart(): Carbon
    {
        return $this->started_on->copy()->startOfDay();
    }

    public function rangeEnd(?Carbon $today = null): Carbon
    {
        if ($this->completed_on !== null) {
            return $this->completed_on->copy()->startOfDay();
        }

        $today = ($today ?? now())->copy()->startOfDay();
        $start = $this->rangeStart();

        return $today->lt($start) ? $start : $today;
    }
}
