<?php

namespace App\Livewire;

use App\Models\JobApplication;
use App\Models\JobApplicationMessage;
use App\Support\Clock;
use App\Support\JobApplicationWriter;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Secret (login-protected) page: one gig's cover letter and conversation thread. */
#[Title('Comm. Threads')]
class JobCommThread extends Component
{
    public JobApplication $application;

    public string $coverLetterBody = '';

    public bool $coverLetterNotNeeded = false;

    public ?string $flash = null;

    /** @var array{direction: string, occurred_on: string, occurred_time: string, subject: string, body: string} */
    public array $messageForm = [
        'direction' => 'sent',
        'occurred_on' => '',
        'occurred_time' => '',
        'subject' => '',
        'body' => '',
    ];

    public ?int $editingMessageId = null;

    public function mount(JobApplication $application): void
    {
        $this->application = $application;
        $this->coverLetterBody = (string) ($application->cover_letter ?? '');
        $this->coverLetterNotNeeded = (bool) $application->cover_letter_not_needed;
        $this->messageForm = $this->blankMessageForm();
    }

    public function saveCoverLetter(): void
    {
        $validated = $this->validate([
            'coverLetterBody' => 'nullable|string|max:20000',
            'coverLetterNotNeeded' => 'boolean',
        ]);
        $body = trim((string) ($validated['coverLetterBody'] ?? ''));
        $notNeeded = (bool) $validated['coverLetterNotNeeded'];
        if ($body !== '') {
            $notNeeded = false;
        }

        $this->application->cover_letter = $body === '' ? null : $body;
        $this->application->cover_letter_not_needed = $notNeeded;
        $this->application->cover_letter_updated_at = now();
        $this->application->save();
        $this->application->refresh();
        $this->coverLetterBody = (string) ($this->application->cover_letter ?? '');
        $this->coverLetterNotNeeded = (bool) $this->application->cover_letter_not_needed;

        $this->flash = $notNeeded
            ? 'Marked cover letter as not needed for '.$this->application->label().'.'
            : ($body === '' ? 'Cleared the cover letter for '.$this->application->label().'.' : 'Saved cover letter for '.$this->application->label().'.');
    }

    public function markClosed(): void
    {
        $this->application->refresh();
        if ($this->application->isClosed()) {
            $this->flash = $this->application->label().' is already closed.';

            return;
        }

        JobApplicationWriter::update($this->application, ['status' => 'closed'], 'manual');
        $this->application->refresh();
        $this->flash = 'Marked '.$this->application->label().' as closed.';
    }

    public function reopen(): void
    {
        $this->application->refresh();
        if (! $this->application->isClosed()) {
            $this->flash = $this->application->label().' is not closed.';

            return;
        }

        $status = $this->application->reopenStatus();
        JobApplicationWriter::update($this->application, ['status' => $status], 'manual');
        $this->application->refresh();
        $this->flash = 'Reopened '.$this->application->label().' as '.$this->application->status.'.';
    }

    /** @return array{direction: string, occurred_on: string, occurred_time: string, subject: string, body: string} */
    private function blankMessageForm(): array
    {
        return [
            'direction' => 'sent',
            'occurred_on' => Clock::todayString(),
            'occurred_time' => '',
            'subject' => '',
            'body' => '',
        ];
    }

    private function resetMessageForm(): void
    {
        $this->resetErrorBag(['messageForm.direction', 'messageForm.occurred_on', 'messageForm.occurred_time', 'messageForm.subject', 'messageForm.body']);
        $this->editingMessageId = null;
        $this->messageForm = $this->blankMessageForm();
    }

    public function startEditMessage(int $id): void
    {
        $message = $this->findMessage($id);
        $this->resetErrorBag(['messageForm.direction', 'messageForm.occurred_on', 'messageForm.occurred_time', 'messageForm.subject', 'messageForm.body']);
        $this->editingMessageId = $message->id;
        $local = $message->occurred_at->copy()->timezone(Clock::timezone());
        $this->messageForm = [
            'direction' => $message->direction,
            'occurred_on' => $local->toDateString(),
            'occurred_time' => $message->has_time ? $local->format('H:i') : '',
            'subject' => (string) ($message->subject ?? ''),
            'body' => (string) $message->body,
        ];
    }

    public function cancelEditMessage(): void
    {
        $this->resetMessageForm();
    }

    public function saveMessage(): void
    {
        $validated = $this->validate([
            'messageForm.direction' => 'required|in:'.implode(',', JobApplicationMessage::DIRECTIONS),
            'messageForm.occurred_on' => 'required|date_format:Y-m-d',
            'messageForm.occurred_time' => 'nullable|date_format:H:i',
            'messageForm.subject' => 'nullable|string|max:255',
            'messageForm.body' => 'required|string|max:20000',
        ]);
        $data = $validated['messageForm'];
        $subject = trim((string) ($data['subject'] ?? ''));
        $time = trim((string) ($data['occurred_time'] ?? ''));
        $hasTime = $time !== '';
        $local = $hasTime
            ? Carbon::parse($data['occurred_on'].' '.$time, Clock::timezone())
            : Carbon::parse($data['occurred_on'], Clock::timezone())->startOfDay();
        $payload = [
            'direction' => $data['direction'],
            'occurred_at' => $local->copy()->utc(),
            'has_time' => $hasTime,
            'subject' => $subject === '' ? null : $subject,
            'body' => trim((string) $data['body']),
        ];

        if ($this->editingMessageId) {
            $message = $this->findMessage($this->editingMessageId);
            $message->fill($payload)->save();
            $this->flash = 'Updated the conversation entry.';
        } else {
            $this->application->messages()->create($payload);
            $this->flash = 'Added a conversation entry for '.$this->application->label().'.';
        }

        $this->resetMessageForm();
    }

    public function deleteMessage(int $id): void
    {
        $this->findMessage($id)->delete();
        if ($this->editingMessageId === $id) {
            $this->resetMessageForm();
        }
        $this->flash = 'Removed the conversation entry.';
    }

    private function findMessage(int $id): JobApplicationMessage
    {
        return JobApplicationMessage::query()
            ->where('job_application_id', $this->application->id)
            ->findOrFail($id);
    }

    public function render()
    {
        $this->application->refresh();
        $messages = $this->application->messages()->orderBy('occurred_at')->orderBy('id')->get();

        return view('livewire.job-comm-thread', [
            'messages' => $messages,
            'tz' => Clock::timezone(),
        ]);
    }
}
