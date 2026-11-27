<?php

namespace App\Livewire;

use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Support\Clock;
use App\Support\JobApplicationWriter;
use App\Support\Onboarding;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Secret (unlinked, login-protected) dashboard for freelance/job applications. */
#[Title('Applications')]
class JobApplications extends Component
{
    /** Add/edit form state. */
    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, ?string> */
    public array $form = [];

    public ?string $flash = null;

    /** Job shown in the read-only details modal. */
    public ?int $viewingId = null;

    /** Job whose cover letter is being edited. */
    public ?int $coverLetterJobId = null;

    public string $coverLetterBody = '';

    public bool $coverLetterNotNeeded = false;

    /** "My cover letters" list modal. */
    public bool $showCoverLetters = false;

    /** Chart drill-down: SUMMARY_SERIES key, or a raw type like Unknown. */
    public ?string $chartSlice = null;

    /** Optional Monday week start (Y-m-d) when drilling into a Per week bar. */
    public ?string $chartWeek = null;

    /** Index into the Next Steps queue for the slideshow card. */
    public int $nextStepIndex = 0;

    /** List/chart filter: all, software or ai. Default all is omitted from the URL. */
    #[Url(as: 'category', history: true, except: 'all')]
    public string $category = 'all';

    public const STATUS_COLORS = [
        'Applied' => '#4CAF50',
        'To apply' => '#39c5cf',
        'Low priority (perm)' => '#6e7681',
        'closed' => '#da3633',
    ];

    public const OTHER_COLOR = '#f778ba';

    public const TYPE_COLORS = [
        'Contract' => '#39c5cf',
        'Permanent' => '#f778ba',
    ];

    /** Shared by the status bars and the weekly chart, in this order. */
    public const SUMMARY_SERIES = [
        'added' => ['label' => 'Added', 'color' => '#39c5cf'],
        'applied' => ['label' => 'Applied', 'color' => '#4CAF50'],
        'responded' => ['label' => 'Responded', 'color' => '#f778ba'],
        'decided' => ['label' => 'Decided', 'color' => '#f0883e'],
        'contract' => ['label' => 'Contract', 'color' => '#a371f7'],
        'permanent' => ['label' => 'Permanent', 'color' => '#6e7681'],
    ];

    public static function statusColor(?string $status): string
    {
        foreach (self::STATUS_COLORS as $s => $c) {
            if (strcasecmp($s, (string) $status) === 0) {
                return $c;
            }
        }

        return self::OTHER_COLOR;
    }

    /**
     * Day rate range from free text like "£500/day", "£700–£900/day", "450-550", "Up to £650/day".
     * Returns null when nothing in a plausible day-rate range (£100–£3000) is found.
     *
     * @return array{min: int, max: int}|null
     */
    public static function parseDayRate(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        if (! preg_match_all('/(\d[\d,]*(?:\.\d+)?)\s*(k)?/i', $text, $m, PREG_SET_ORDER)) {
            return null;
        }
        $values = [];
        foreach ($m as $match) {
            $v = (float) str_replace(',', '', $match[1]);
            if (! empty($match[2])) {
                $v *= 1000;
            }
            if ($v >= 100 && $v <= 3000) {
                $values[] = (int) round($v);
            }
        }
        if (! $values) {
            return null;
        }

        return ['min' => min($values), 'max' => max($values)];
    }

    /**
     * Weekly counts (Mon-start weeks): added, applied, first reply from them, and a decided outcome (offer or passed over).
     *
     * @return array<int, array{start: string, label: string, added: int, applied: int, responded: int, decided: int}>
     */
    public static function weekly(Collection $apps, ?Carbon $today = null): array
    {
        $today = $today ?? Clock::today();
        $dated = $apps->map(fn ($a) => [
            'added' => $a->date_added,
            'applied' => $a->date_applied,
            'responded' => self::respondedOn($a),
            'decided' => self::decidedOn($a),
            'contract' => strcasecmp((string) $a->type, 'Contract') === 0 ? $a->date_added : null,
            'permanent' => strcasecmp((string) $a->type, 'Permanent') === 0 ? $a->date_added : null,
        ]);
        $dates = $dated->flatMap(fn ($row) => array_filter($row));
        if ($dates->isEmpty()) {
            return [];
        }
        $start = $dates->min()->copy()->startOfWeek(Carbon::MONDAY);
        $end = $today->copy()->startOfWeek(Carbon::MONDAY);
        $last = $dates->max()->copy()->startOfWeek(Carbon::MONDAY);
        if ($last->gt($end)) {
            $end = $last;
        }

        $blank = array_fill_keys(array_keys(self::SUMMARY_SERIES), 0);
        $weeks = [];
        for ($w = $start->copy(); $w->lte($end); $w->addWeek()) {
            $weeks[$w->toDateString()] = ['start' => $w->toDateString(), 'label' => $w->format('j M')] + $blank;
        }
        foreach ($dated as $row) {
            foreach ($row as $series => $d) {
                if (! $d) {
                    continue;
                }
                $k = $d->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                if (isset($weeks[$k])) {
                    $weeks[$k][$series]++;
                }
            }
        }

        return array_values($weeks);
    }

