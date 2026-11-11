<?php

namespace App\Support;

use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates and edits job applications directly (JSON API or the page form) and writes
 * every change into job_application_events, so the progress log stays complete now
 * that the page is the tracker (no more sheet syncs).
 */
class JobApplicationWriter
{
    public const SOURCES = ['api', 'manual'];

    /** Editable fields, in form order. */
    public const FIELDS = [
        'company', 'role', 'category', 'status', 'date_added', 'date_applied', 'type', 'rate', 'ir35',
        'location', 'source', 'link', 'contact', 'next_step', 'follow_up_date', 'notes',
    ];

    public const DATE_FIELDS = ['date_added', 'date_applied', 'follow_up_date'];

    /** Canonical spellings; incoming values matching case-insensitively are normalised to these. */
    public const KNOWN_STATUSES = ['To apply', 'Applied', 'Interview', 'Offer', 'Rejected', 'closed', 'Low priority (perm)'];

    public const TYPES = ['Contract', 'Permanent'];

    public const CATEGORIES = ['software', 'ai'];

    public const CATEGORY_LABELS = [
        'software' => 'Software Engineer',
        'ai' => 'AI Engineer',
    ];

    public const DEFAULT_CATEGORY = 'software';

    public const IR35 = ['Outside', 'Inside', 'N/A'];

    public const DEFAULT_STATUS = 'To apply';

    /** Fields whose change gets its own event type; the rest are summarised in one 'updated' event. */
    private const TRACKED = ['status', 'date_applied', 'next_step', 'follow_up_date', 'notes'];

