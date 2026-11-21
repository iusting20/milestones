<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'hub',
        'name',
        'slug',
        'position',
        'is_archived',
        'track_comms',
        'is_delimiter',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_archived' => 'boolean',
            'track_comms' => 'boolean',
            'is_delimiter' => 'boolean',
        ];
    }

    public function linkGroups(): HasMany
    {
        return $this->hasMany(LinkGroup::class)->orderBy('position');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class)->orderBy('occurred_on')->orderBy('id');
    }

    public static function uniqueSlug(string $hub, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $i = 2;

        while (
            static::query()
                ->where('hub', $hub)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