    /** Day of the first message from them. */
    public static function respondedOn(JobApplication $app): ?Carbon
    {
        if (! $app->relationLoaded('messages')) {
            return null;
        }
        $at = $app->messages->where('direction', 'received')->min('occurred_at');

        return $at ? Carbon::parse($at)->timezone(Clock::timezone())->startOfDay() : null;
    }

    /** Day an offer landed or the role was passed over. */
    public static function decidedOn(JobApplication $app): ?Carbon
    {
        if ($app->relationLoaded('events')) {
            $at = $app->events
                ->filter(fn ($e) => $e->type === 'status' && JobApplicationWriter::canonical((string) $e->to_value, ['Offer', 'Rejected']) !== null)
                ->min('occurred_at');
            if ($at) {
                return Carbon::parse($at)->timezone(Clock::timezone())->startOfDay();
            }
        }
        foreach ([$app->next_step, $app->notes] as $text) {
            if (preg_match('/\b(?:Rejected|Offer(?:ed)?)\s+(?:on\s+)?(\d{1,2}\s+[A-Z][a-z]{2,8}\s+\d{4})\b/u', (string) $text, $m)) {
                try {
                    return Carbon::parse($m[1])->startOfDay();
                } catch (\Throwable) {
                    continue;
                }
            }
        }
        if (JobApplicationWriter::canonical((string) $app->status, ['Offer', 'Rejected']) !== null && $app->updated_at) {
            return $app->updated_at->copy()->timezone(Clock::timezone())->startOfDay();
        }

        return null;
    }

    /** Statuses that count as "not applied yet" for the Next Steps panel (case-insensitive). */
    public const NEXT_STEP_STATUSES = ['To apply', 'Lead'];

    /**
     * Comparable day rate for ranking leads: the top of the stated range, hourly rates
     * converted at 8 hours a day. Null when no rate can be read (salaries, "Not stated").
     */
    public static function rankRate(?string $text): ?int
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        if (preg_match('/(?:\/|\bper\s+)(?:hour|hr|h)\b|\bhourly\b|\bp\/?h\b/i', $text)) {
            preg_match_all('/\d[\d,]*(?:\.\d+)?/', $text, $m);
            $values = array_filter(array_map(fn ($v) => (float) str_replace(',', '', $v), $m[0]), fn ($v) => $v >= 10 && $v <= 500);

            return $values ? (int) round(max($values) * 8) : null;
        }
        $r = self::parseDayRate($text);

