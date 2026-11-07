<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual client/asset classification of a logged item (by its title) chosen from the
 * "Others" modal of the log page activity charts. One row per hub and title.
 */
class ActivityAssignment extends Model
{
    protected $fillable = [
        'hub',
        'title',
        'project_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
