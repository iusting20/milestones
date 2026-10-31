<?php

namespace App\Models;

use App\Enums\CommunicationType;
use Database\Factories\CommunicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Communication extends Model
{
    /** @use HasFactory<CommunicationFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'person_id',
        'type',
        'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommunicationType::class,
            'occurred_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function subjectName(): string
    {
        if ($this->person_id !== null) {
            return $this->person?->name ?? '';
        }

        return $this->project?->name ?? '';
    }
}
