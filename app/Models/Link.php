<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    protected $fillable = [
        'link_group_id',
        'title',
        'url',
        'note',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function linkGroup(): BelongsTo
    {
        return $this->belongsTo(LinkGroup::class);
    }
}
