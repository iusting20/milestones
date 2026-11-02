<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Idle gaps within a working window (08:00–17:00 display time) for one day.
 */
class IdleTimeline
{
    public const START_HOUR = 8;

    public const END_HOUR = 17;

    /**
     * @param  iterable<array{0: CarbonInterface, 1: CarbonInterface}>  $intervals  busy intervals (any timezone)
     * @return array{segments: array<int, array{left: float, width: float, from: string, to: string, minutes: int, label: string}>, idleMinutes: int, idleDuration: string, future: ?array{left: float, width: float}, ticks: array<int, array{hour: int, left: float}>}
     */
    public static function forDay(string $date, iterable $intervals, ?CarbonInterface $now = null): array
    {
        $tz = Clock::timezone();
        $windowStart = Carbon::parse($date, $tz)->setTime(self::START_HOUR, 0)->getTimestamp();
        $windowEnd = Carbon::parse($date, $tz)->setTime(self::END_HOUR, 0)->getTimestamp();
        $total = $windowEnd - $windowStart;
        $now = ($now ?? Carbon::now())->getTimestamp();
        $limit = min($windowEnd, max($windowStart, $now));

        $busy = [];
        foreach ($intervals as [$from, $to]) {
            $a = max($windowStart, $from->getTimestamp());
            $b = min($limit, $to->getTimestamp());
            if ($b > $a) {
                $busy[] = [$a, $b];
            }
        }
        usort($busy, fn ($x, $y) => $x[0] <=> $y[0]);

        $merged = [];
        foreach ($busy as $iv) {
            $last = count($merged) - 1;
            if ($last >= 0 && $iv[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $iv[1]);
            } else {
                $merged[] = $iv;
            }
        }

        $gaps = [];
        $cursor = $windowStart;
        foreach ($merged as [$a, $b]) {
            if ($a > $cursor) {
                $gaps[] = [$cursor, $a];
            }
            $cursor = max($cursor, $b);
        }
        if ($limit > $cursor) {
            $gaps[] = [$cursor, $limit];
        }

        $segments = [];
        $idleSeconds = 0;
        foreach ($gaps as [$a, $b]) {
            $minutes = intdiv($b - $a, 60);
            if ($minutes < 1) {
                continue;
            }
            $idleSeconds += $b - $a;
            $from = Carbon::createFromTimestamp($a, $tz)->format('H:i');
            $to = Carbon::createFromTimestamp($b, $tz)->format('H:i');
            $segments[] = [
                'left' => round(($a - $windowStart) / $total * 100, 3),
                'width' => round(($b - $a) / $total * 100, 3),
                'from' => $from,
                'to' => $to,
                'minutes' => $minutes,
                'label' => "Idle {$from}–{$to} (".self::duration($minutes).')',
            ];
        }

        $ticks = [];
        for ($h = self::START_HOUR; $h <= self::END_HOUR; $h++) {
            $ticks[] = ['hour' => $h, 'left' => round(($h - self::START_HOUR) / (self::END_HOUR - self::START_HOUR) * 100, 3)];
        }

        $idleMinutes = intdiv($idleSeconds, 60);

        return [
            'segments' => $segments,
            'idleMinutes' => $idleMinutes,
            'idleDuration' => self::duration($idleMinutes),
            'future' => $limit < $windowEnd
                ? ['left' => round(($limit - $windowStart) / $total * 100, 3), 'width' => round(($windowEnd - $limit) / $total * 100, 3)]
                : null,
            'ticks' => $ticks,
        ];
    }

    /**
     * Running-timer spans clipped to the day's window and to now.
     *
     * @param  iterable<array{0: CarbonInterface, 1: CarbonInterface, 2: string}>  $timers  [start, end, title]
     * @return array<int, array{left: float, width: float, label: string}>
     */
    public static function runningSegments(string $date, iterable $timers, ?CarbonInterface $now = null): array
    {
        $tz = Clock::timezone();
        $windowStart = Carbon::parse($date, $tz)->setTime(self::START_HOUR, 0)->getTimestamp();
        $windowEnd = Carbon::parse($date, $tz)->setTime(self::END_HOUR, 0)->getTimestamp();
        $total = $windowEnd - $windowStart;
        $limit = min($windowEnd, ($now ?? Carbon::now())->getTimestamp());

        $segments = [];
        foreach ($timers as [$from, $to, $title]) {
            $a = max($windowStart, $from->getTimestamp());
            $b = min($limit, $to->getTimestamp());
            if ($b <= $a) {
                continue;
            }
            $segments[] = [
                'left' => round(($a - $windowStart) / $total * 100, 3),
                'width' => round(($b - $a) / $total * 100, 3),
                'label' => 'Running: '.$title.' since '.Carbon::createFromTimestamp($from->getTimestamp(), $tz)->format('H:i'),
            ];
        }

        return $segments;
    }

    private static function duration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? ($m > 0 ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";
    }
}
