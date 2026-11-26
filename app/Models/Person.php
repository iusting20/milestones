<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class)->orderBy('occurred_on')->orderBy('id');
    }

    public function commNotes(): HasMany
    {
        return $this->hasMany(CommNote::class)->orderByDesc('note_date')->orderByDesc('id');
    }
}
