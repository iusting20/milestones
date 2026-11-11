<?php

namespace App\Support;

use App\Models\TaskLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finished Reech work sessions as read-only, unsaved TaskLog models, so the Log page
 * treats them like its own sessions (day lists, totals, charts). They carry a negative
 * id (-reech_id) and never touch the database. Any failure returns no sessions.
 */
class ReechTimelog
{
    public static function configured(): bool
    {
        return filled(config('reech_timelog.url')) && filled(config('reech_timelog.token'));
    }

    public static function isExternalId(int|string|null $id): bool
    {
        return is_numeric($id) && (int) $id < 0;
    }

    /**
     * @return array{configured: bool, available: bool, logs: Collection<int, TaskLog>}
     */
    public static function forDays(string $fromDate, string $toDate): array
    {
        if (! self::configured()) {
            return ['configured' => false, 'available' => false, 'logs' => collect()];
        }

        $key = 'reech-timelog:'.md5((string) config('reech_timelog.url')).":{$fromDate}:{$toDate}";
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            $payload = self::fetch($fromDate, $toDate);
            $ttl = $payload['ok']
                ? (int) config('reech_timelog.cache_seconds', 120)
                : (int) config('reech_timelog.failure_cache_seconds', 60);
            Cache::put($key, $payload, max(1, $ttl));
        }

        return [
            'configured' => true,
            'available' => (bool) $payload['ok'],
            'logs' => self::toLogs($payload['sessions'] ?? []),
        ];
    }

    /**
     * @return array{ok: bool, sessions: list<array<string, mixed>>}
     */
    private static function fetch(string $fromDate, string $toDate): array
    {
        try {
            $timeout = (float) config('reech_timelog.timeout', 3);
            $response = Http::acceptJson()
                ->withToken((string) config('reech_timelog.token'))
                ->connectTimeout(min(2, $timeout))
                ->timeout($timeout)
                ->get((string) config('reech_timelog.url'), ['from' => $fromDate, 'to' => $toDate]);

            $sessions = $response->json('sessions');

            if (! $response->successful() || ! is_array($sessions)) {
                Log::warning('Reech timelog unavailable', ['status' => $response->status()]);

                return ['ok' => false, 'sessions' => []];
            }

            return ['ok' => true, 'sessions' => array_values(array_filter($sessions, 'is_array'))];
        } catch (\Throwable $e) {
            Log::warning('Reech timelog unavailable', ['error' => $e->getMessage()]);

            return ['ok' => false, 'sessions' => []];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return Collection<int, TaskLog>
     */
    private static function toLogs(array $sessions): Collection
    {
        $titles = (array) config('reech_timelog.titles', []);

        return collect($sessions)
            ->map(function (array $s) use ($titles): ?TaskLog {
                $id = (int) ($s['id'] ?? 0);
                $title = $titles[$s['type'] ?? ''] ?? null;

                if ($id <= 0 || $title === null || empty($s['started_at']) || empty($s['ended_at'])) {
                    return null;
                }

                try {
                    $started = Carbon::parse((string) $s['started_at'])->utc();
                    $finished = Carbon::parse((string) $s['ended_at'])->utc();
                } catch (\Throwable) {
                    return null;
                }

                // Sub-minute sessions are accidental start/stop clicks; Milestones rounds every
                // session up to at least 1m, so they would otherwise inflate the totals.
                if ($started->diffInSeconds($finished) < 60) {
                    return null;
                }

                $log = new TaskLog;
                $log->forceFill([
                    'id' => -$id,
                    'todo_item_id' => null,
                    'title' => $title,
                    'started_at' => $started,
                    'finished_at' => $finished,
                ]);

                return $log;
            })
            ->filter()
            ->values();
    }
}
