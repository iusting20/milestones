<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingState extends Model
{
    protected $fillable = [
        'completed',
        'dismissed_at',
    ];

    protected $attributes = [
        'completed' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'completed' => [],
        ]);
    }
}
