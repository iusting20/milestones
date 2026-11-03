<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ImpactMap extends Model
{
    protected $fillable = ['name', 'client'];

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

    public function goal(): HasOne
    {
        return $this->hasOne(ImpactMapNode::class)->whereNull('parent_id');
    }
}