    /**
     * Validate and normalise input. Empty strings become null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $input, bool $creating): array
    {
        $unknown = array_diff(array_keys($input), self::FIELDS);
        if ($unknown) {
            throw ValidationException::withMessages([
                'fields' => 'Unknown field(s): '.implode(', ', $unknown).'. Allowed: '.implode(', ', self::FIELDS).'.',
            ]);
        }

        $data = [];
        foreach ($input as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);
                $v = $v === '' ? null : $v;
            }
            $data[$k] = $v;
        }

        $req = $creating ? 'required' : 'sometimes|required';
        $str = 'nullable|string|max:255';
        $rules = [
            'company' => $req.'|string|max:255',
            'role' => $req.'|string|max:255',
            'category' => array_values(array_filter([
                $creating ? 'nullable' : 'sometimes',
                $creating ? null : 'required',
                'string',
                function ($attr, $value, $fail) {
                    if (self::canonicalCategory($value) === null) {
                        $fail('The category must be software or ai.');
                    }
                },
            ])),
            'status' => $str,
            'date_added' => 'nullable|date_format:Y-m-d',
            'date_applied' => 'nullable|date_format:Y-m-d',
            'follow_up_date' => 'nullable|date_format:Y-m-d',
            'type' => ['nullable', 'string', function ($attr, $value, $fail) {
                if (self::canonical($value, self::TYPES) === null) {
                    $fail('The type must be Contract or Permanent.');
                }
            }],
            'rate' => $str,
            'ir35' => $str,
            'location' => $str,
            'source' => $str,
            'link' => 'nullable|string|max:2000',
            'contact' => $str,
            'next_step' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:20000',
        ];
        $messages = ['date_format' => 'The :attribute must be a date in YYYY-MM-DD format.'];

        $data = Validator::make($data, $rules, $messages)->validate();

        if (array_key_exists('category', $data) && $data['category'] !== null) {
            $data['category'] = self::canonicalCategory($data['category']);
        }
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $data['status'] = self::canonical($data['status'], self::KNOWN_STATUSES) ?? $data['status'];
        }
        if (array_key_exists('type', $data) && $data['type'] !== null) {
            $data['type'] = self::canonical($data['type'], self::TYPES);
        }
        if (array_key_exists('ir35', $data) && $data['ir35'] !== null) {
            $data['ir35'] = self::canonical($data['ir35'], self::IR35) ?? $data['ir35'];
        }

        return $data;
    }

    public static function canonical(?string $value, array $known): ?string
    {
        foreach ($known as $k) {
            if (strcasecmp($k, trim((string) $value)) === 0) {
                return $k;
            }
        }

        return null;
    }

    /** Normalise API/form input to software|ai; also accepts the display labels. */
    public static function canonicalCategory(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));
        $v = str_replace(['_', '-'], ' ', $v);

        return match ($v) {
            'software', 'software engineer' => 'software',
            'ai', 'ai engineer' => 'ai',
            default => null,
        };
    }

    /**
     * Infer category from a role title. "AI" matches as a whole word only (so
     * "maintain" is not a hit). "agent" also matches agentic/agents. "LLM",
     * "LangGraph", "applied AI" and "artificial intelligence" also count as AI.
     */
    public static function inferCategory(?string $role): string
    {
        if (preg_match('/\bai\b|\bagent|\bllm\b|\blanggraph\b|applied\s+ai|artificial\s+intelligence/iu', (string) $role)) {
            return 'ai';
        }

        return self::DEFAULT_CATEGORY;
    }

    /** Unique key for rows created directly; can't collide with the sheet import's sha1 keys. */
    public static function newKey(): string
    {
        do {
            $key = 'direct-'.strtolower((string) Str::ulid());
        } while (JobApplication::query()->where('key', $key)->exists());

        return $key;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: JobApplication, 1: array<int, JobApplicationEvent>}
     */
    public static function create(array $input, string $source, ?Carbon $now = null): array
    {
        $data = self::validate($input, true);
        $now = ($now ?? Carbon::now())->copy()->utc();
        $data['status'] = $data['status'] ?? self::DEFAULT_STATUS;
        $data['category'] = $data['category'] ?? self::DEFAULT_CATEGORY;
        $data['date_added'] = $data['date_added'] ?? Clock::todayString();

        return DB::transaction(function () use ($data, $source, $now) {
            $app = JobApplication::query()->create($data + [
                'key' => self::newKey(),
                'origin' => $source,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            $events = [self::event($app, 'created', null, $app->status, $app->label(), $source, $now)];
            if ($app->date_applied) {
                $events[] = self::event($app, 'applied', null, $app->date_applied->toDateString(), null, $source, $now);
            }
            if ($app->next_step) {
                $events[] = self::event($app, 'next_step', null, self::short($app->next_step), $app->next_step, $source, $now);
            }
            if ($app->follow_up_date) {
                $events[] = self::event($app, 'follow_up', null, $app->follow_up_date->toDateString(), $app->next_step, $source, $now);
            }

            return [$app, $events];
        });
    }

    /**
     * Apply any subset of fields. Returns the events written (empty when nothing changed).
     *
     * @param  array<string, mixed>  $input
     * @return array<int, JobApplicationEvent>
     */
    public static function update(JobApplication $app, array $input, string $source, ?Carbon $now = null): array
    {
        $data = self::validate($input, false);
        $now = ($now ?? Carbon::now())->copy()->utc();

        $before = self::values($app);
        $after = array_merge($before, array_intersect_key($data, array_flip(self::FIELDS)));
        $changed = array_values(array_filter(self::FIELDS, fn ($f) => (string) $before[$f] !== (string) $after[$f]));

        if (! $changed) {
            return [];
        }

        return DB::transaction(function () use ($app, $data, $source, $now, $before, $after, $changed) {
            $app->fill($data);
            $app->save();

            $events = [];
            foreach ($changed as $f) {
                $from = $before[$f];
                $to = $after[$f];
                $events[] = match ($f) {
                    'status' => self::event($app, 'status', $from, $to, null, $source, $now),
                    'date_applied' => $to === null
                        ? self::event($app, 'updated', $from, null, 'Date applied cleared', $source, $now)
                        : self::event($app, 'applied', $from, $to, null, $source, $now),
                    'next_step' => self::event($app, 'next_step', self::short($from), self::short($to), $to ?? 'Next step cleared', $source, $now),
                    'follow_up_date' => self::event($app, 'follow_up', $from, $to, $to === null ? 'Follow-up cleared' : $app->next_step, $source, $now),
                    'notes' => self::event($app, 'notes', null, null, $to ?? 'Notes cleared', $source, $now),
                    default => null,
                };
            }
            $other = array_values(array_diff($changed, self::TRACKED));
            if ($other) {
                $events[] = self::event($app, 'updated', null, null, 'Changed '.implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), $other)), $source, $now);
            }

            return array_values(array_filter($events));
        });
    }

    /** @return array<string, ?string> current field values as plain strings (dates as Y-m-d) */
    public static function values(JobApplication $app): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $v = $app->getAttribute($f);
            $out[$f] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v === null ? null : (string) $v);
        }

        return $out;
    }

    private static function event(JobApplication $app, string $type, ?string $from, ?string $to, ?string $note, string $source, Carbon $now): JobApplicationEvent
    {
        return JobApplicationEvent::query()->create([
            'job_application_id' => $app->id,
            'type' => $type,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
            'source' => $source,
            'occurred_at' => $now,
        ]);
    }

    private static function short(?string $v): ?string
    {
        return $v === null ? null : mb_strimwidth($v, 0, 250, '…');
    }
}