        return $r ? $r['max'] : null;
    }

    public static function isFlagged(JobApplication $a): bool
    {
        return str_starts_with(trim((string) $a->role), "\u{1F6A9}") || str_starts_with(trim((string) $a->company), "\u{1F6A9}");
    }

    public static function isNextStepCandidate(JobApplication $a): bool
    {
        return $a->removed_at === null
            && $a->date_applied === null
            && JobApplicationWriter::canonical((string) $a->status, self::NEXT_STEP_STATUSES) !== null;
    }

    /**
     * Not-yet-applied leads, best first: 🚩-prefixed roles, then highest day rate
     * (unknown rate last), then oldest added, then id.
     */
    public static function nextStepQueue(Collection $apps): Collection
    {
        return $apps->filter(fn ($a) => self::isNextStepCandidate($a))
            ->sort(function ($x, $y) {
                $rx = self::rankRate($x->rate);
                $ry = self::rankRate($y->rate);

                return [self::isFlagged($x) ? 0 : 1, $rx === null ? 1 : 0, -($rx ?? 0), $x->date_added?->toDateString() ?? '9999', $x->id]
                    <=> [self::isFlagged($y) ? 0 : 1, $ry === null ? 1 : 0, -($ry ?? 0), $y->date_added?->toDateString() ?? '9999', $y->id];
            })->values();
    }

    /**
     * Chronological progress log, newest first: tracker dates, dated phrases in the
     * "Next step / result" and "Notes" text, and recorded sync changes.
     *
     * @return array<int, array{date: string, kind: string, title: string, detail: ?string, app: ?JobApplication}>
     */
    public static function progressLog(Collection $apps, Collection $events, ?Carbon $today = null): array
    {
        $today = $today ?? Clock::today();
        $entries = [];
        $add = function (string $date, string $kind, string $title, ?string $detail, ?JobApplication $app) use (&$entries) {
            $k = ($app?->id ?? 0).'|'.$kind.'|'.$date;
            if (! isset($entries[$k])) {
                $entries[$k] = compact('date', 'kind', 'title', 'detail', 'app') + ['source' => null];
            }
        };

        foreach ($apps as $a) {
            if ($a->date_added) {
                $add($a->date_added->toDateString(), 'added', 'Added to tracker', $a->status, $a);
            }
            if ($a->date_applied) {
                $add($a->date_applied->toDateString(), 'applied', 'Applied', $a->source, $a);
            }
            if ($a->follow_up_date && $a->follow_up_date->lte($today)) {
                $add($a->follow_up_date->toDateString(), 'follow-up', 'Follow-up due', $a->next_step, $a);
            }
            foreach ([$a->next_step, $a->notes] as $text) {
                if (! $text) {
                    continue;
                }
                preg_match_all('/\b(Rejected|Closed as of|Applied|Interview(?:ed)?|Offer(?:ed)?|Withdrew|Shortlisted)\s+(?:on\s+)?(\d{1,2}\s+[A-Z][a-z]{2,8}\s+\d{4})\b[^;.]*/u', $text, $m, PREG_SET_ORDER);
                foreach ($m as $match) {
                    try {
                        $date = Carbon::parse($match[2])->toDateString();
                    } catch (\Throwable) {
                        continue;
                    }
                    $word = strtolower($match[1]);
                    $kind = match (true) {
                        str_starts_with($word, 'rejected') => 'rejected',
                        str_starts_with($word, 'closed') => 'closed',
                        str_starts_with($word, 'applied') => 'applied',
                        str_starts_with($word, 'offer') => 'offer',
                        str_starts_with($word, 'interview') => 'interview',
                        default => 'note',
                    };
                    $add($date, $kind, ucfirst($kind === 'note' ? $match[1] : $kind), trim($match[0]), $a);
                }
            }
        }

        foreach ($events as $e) {
            // A direct create already shows as "Added to tracker" on its Date added.
            if ($e->type === 'created' && $e->application?->date_added) {
                continue;
            }
            $date = $e->occurred_at->copy()->timezone(Clock::timezone())->toDateString();
            [$kind, $title, $detail] = match ($e->type) {
                'status' => ['status', 'Status changed', ($e->from_value ?? '—').' → '.($e->to_value ?? '—')],
                'applied' => ['applied', 'Applied', 'Date applied set to '.$e->to_value],
                'next_step' => ['note', 'Next step updated', $e->note],
                'created' => ['added', 'Added', $e->to_value],
                'follow_up' => ['follow-up', $e->to_value ? 'Follow-up set' : 'Follow-up cleared', $e->to_value ? Carbon::parse($e->to_value)->format('D j M').($e->note ? ' · '.$e->note : '') : null],
                'notes' => ['note', 'Notes updated', $e->note ? mb_strimwidth($e->note, 0, 200, '…') : null],
                'updated' => ['note', 'Details updated', $e->note],
                'removed' => ['removed', 'Removed from sheet', $e->from_value],
                'restored' => ['note', 'Back in sheet', null],
                default => ['note', ucfirst($e->type), $e->note],
            };
            $add($date, 'event-'.$e->id, $title, $detail, $e->application);
            $entries[array_key_last($entries)]['kind'] = $kind;
            $entries[array_key_last($entries)]['source'] = $e->source;
        }

        $list = array_values($entries);
        usort($list, fn ($x, $y) => strcmp($y['date'], $x['date']));

        return $list;
    }

    /** @return array<string, ?string> */
    private static function blankForm(): array
    {
        return array_fill_keys(JobApplicationWriter::FIELDS, null);
    }

    public function mount(): void
    {
        $this->normalizeCategoryFilter();
    }

    public function updatedCategory(): void
    {
        $this->normalizeCategoryFilter();
        $this->nextStepIndex = 0;
    }

    /** Client-side cycling calls this without a re-render so the stack animation is not blocked. */
    #[Renderless]
    public function rememberNextStep(int $index): void
    {
        $this->nextStepIndex = max(0, $index);
    }

    public function cycleNextStep(int $delta): void
    {
        $count = self::nextStepQueue($this->trackedApps())->count();
        if ($count < 2) {
            $this->nextStepIndex = 0;

            return;
        }

        $this->nextStepIndex = min($count - 1, max(0, $this->nextStepIndex + $delta));
    }

    private function trackedApps(): Collection
    {
        return JobApplication::query()->whereNull('removed_at')
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->get();
    }

    private function clampNextStepIndex(): void
    {
        $count = self::nextStepQueue($this->trackedApps())->count();
        if ($count === 0 || $this->nextStepIndex < 0) {
            $this->nextStepIndex = 0;
        } elseif ($this->nextStepIndex >= $count) {
            $this->nextStepIndex = $count - 1;
        }
    }

    private function normalizeCategoryFilter(): void
    {
        if (! in_array($this->category, ['all', ...JobApplicationWriter::CATEGORIES], true)) {
            $this->category = 'all';
        }
    }

    public static function isAppliedStatus(?string $status): bool
    {
        return strcasecmp((string) $status, 'Applied') === 0;
    }

    public function openCreate(): void
    {
        $this->resetErrorBag();
        $this->closeCoverLetter();
        $this->showCoverLetters = false;
        $this->editingId = null;
        $this->form = array_merge(self::blankForm(), [
            'status' => JobApplicationWriter::DEFAULT_STATUS,
            'category' => JobApplicationWriter::DEFAULT_CATEGORY,
            'date_added' => Clock::todayString(),
        ]);
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $app = JobApplication::query()->findOrFail($id);
        $this->resetErrorBag();
        $this->closeCoverLetter();
        $this->showCoverLetters = false;
        $this->viewingId = null;
        $this->editingId = $app->id;
        $this->form = JobApplicationWriter::values($app);
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->resetErrorBag();
        $this->showForm = false;
        $this->editingId = null;
        $this->form = [];
    }

    public function openView(int $id): void
    {
        $this->showCoverLetters = false;
        $this->closeChartSlice();
        $this->viewingId = JobApplication::query()->findOrFail($id)->id;
        Onboarding::complete('gigs.view');
    }

    public function closeView(): void
    {
        if ($this->coverLetterJobId !== null || $this->showCoverLetters) {
            return;
        }
        $this->viewingId = null;
    }

    public function openChartSlice(string $series, ?string $weekStart = null): void
    {
        $raw = trim($series);
        if ($raw === '') {
            return;
        }
        if (str_starts_with(strtolower($raw), 'type:')) {
            $type = trim(substr($raw, 5));
            if ($type === '') {
                return;
            }
            $this->chartSlice = 'type:'.$type;
        } else {
            $key = strtolower($raw);
            if (! isset(self::SUMMARY_SERIES[$key])) {
                return;
            }
            $this->chartSlice = $key;
        }
        $this->showCoverLetters = false;
        $this->viewingId = null;
        $this->chartWeek = $weekStart ? Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)->toDateString() : null;
    }

    public function closeChartSlice(): void
    {
        $this->chartSlice = null;
        $this->chartWeek = null;
    }

    /**
     * Jobs that make up one chart cell. Week filters use the same date field as the weekly series.
     *
     * @return Collection<int, JobApplication>
     */
    public static function appsForChartSlice(Collection $apps, string $series, ?string $weekStart = null): Collection
    {
        $series = strtolower($series);

        if ($weekStart !== null && $weekStart !== '') {
            $start = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->addWeek();
            $dateFor = match ($series) {
                'applied' => fn (JobApplication $a) => $a->date_applied,
                'responded' => fn (JobApplication $a) => self::respondedOn($a),
                'decided' => fn (JobApplication $a) => self::decidedOn($a),
                'contract' => fn (JobApplication $a) => strcasecmp((string) $a->type, 'Contract') === 0 ? $a->date_added : null,
                'permanent' => fn (JobApplication $a) => strcasecmp((string) $a->type, 'Permanent') === 0 ? $a->date_added : null,
                'added' => fn (JobApplication $a) => $a->date_added,
                default => fn (JobApplication $a) => null,
            };

            return $apps->filter(function (JobApplication $a) use ($dateFor, $start, $end) {
                $d = $dateFor($a);

                return $d !== null && $d->gte($start) && $d->lt($end);
            })->values();
        }

        if (str_starts_with($series, 'type:')) {
            $type = substr($series, 5);

            return $apps->filter(fn ($a) => strcasecmp((string) ($a->type ?: 'Unknown'), $type) === 0)->values();
        }

        return match ($series) {
            'added' => $apps->filter(fn ($a) => $a->date_added !== null)->values(),
            'applied' => $apps->filter(fn ($a) => self::isAppliedStatus($a->status))->values(),
            'responded' => $apps->filter(fn ($a) => self::respondedOn($a) !== null)->values(),
            'decided' => $apps->filter(fn ($a) => self::decidedOn($a) !== null)->values(),
            'contract' => $apps->filter(fn ($a) => strcasecmp((string) $a->type, 'Contract') === 0)->values(),
            'permanent' => $apps->filter(fn ($a) => strcasecmp((string) $a->type, 'Permanent') === 0)->values(),
            default => $apps->filter(fn () => false)->values(),
        };
    }

    public static function chartSliceTitle(string $series, ?string $weekStart = null): string
    {
        if (str_starts_with(strtolower($series), 'type:')) {
            $label = substr($series, 5);
        } else {
            $key = strtolower($series);
            $label = self::SUMMARY_SERIES[$key]['label'] ?? ucfirst($key);
        }
        if ($weekStart) {
            return $label.' · week of '.Carbon::parse($weekStart)->format('j M Y');
        }

        return $label;
    }

    public function openCoverLetter(int $id): void
    {
        $app = JobApplication::query()->findOrFail($id);
        $this->resetErrorBag();
        $this->showCoverLetters = false;
        $this->coverLetterJobId = $app->id;
        $this->coverLetterBody = (string) ($app->cover_letter ?? '');
        $this->coverLetterNotNeeded = (bool) $app->cover_letter_not_needed;
    }

    public function closeCoverLetter(): void
    {
        $this->resetErrorBag(['coverLetterBody', 'coverLetterNotNeeded']);
        $this->coverLetterJobId = null;
        $this->coverLetterBody = '';
        $this->coverLetterNotNeeded = false;
    }

    public function saveCoverLetter(): void
    {
        $app = JobApplication::query()->findOrFail((int) $this->coverLetterJobId);
        $validated = $this->validate([
            'coverLetterBody' => 'nullable|string|max:20000',
            'coverLetterNotNeeded' => 'boolean',
        ]);
        $body = trim((string) ($validated['coverLetterBody'] ?? ''));
        $notNeeded = (bool) $validated['coverLetterNotNeeded'];
        if ($body !== '') {
            $notNeeded = false;
        }

        $app->cover_letter = $body === '' ? null : $body;
        $app->cover_letter_not_needed = $notNeeded;
        $app->cover_letter_updated_at = now();
        $app->save();

        $this->flash = $notNeeded
            ? 'Marked cover letter as not needed for '.$app->label().'.'
            : ($body === '' ? 'Cleared the cover letter for '.$app->label().'.' : 'Saved cover letter for '.$app->label().'.');
        $this->closeCoverLetter();
    }

    public function openCoverLetters(): void
    {
        $this->closeCoverLetter();
        $this->showForm = false;
        $this->showCoverLetters = true;
        Onboarding::complete('gigs.letters');
    }

    public function closeCoverLetters(): void
    {
        $this->showCoverLetters = false;
    }

    public function markClosed(int $id): void
    {
        $app = JobApplication::query()->findOrFail($id);
        if ($app->isClosed()) {
            $this->flash = $app->label().' is already closed.';

            return;
        }

        JobApplicationWriter::update($app, ['status' => 'closed'], 'manual');
        $this->flash = 'Marked '.$app->fresh()->label().' as closed.';
        $this->clampNextStepIndex();
    }

    public function reopen(int $id): void
    {
        $app = JobApplication::query()->findOrFail($id);
        if (! $app->isClosed()) {
            $this->flash = $app->label().' is not closed.';

            return;
        }

        $status = $app->reopenStatus();
        JobApplicationWriter::update($app, ['status' => $status], 'manual');
        $this->flash = 'Reopened '.$app->fresh()->label().' as '.$app->fresh()->status.'.';
    }

    /** "Done, I applied" from Next Steps: status Applied + date applied today, logged like any manual edit. */
    public function markApplied(int $id): void
    {
        $app = JobApplication::query()->find($id);
        if (! $app || ! self::isNextStepCandidate($app)) {
            $this->flash = 'That role is no longer waiting to be applied to.';

            return;
        }
        JobApplicationWriter::update($app, ['status' => 'Applied', 'date_applied' => Clock::todayString()], 'manual');
        $this->flash = 'Marked '.$app->fresh()->label().' as applied.';
        $this->clampNextStepIndex();
    }

    public function save(): void
    {
        $input = array_intersect_key($this->form, array_flip(JobApplicationWriter::FIELDS));
        $input = array_map(fn ($v) => is_string($v) || $v === null ? $v : (string) $v, $input);

        try {
            if ($this->editingId) {
                $app = JobApplication::query()->findOrFail($this->editingId);
                $events = JobApplicationWriter::update($app, $input, 'manual');
                $this->flash = $events ? 'Saved '.$app->fresh()->label().'.' : 'No changes.';
            } else {
                [$app] = JobApplicationWriter::create($input, 'manual');
                $this->flash = 'Added '.$app->label().'.';
                Onboarding::complete('gigs.create');
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $m) {
                    $this->addError('form.'.$field, $m);
                }
            }

            return;
        }

        $this->closeForm();
    }

    public function render()
    {
        $today = Clock::today();
        $this->normalizeCategoryFilter();
        $apps = $this->trackedApps()
            ->loadCount('messages')
            ->loadExists(['messages as has_reply' => fn ($q) => $q->where('direction', 'received')])
            ->sortBy([
                fn ($a) => $a->sheet_row === null ? 1 : 0,
                fn ($a) => $a->sheet_row ?? PHP_INT_MAX,
                fn ($a) => $a->id,
            ])->values();

        $apps->load([
            'messages' => fn ($q) => $q->where('direction', 'received')->select('id', 'job_application_id', 'direction', 'occurred_at'),
            'events' => fn ($q) => $q->where('type', 'status')->select('id', 'job_application_id', 'type', 'to_value', 'occurred_at'),
        ]);

        $statusCounts = [
            'Added' => $apps->filter(fn ($a) => $a->date_added)->count(),
            'Applied' => $apps->filter(fn ($a) => self::isAppliedStatus($a->status))->count(),
            'Responded' => $apps->filter(fn ($a) => self::respondedOn($a) !== null)->count(),
            'Decided' => $apps->filter(fn ($a) => self::decidedOn($a) !== null)->count(),
            'Contract' => $apps->filter(fn ($a) => strcasecmp((string) $a->type, 'Contract') === 0)->count(),
            'Permanent' => $apps->filter(fn ($a) => strcasecmp((string) $a->type, 'Permanent') === 0)->count(),
        ];

        $typeCounts = $apps->groupBy(fn ($a) => $a->type ?: 'Unknown')->map->count()->sortDesc()->all();
        $typeTotal = array_sum($typeCounts);
        $stops = [];
        $acc = 0.0;
        foreach ($typeCounts as $t => $n) {
            $pct = $typeTotal ? $n / $typeTotal * 100 : 0;
            $stops[] = (self::TYPE_COLORS[$t] ?? '#6e7681').' '.round($acc, 3).'% '.round($acc + $pct, 3).'%';
            $acc += $pct;
        }

        $rates = [];
        $unparsed = 0;
        foreach ($apps->filter(fn ($a) => strcasecmp((string) $a->type, 'Contract') === 0) as $a) {
            $r = self::parseDayRate($a->rate);
            if ($r) {
                $rates[] = $r + ['app' => $a, 'mid' => ($r['min'] + $r['max']) / 2];
            } else {
                $unparsed++;
            }
        }
        usort($rates, fn ($x, $y) => $y['mid'] <=> $x['mid']);
        $mids = collect($rates)->pluck('mid')->sort()->values();
        $rateStats = $rates ? [
            'low' => min(array_column($rates, 'min')),
            'high' => max(array_column($rates, 'max')),
            'median' => (int) round($mids->count() % 2 ? $mids[intdiv($mids->count(), 2)] : ($mids[$mids->count() / 2 - 1] + $mids[$mids->count() / 2]) / 2),
        ] : null;
        $axisMin = $rateStats ? (int) (floor(($rateStats['low'] - 50) / 50) * 50) : 0;
        $axisMax = $rateStats ? (int) (ceil(($rateStats['high'] + 50) / 50) * 50) : 1;

        $weekly = self::weekly($apps, $today);
        $maxWeek = max(1, ...array_map(
            fn ($w) => max(...array_map(fn ($key) => $w[$key], array_keys(self::SUMMARY_SERIES))),
            $weekly ?: [array_fill_keys(array_keys(self::SUMMARY_SERIES), 0)]
        ));

        $followUps = $apps->filter(fn ($a) => ! $a->isClosed() && $a->follow_up_date && $a->follow_up_date->gte($today))
            ->sortBy(fn ($a) => $a->follow_up_date->toDateString())->values();

        $logApps = JobApplication::query()
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->whereNotNull('date_added')
            ->orderByDesc('date_added')
            ->orderByDesc('id')
            ->get();
        $log = $logApps->map(fn (JobApplication $a) => [
            'date' => $a->date_added->toDateString(),
            'kind' => 'added',
            'title' => 'Added to tracker',
            'detail' => $a->status,
            'app' => $a,
            'source' => null,
        ])->all();
        $queue = self::nextStepQueue($apps);
        $queueCount = $queue->count();
        if ($queueCount === 0 || $this->nextStepIndex < 0) {
            $this->nextStepIndex = 0;
        } elseif ($this->nextStepIndex >= $queueCount) {
            $this->nextStepIndex = $queueCount - 1;
        }

        $chartSliceApps = $this->chartSlice
            ? self::appsForChartSlice($apps, $this->chartSlice, $this->chartWeek)
            : collect();

        return view('livewire.job-applications', [
            'apps' => $apps,
            'statusCounts' => $statusCounts,
            'maxStatus' => max(1, ...array_values($statusCounts ?: [0])),
            'typeCounts' => $typeCounts,
            'typeTotal' => $typeTotal,
            'typeGradient' => $stops ? 'conic-gradient('.implode(', ', $stops).')' : null,
            'rates' => $rates,
            'rateStats' => $rateStats,
            'unparsedRates' => $unparsed,
            'axisMin' => $axisMin,
            'axisMax' => max($axisMin + 1, $axisMax),
            'weekly' => $weekly,
            'maxWeek' => $maxWeek,
            'followUps' => $followUps,
            'log' => $log,
            'nextUp' => $queueCount ? $queue[$this->nextStepIndex] : null,
            'nextQueue' => $queue,
            'nextQueueCount' => $queueCount,
            'chartSliceApps' => $chartSliceApps,
            'chartSliceTitle' => $this->chartSlice ? self::chartSliceTitle($this->chartSlice, $this->chartWeek) : null,
            'viewing' => $this->viewingId
                ? JobApplication::query()->withCount('messages')->find($this->viewingId)
                : null,
            'coverLetterJob' => $this->coverLetterJobId ? JobApplication::query()->find($this->coverLetterJobId) : null,
            'savedCoverLetters' => JobApplication::query()
                ->whereNotNull('cover_letter')
                ->where('cover_letter', '!=', '')
                ->orderByDesc('cover_letter_updated_at')
                ->orderByDesc('id')
                ->get(),
            'lastChange' => collect([JobApplication::query()->max('updated_at'), JobApplicationEvent::query()->max('occurred_at')])
                ->filter()->map(fn ($v) => Carbon::parse($v, 'UTC'))->max(),
            'knownStatuses' => JobApplicationWriter::KNOWN_STATUSES,
            'types' => JobApplicationWriter::TYPES,
            'categories' => JobApplicationWriter::CATEGORY_LABELS,
            'ir35Options' => JobApplicationWriter::IR35,
            'today' => $today,
            'tz' => Clock::timezone(),
        ]);
    }
}
