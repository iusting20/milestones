<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ImpactMap extends Model
{
    protected $fillable = ['name', 'client', 'track_start', 'track_start_label', 'track_target', 'track_target_label'];

    protected $casts = ['track_start' => 'float', 'track_target' => 'float'];

    /** Unguessable public URL segment: /impact-maps/{slug}. */
    protected static function booted(): void
    {
        static::creating(function (ImpactMap $map) {
            $map->slug ??= Str::random(32);
        });
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(ImpactMapNode::class);
    }

    /** Progress check-ins, oldest first (latest = last). */
    public function checkins(): HasMany
    {
        return $this->hasMany(ImpactMapCheckin::class)->orderBy('date')->orderBy('id');
    }

    public function hasTracker(): bool
    {
        return $this->track_start !== null && $this->track_target !== null && $this->track_start != $this->track_target;
    }

    /** Unclamped % of the way from start to target; works for decreasing goals (start > target). */
    public function progressPct(float $value): float
    {
        if (! $this->hasTracker()) {
            return 0.0;
        }

        return ($value - $this->track_start) / ($this->track_target - $this->track_start) * 100;
    }

    public static function fmt(?float $v): string
    {
        if ($v === null) {
            return '';
        }
        $s = rtrim(rtrim(number_format($v, 4, '.', ','), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }

    public function goal(): HasOne
    {
        return $this->hasOne(ImpactMapNode::class)->whereNull('parent_id');
    }
}
