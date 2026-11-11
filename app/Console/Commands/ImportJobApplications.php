<?php

namespace App\Console\Commands;

use App\Support\JobTrackerImport;
use Illuminate\Console\Command;

class ImportJobApplications extends Command
{
    protected $signature = 'jobs:import {path : CSV export of the Freelance Work Tracker tab} {--source=Google Sheet export}';

    protected $description = 'Sync the freelance/job applications tracker from a CSV export (upsert + record status changes)';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        try {
            $stats = JobTrackerImport::import((string) file_get_contents($path), (string) $this->option('source'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Synced %d rows: %d new, %d updated, %d status changes, %d removed.',
            $stats['rows'], $stats['created'], $stats['updated'], $stats['status_changes'], $stats['removed'],
        ));

        return self::SUCCESS;
    }
}
