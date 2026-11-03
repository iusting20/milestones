<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImpactMap extends Model
{
    protected $fillable = ['name', 'client'];

    public function nodes(): HasMany
    {
        return $this->hasMany(ImpactMapNode::class);
    }

    public function goal(): HasOne
    {
        return $this->hasOne(ImpactMapNode::class)->whereNull('parent_id');
    }
}
