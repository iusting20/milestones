<?php

namespace App\Models;

use App\Support\JobApplicationWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    protected $attributes = [
        'category' => 'software',
    ];

    protected $fillable = [
        'key', 'date_added', 'status', 'date_applied', 'company', 'role', 'type', 'rate', 'ir35',
        'category', 'location', 'source', 'link', 'contact', 'next_step', 'follow_up_date', 'notes', 'sheet_row',
        'first_seen_at', 'last_seen_at', 'removed_at', 'origin',
        'cover_letter', 'cover_letter_not_needed', 'cover_letter_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'date_added' => 'date',
            'date_applied' => 'date',
            'follow_up_date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'removed_at' => 'datetime',
            'sheet_row' => 'integer',
            'cover_letter_not_needed' => 'boolean',
            'cover_letter_updated_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(JobApplicationEvent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(JobApplicationMessage::class);
    }

    public function label(): string
    {
        return trim(($this->role ?: 'Role').' · '.($this->company ?: 'Company'));
    }

    public function hasCoverLetter(): bool
    {
        return filled($this->cover_letter);
    }

    public function isClosed(): bool
    {
        return JobApplicationWriter::canonical((string) $this->status, ['closed']) !== null;
    }

    /** direct, agency, or upwork. Upwork wins; a named source is a recruiter or board; otherwise direct. */
    public function channel(): string
    {
        $haystack = mb_strtolower(trim(($this->source ?? '').' '.($this->link ?? '')));
        if (str_contains($haystack, 'upwork')) {
            return 'upwork';
        }
        if (filled($this->source)) {
            return 'agency';
        }

        return 'direct';
    }

    /** They replied, and the role is not closed or already decided. */
    public function isInDiscussion(): bool
    {
        if ($this->isClosed() || ! $this->has_reply) {
            return false;
        }

        return JobApplicationWriter::canonical((string) $this->status, ['Rejected', 'Offer']) === null;
    }

    public function threadsUrl(): string
    {
        return route('jobs.threads', [
            'token' => config('jobs.page_token'),
            'application' => $this,
        ]);
    }

    /**
     * Status to restore when reopening a closed gig: the status it had when it was
     * last marked closed, otherwise Applied if a date applied is set, else To apply.
     */
    public function reopenStatus(): string
    {
        $event = $this->events()
            ->where('type', 'status')
            ->whereRaw('lower(to_value) = ?', ['closed'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        $from = trim((string) ($event?->from_value ?? ''));
        if ($from !== '' && JobApplicationWriter::canonical($from, ['closed']) === null) {
            return JobApplicationWriter::canonical($from, JobApplicationWriter::KNOWN_STATUSES) ?? $from;
        }

        return $this->date_applied ? 'Applied' : JobApplicationWriter::DEFAULT_STATUS;
    }

    /** Same-origin avatar URL. The PNG is fetched once and cached under storage/app/private/avatars. */
    public function avatarUrl(): string
    {
        return route('jobs.avatar', [
            'token' => config('jobs.page_token'),
            'application' => $this,
        ]);
    }

    /**
     * Upstream DiceBear Micah Bare PNG. Seed alone varies the face; only the mouth list
     * is constrained so sad / frown / nervous stay out.
     */
    public function dicebearSourceUrl(): string
    {
        return 'https://api.dicebear.com/9.x/micah/png?'.http_build_query([
            'seed' => 'job-'.$this->id,
            'size' => 256,
            'radius' => 0,
            'earringsProbability' => 0,
            'glassesProbability' => 0,
            'facialHairProbability' => 0,
            'mouth' => 'surprised,laughing,smile,pucker,smirk',
        ]);
    }

    public function avatarCachePath(): string
    {
        return 'avatars/job-'.$this->id.'-256.png';
    }
}
