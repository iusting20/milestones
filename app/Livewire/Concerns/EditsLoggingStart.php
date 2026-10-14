<?php

namespace App\Livewire\Concerns;

use Carbon\Carbon;

trait EditsLoggingStart
{
    public ?int $editingLoggingId = null;

    public string $editLoggingStartedAt = '';

    public function startEditingLoggingStart(int $id): void
    {
        $item = $this->todoItem($id);

        if ($item->is_separator || ! $item->isLogging()) {
            return;
        }

        $this->resetValidation();
        $this->editingLoggingId = $item->id;
        $this->editLoggingStartedAt = $item->logging_started_at
            ->timezone('Europe/Bucharest')
            ->format('H:i');
    }

    public function finishEditingLoggingStart(): void
    {
        if (! $this->editingLoggingId) {
            return;
        }

        $this->editLoggingStartedAt = $this->normalizeLoggingTime($this->editLoggingStartedAt);

        $this->validate([
            'editLoggingStartedAt' => ['required', 'date_format:H:i'],
        ]);

        $item = $this->todoItem($this->editingLoggingId);

        if ($item->is_separator || ! $item->isLogging()) {
            $this->cancelEditingLoggingStart();

            return;
        }

        $timezone = 'Europe/Bucharest';
        $started = $item->logging_started_at->timezone($timezone);
        $updated = Carbon::createFromFormat(
            'Y-m-d H:i',
            $started->toDateString().' '.$this->editLoggingStartedAt,
            $timezone,
        );

        $item->update([
            'logging_started_at' => $updated->clone()->utc(),
        ]);

        $this->cancelEditingLoggingStart();
    }

    public function cancelEditingLoggingStart(): void
    {
        $this->editingLoggingId = null;
        $this->editLoggingStartedAt = '';
        $this->resetValidation('editLoggingStartedAt');
    }

    private function normalizeLoggingTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $matches) !== 1) {
            return $time;
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }
}
