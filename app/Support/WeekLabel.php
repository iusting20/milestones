<?php

namespace App\Support;

use Carbon\CarbonInterface;

class WeekLabel
{
    /**
     * Monday-Sunday range of the week starting at $monday: "2-8 Nov", "30 Nov-6 Dec", "29 Dec 2025-4 Jan 2026".
     */
    public static function range(CarbonInterface $monday): string
    {
        $start = $monday->copy()->startOfDay();
        $end = $start->copy()->addDays(6);

        if ($start->year !== $end->year) {
            return $start->format('j M Y').'-'.$end->format('j M Y');
        }
        if ($start->month !== $end->month) {
            return $start->format('j M').'-'.$end->format('j M');
        }

        return $start->format('j').'-'.$end->format('j M');
    }
}
