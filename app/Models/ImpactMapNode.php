<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImpactMapNode extends Model
{
    public const GOAL = 0;

    public const STORY = 4;

    /** Column labels by level. */
    public const LEVELS = ['Goal', 'Actor', 'Impact', 'Deliverable', 'Story'];

    protected $fillable = ['impact_map_id', 'parent_id', 'level', 'title', 'position'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'position' => 'integer'];
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(ImpactMap::class, 'impact_map_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ImpactMapNode::class, 'parent_id')->orderBy('position')->orderBy('id');
    }
}
