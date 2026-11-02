<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Calendar "now" for the app. Storage stays in UTC (app.timezone); anything that
 * decides which day, week or month it is uses the display timezone (Europe/Bucharest).
 */
class Clock
{
    public static function timezone(): string
    {
        return (string) config('app.display_timezone', 'Europe/Bucharest');
    }

    /**
     * Current moment in the display timezone. Use for formatting/calendar keys only,
     * never for values written to the database.
     */
    public static function now(): Carbon
    {
        return Carbon::now()->timezone(self::timezone());
    }

    /**
     * Today's display-timezone date as a midnight Carbon in the app timezone, so it
     * compares correctly with `date` columns (stored as plain Y-m-d).
     */
    public static function today(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', self::todayString(), config('app.timezone'))->startOfDay();
    }

    public static function todayString(): string
    {
        return self::now()->toDateString();
    }

    public static function currentWeek(): string
    {
        return self::now()->format('o-\WW');
    }

    public static function currentMonth(): string
    {
        return self::now()->format('Y-m');
    }
}
