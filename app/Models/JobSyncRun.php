<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSyncRun extends Model
{
    protected $fillable = ['synced_at', 'source', 'rows', 'created', 'updated', 'status_changes', 'removed'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }
}
