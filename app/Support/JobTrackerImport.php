<?php

namespace App\Support;

use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Models\JobSyncRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Upserts rows from a CSV export of the "Freelance Work Applications Tracker" sheet
 * and records what changed between syncs (status, next step, applied date, removals)
 * as job_application_events, so repeated syncs build a real progress log.
 */
class JobTrackerImport
{
    public const COLUMNS = [
        'Date added' => 'date_added',
        'Status' => 'status',
        'Date applied' => 'date_applied',
        'Company' => 'company',
        'Role' => 'role',
        'Type' => 'type',
        'Rate / Salary' => 'rate',
        'IR35' => 'ir35',
        'Location' => 'location',
        'Source / Recruiter' => 'source',
        'Link' => 'link',
        'Contact' => 'contact',
        'Next step / result' => 'next_step',
        'Follow-up date' => 'follow_up_date',
        'Notes' => 'notes',
    ];

    private const DATE_FIELDS = ['date_added', 'date_applied', 'follow_up_date'];

    /**
     * @return array<int, array<string, mixed>> rows keyed by db field, plus 'key' and 'sheet_row'
     */
    public static function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $h = fopen('php://temp', 'r+');
        fwrite($h, $csv);
        rewind($h);

        $header = fgetcsv($h, null, ',', '"', '');
        if (! is_array($header)) {
            throw new InvalidArgumentException('CSV is empty.');
        }
        $map = [];
        foreach ($header as $i => $name) {
            $name = trim((string) $name);
            if (isset(self::COLUMNS[$name])) {
                $map[$i] = self::COLUMNS[$name];
            }
        }
        if (! in_array('company', $map, true) || ! in_array('role', $map, true) || ! in_array('status', $map, true)) {
            throw new InvalidArgumentException('CSV header is missing Company, Role or Status.');
        }

        $rows = [];
        $seenKeys = [];
        $line = 1;
        while (($cells = fgetcsv($h, null, ',', '"', '')) !== false) {
            $line++;
            $row = array_fill_keys(array_values(self::COLUMNS), null);
            foreach ($map as $i => $field) {
                $v = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
                $row[$field] = $v === '' ? null : $v;
            }
            if ($row['company'] === null && $row['role'] === null) {
                continue;
            }
            foreach (self::DATE_FIELDS as $f) {
                $row[$f] = self::date($row[$f]);
            }
            $base = sha1(mb_strtolower((string) $row['company']).'|'.mb_strtolower((string) $row['role']).'|'.$row['date_added'].'|'.mb_strtolower((string) $row['link']));
            $key = $base;
            $n = 1;
            while (isset($seenKeys[$key])) {
                $n++;
                $key = sha1($base.'#'.$n);
            }
            $seenKeys[$key] = true;
            $row['key'] = $key;
            $row['sheet_row'] = $line;
            $rows[] = $row;
        }
        fclose($h);

        return $rows;
    }

    public static function date(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'j M Y', 'd M Y', 'j F Y'] as $f) {
            $d = \DateTime::createFromFormat('!'.$f, $v);
            if ($d && $d->format($f) === $v) {
                return $d->format('Y-m-d');
            }
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{rows: int, created: int, updated: int, status_changes: int, removed: int}
     */
    public static function import(string $csv, ?string $source = null, ?Carbon $now = null): array
    {
        $rows = self::parse($csv);
        $now = ($now ?? Carbon::now())->copy()->utc();

        return DB::transaction(function () use ($rows, $source, $now) {
            $stats = ['rows' => count($rows), 'created' => 0, 'updated' => 0, 'status_changes' => 0, 'removed' => 0];
            $keys = [];

            foreach ($rows as $row) {
                $keys[] = $row['key'];
                $app = JobApplication::query()->where('key', $row['key'])->first();
                $fields = $row;
                unset($fields['key']);

                if (! $app) {
                    $row['category'] = JobApplicationWriter::inferCategory($row['role']);
                    JobApplication::query()->create($row + ['first_seen_at' => $now, 'last_seen_at' => $now]);
                    $stats['created']++;

                    continue;
                }

                $events = [];
                if ($app->removed_at !== null) {
                    $events[] = ['type' => 'restored', 'note' => 'Back in the sheet'];
                }
                if ((string) $app->status !== (string) $row['status']) {
                    $events[] = ['type' => 'status', 'from_value' => $app->status, 'to_value' => $row['status']];
                    $stats['status_changes']++;
                }
                if ($app->date_applied?->toDateString() !== $row['date_applied'] && $row['date_applied'] !== null) {
                    $events[] = ['type' => 'applied', 'from_value' => $app->date_applied?->toDateString(), 'to_value' => $row['date_applied']];
                }
                if ((string) $app->next_step !== (string) $row['next_step'] && $row['next_step'] !== null) {
                    $events[] = ['type' => 'next_step', 'from_value' => self::short($app->next_step), 'to_value' => self::short($row['next_step']), 'note' => $row['next_step']];
                }

                $app->fill($fields + ['last_seen_at' => $now, 'removed_at' => null]);
                if ($app->isDirty() || $events) {
                    $stats['updated']++;
                }
                $app->save();

                foreach ($events as $e) {
                    JobApplicationEvent::query()->create($e + ['job_application_id' => $app->id, 'occurred_at' => $now, 'source' => 'sync']);
                }
            }

            // Only sheet-imported rows can drop out of the sheet; rows created on the page/API never do.
            JobApplication::query()->where('origin', 'sheet')->whereNull('removed_at')->whereNotIn('key', $keys)->get()
                ->each(function (JobApplication $app) use ($now, &$stats) {
                    $app->update(['removed_at' => $now]);
                    JobApplicationEvent::query()->create([
                        'job_application_id' => $app->id, 'type' => 'removed', 'from_value' => $app->status,
                        'note' => 'No longer in the sheet', 'occurred_at' => $now, 'source' => 'sync',
                    ]);
                    $stats['removed']++;
                });

            JobSyncRun::query()->create($stats + ['synced_at' => $now, 'source' => $source]);

            return $stats;
        });
    }

    private static function short(?string $v): ?string
    {
        return $v === null ? null : mb_strimwidth($v, 0, 250, '…');
    }
}
