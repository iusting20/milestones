<?php

namespace App\Livewire;

use App\Models\ReadingSession;
use App\Support\Clock;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reading stats')]
class ReadStats extends Component
{
    public const TYPE_COLORS = [
        'A' => '#58a6ff',
        'B' => '#ffe600',
        'C' => '#3fb950',
        '—' => '#8b8b8b',
    ];

    /**
     * Minutes read per local day (Mon..Sun of the current week) and per book type.
     * Sessions spanning midnight are split across days; a live session counts up to now.
     *
     * @return array{days: array<int, array{label: string, date: string, minutes: array<string, int>}>, totals: array<string, int>}
     */
    public static function weekBreakdown(?Carbon $now = null): array
    {
        $tz = Clock::timezone();
        $now = ($now ?? Clock::now())->copy()->timezone($tz);
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(7);

        $types = array_keys(self::TYPE_COLORS);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $weekStart->copy()->addDays($i);
            $days[$i] = [
                'label' => $d->format('D'),
                'date' => $d->format('j M'),
                'minutes' => array_fill_keys($types, 0),
                'seconds' => array_fill_keys($types, 0),
            ];
        }

        $sessions = ReadingSession::query()
            ->with('book:id,type')
            ->where('started_at', '<', $weekEnd->copy()->utc())
            ->where(function ($q) use ($weekStart) {
                $q->whereNull('finished_at')->orWhere('finished_at', '>', $weekStart->copy()->utc());
            })
            ->get();

        foreach ($sessions as $session) {
            $type = $session->book?->type && isset(self::TYPE_COLORS[$session->book->type]) ? $session->book->type : '—';
            $start = $session->started_at->copy()->timezone($tz);
            $end = ($session->finished_at ?? $now)->copy()->timezone($tz);
            if ($end->gt($now)) {
                $end = $now->copy();
            }
            for ($i = 0; $i < 7; $i++) {
                $dayStart = $weekStart->copy()->addDays($i);
                $dayEnd = $dayStart->copy()->addDay();
                $from = $start->gt($dayStart) ? $start : $dayStart;
                $to = $end->lt($dayEnd) ? $end : $dayEnd;
                if ($to->gt($from)) {
                    $days[$i]['seconds'][$type] += $from->diffInSeconds($to);
                }
            }
        }

        $totals = array_fill_keys($types, 0);
        foreach ($days as $i => $day) {
            foreach ($types as $t) {
                $m = intdiv((int) $day['seconds'][$t], 60);
                $days[$i]['minutes'][$t] = $m;
                $totals[$t] += $m;
            }
            unset($days[$i]['seconds']);
        }

        return ['days' => $days, 'totals' => $totals];
    }

    public static function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h === 0) {
            return $m.'m';
        }

        return $m === 0 ? $h.'h' : $h.'h '.$m.'m';
    }

    public function render()
    {
        $data = self::weekBreakdown();
        $types = array_keys(self::TYPE_COLORS);

        // Only show the "no type" series when it actually has time.
        if ($data['totals']['—'] === 0) {
            $types = array_values(array_diff($types, ['—']));
        }

        $maxDay = 0;
        foreach ($data['days'] as $day) {
            foreach ($types as $t) {
                $maxDay = max($maxDay, $day['minutes'][$t]);
            }
        }

        $weekTotal = array_sum(array_intersect_key($data['totals'], array_flip($types)));

        // conic-gradient stops for the pie
        $stops = [];
        $acc = 0.0;
        foreach ($types as $t) {
            if ($weekTotal === 0 || $data['totals'][$t] === 0) {
                continue;
            }
            $pct = $data['totals'][$t] / $weekTotal * 100;
            $stops[] = self::TYPE_COLORS[$t].' '.round($acc, 3).'% '.round($acc + $pct, 3).'%';
            $acc += $pct;
        }

        return view('livewire.read-stats', [
            'days' => $data['days'],
            'totals' => $data['totals'],
            'types' => $types,
            'colors' => self::TYPE_COLORS,
            'maxDay' => max(1, $maxDay),
            'weekTotal' => $weekTotal,
            'pieGradient' => $stops ? 'conic-gradient('.implode(', ', $stops).')' : null,
            'today' => Clock::now()->timezone(Clock::timezone())->format('D'),
        ]);
    }
}
