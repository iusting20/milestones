<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpactMapCheckin extends Model
{
    protected $fillable = ['value', 'date', 'note'];

    protected $casts = ['value' => 'float', 'date' => 'date'];

    public function map(): BelongsTo
    {
        return $this->belongsTo(ImpactMap::class, 'impact_map_id');
    }
}
