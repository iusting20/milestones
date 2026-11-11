<?php

namespace App\Livewire;

use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Models\JobSyncRun;
use App\Support\Clock;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Secret (unlinked, login-protected) dashboard for freelance/job applications. */
#[Title('Applications')]
class JobApplications extends Component
{
    public const STATUS_COLORS = [
        'Applied' => '#f0883e',
        'To apply' => '#39c5cf',
        'Low priority (perm)' => '#6e7681',
        'closed' => '#da3633',
    ];

    public const OTHER_COLOR = '#f778ba';

    public const TYPE_COLORS = [
        'Contract' => '#39c5cf',
        'Permanent' => '#f778ba',
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
     * Weekly counts (Mon-start weeks) of applications by Date added and Date applied.
     *
     * @return array<int, array{start: string, label: string, added: int, applied: int}>
     */
    public static function weekly(Collection $apps, ?Carbon $today = null): array
    {
        $today = $today ?? Clock::today();
        $dates = $apps->flatMap(fn ($a) => array_filter([$a->date_added, $a->date_applied]));
        if ($dates->isEmpty()) {
            return [];
        }
        $start = $dates->min()->copy()->startOfWeek(Carbon::MONDAY);
        $end = $today->copy()->startOfWeek(Carbon::MONDAY);
        $last = $dates->max()->copy()->startOfWeek(Carbon::MONDAY);
        if ($last->gt($end)) {
            $end = $last;
        }

        $weeks = [];
        for ($w = $start->copy(); $w->lte($end); $w->addWeek()) {
            $weeks[$w->toDateString()] = ['start' => $w->toDateString(), 'label' => $w->format('j M'), 'added' => 0, 'applied' => 0];
        }
        foreach ($apps as $a) {
            foreach (['added' => $a->date_added, 'applied' => $a->date_applied] as $series => $d) {
                if ($d) {
                    $k = $d->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                    if (isset($weeks[$k])) {
                        $weeks[$k][$series]++;
                    }
                }
            }
        }

        return array_values($weeks);
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
                $entries[$k] = compact('date', 'kind', 'title', 'detail', 'app');
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
            $date = $e->occurred_at->copy()->timezone(Clock::timezone())->toDateString();
            [$kind, $title, $detail] = match ($e->type) {
                'status' => ['status', 'Status changed', ($e->from_value ?? '—').' → '.($e->to_value ?? '—')],
                'applied' => ['applied', 'Applied', 'Date applied set to '.$e->to_value],
                'next_step' => ['note', 'Next step updated', $e->note],
                'removed' => ['removed', 'Removed from sheet', $e->from_value],
                'restored' => ['note', 'Back in sheet', null],
                default => ['note', ucfirst($e->type), $e->note],
            };
            $add($date, 'event-'.$e->id, $title, $detail, $e->application);
            $entries[array_key_last($entries)]['kind'] = $kind;
        }

        $list = array_values($entries);
        usort($list, fn ($x, $y) => strcmp($y['date'], $x['date']));

        return $list;
    }

    public function render()
    {
        $today = Clock::today();
        $apps = JobApplication::query()->whereNull('removed_at')->orderBy('sheet_row')->get();

        $statusCounts = [];
        foreach (array_keys(self::STATUS_COLORS) as $s) {
            $statusCounts[$s] = 0;
        }
        foreach ($apps as $a) {
            $s = $a->status ?: 'No status';
            $match = collect(array_keys(self::STATUS_COLORS))->first(fn ($k) => strcasecmp($k, $s) === 0);
            $statusCounts[$match ?? $s] = ($statusCounts[$match ?? $s] ?? 0) + 1;
        }
        $statusCounts = array_filter($statusCounts);

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
        $maxWeek = max(1, ...array_map(fn ($w) => max($w['added'], $w['applied']), $weekly ?: [['added' => 0, 'applied' => 0]]));

        $followUps = $apps->filter(fn ($a) => $a->follow_up_date && $a->follow_up_date->gte($today))
            ->sortBy(fn ($a) => $a->follow_up_date->toDateString())->values();

        $events = JobApplicationEvent::query()->with('application')->orderBy('occurred_at')->get();
        $log = self::progressLog(JobApplication::query()->get(), $events, $today);

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
            'lastSync' => JobSyncRun::query()->latest('synced_at')->latest('id')->first(),
            'today' => $today,
            'tz' => Clock::timezone(),
        ]);
    }
}
