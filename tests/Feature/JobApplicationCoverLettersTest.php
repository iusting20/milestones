<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\JobApplicationController;
use App\Livewire\JobApplications;
use App\Models\JobApplication;
use App\Models\JobApplicationMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JobApplicationCoverLettersTest extends TestCase
{
    use RefreshDatabase;

    private function url(): string
    {
        return '/x/'.config('jobs.page_token');
    }

    private function job(string $key, array $attrs = []): JobApplication
    {
        return JobApplication::query()->create($attrs + [
            'key' => $key, 'company' => 'Co '.$key, 'role' => 'Engineer '.$key,
            'category' => 'software', 'status' => 'To apply', 'type' => 'Contract', 'date_added' => '2026-11-01',
        ]);
    }

    public function test_cover_letter_button_is_next_to_applied_and_saves_text(): void
    {
        $job = $this->job('cl', ['company' => 'CoverCo', 'role' => 'Cover Role', 'rate' => '£800/day']);

        $this->get($this->url())->assertOk()
            ->assertSee('My cover letters')
            ->assertSee('Done, I applied')
            ->assertSee('Cover letter')
            ->assertSee('data-cover-letter-btn="'.$job->id.'"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        Livewire::test(JobApplications::class)
            ->assertSee('Cover letter')
            ->assertDontSee('data-cover-letter-modal', false)
            ->call('openCoverLetter', $job->id)
            ->assertSet('coverLetterJobId', $job->id)
            ->assertSee('Paste your cover letter here')
            ->assertSee('CoverCo')
            ->set('coverLetterBody', "Dear hiring team,\n\nI would like to apply.")
            ->call('saveCoverLetter')
            ->assertSet('coverLetterJobId', null)
            ->assertSee('Saved cover letter for Cover Role · CoverCo')
            ->assertSee('Cover letter ✓');

        $job->refresh();
        $this->assertSame("Dear hiring team,\n\nI would like to apply.", $job->cover_letter);
        $this->assertFalse($job->cover_letter_not_needed);
        $this->assertNotNull($job->cover_letter_updated_at);
    }

    public function test_cover_letter_can_be_marked_not_needed(): void
    {
        $job = $this->job('none', ['company' => 'SkipCo', 'rate' => '£700/day']);

        Livewire::test(JobApplications::class)
            ->call('openCoverLetter', $job->id)
            ->set('coverLetterNotNeeded', true)
            ->set('coverLetterBody', '')
            ->call('saveCoverLetter')
            ->assertSee('Marked cover letter as not needed for Engineer none · SkipCo')
            ->assertSee('No cover letter');

        $job->refresh();
        $this->assertTrue($job->cover_letter_not_needed);
        $this->assertNull($job->cover_letter);
    }

    public function test_saving_a_letter_clears_the_not_needed_flag(): void
    {
        $job = $this->job('needed', ['cover_letter_not_needed' => true]);

        Livewire::test(JobApplications::class)
            ->call('openCoverLetter', $job->id)
            ->assertSet('coverLetterNotNeeded', true)
            ->set('coverLetterBody', 'Here is the letter.')
            ->set('coverLetterNotNeeded', true)
            ->call('saveCoverLetter')
            ->assertSee('Saved cover letter');

        $job->refresh();
        $this->assertSame('Here is the letter.', $job->cover_letter);
        $this->assertFalse($job->cover_letter_not_needed);
    }

    public function test_my_cover_letters_lists_saved_letters_and_opens_the_job(): void
    {
        $with = $this->job('with', [
            'company' => 'ListedCo', 'role' => 'Listed Role',
            'link' => 'https://jobs.example.com/listed',
            'cover_letter' => 'The listed cover letter body.',
            'cover_letter_updated_at' => '2026-11-18 10:00:00',
        ]);
        $this->job('empty');
        $this->job('skipped', ['cover_letter_not_needed' => true]);

        Livewire::test(JobApplications::class)
            ->assertSee('My cover letters')
            ->assertSee('(1)')
            ->call('openCoverLetters')
            ->assertSet('showCoverLetters', true)
            ->assertSee('My cover letters')
            ->assertSee('1 saved')
            ->assertSee('Listed Role · ListedCo')
            ->assertSee('The listed cover letter body.')
            ->call('openView', $with->id)
            ->assertSet('showCoverLetters', false)
            ->assertSet('viewingId', $with->id)
            ->assertSee('Open application')
            ->assertSee('ListedCo')
            ->assertSee('Conversation');
    }

    public function test_conversation_history_can_be_added_edited_and_deleted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 18:00:00', 'Europe/Bucharest'));
        $job = $this->job('msg', ['company' => 'MsgCo', 'role' => 'Msg Role']);

        $c = Livewire::test(JobApplications::class)
            ->call('openView', $job->id)
            ->assertSee('Conversation')
            ->assertSee('No emails logged yet')
            ->set('messageForm.direction', 'sent')
            ->set('messageForm.occurred_at', '2026-11-17T09:15')
            ->set('messageForm.subject', 'Application for Msg Role')
            ->set('messageForm.body', 'Hello, please find my CV attached.')
            ->call('saveMessage')
            ->assertSee('Added a conversation entry for Msg Role · MsgCo')
            ->assertSee('Sent by me')
            ->assertSee('Application for Msg Role')
            ->assertSee('Hello, please find my CV attached.')
            ->assertSee('Tue 17 Nov 2026, 09:15')
            ->assertDontSee('No emails logged yet');

        $this->assertSame(1, $job->messages()->count());
        $message = $job->messages()->first();
        $this->assertSame('sent', $message->direction);
        $this->assertSame('Application for Msg Role', $message->subject);
        $this->assertSame('2026-11-17 07:15:00', $message->occurred_at->copy()->utc()->format('Y-m-d H:i:s'));

        $c->set('messageForm.direction', 'received')
            ->set('messageForm.occurred_at', '2026-11-18T14:00')
            ->set('messageForm.subject', 'Re: Application for Msg Role')
            ->set('messageForm.body', 'Thanks, we will be in touch.')
            ->call('saveMessage')
            ->assertSee('Received')
            ->assertSee('Thanks, we will be in touch.')
            ->assertSee('Wed 18 Nov 2026, 14:00');

        $this->assertSame(['sent', 'received'], $job->messages()->orderBy('occurred_at')->pluck('direction')->all());

        $firstId = $message->id;
        $c->call('startEditMessage', $firstId)
            ->assertSet('editingMessageId', $firstId)
            ->set('messageForm.body', 'Hello, please find my CV and portfolio attached.')
            ->call('saveMessage')
            ->assertSee('Updated the conversation entry.')
            ->assertSee('Hello, please find my CV and portfolio attached.')
            ->assertDontSee('Hello, please find my CV attached.');

        $replyId = $job->messages()->where('direction', 'received')->value('id');
        $c->call('deleteMessage', $replyId)
            ->assertSee('Removed the conversation entry.')
            ->assertDontSee('Thanks, we will be in touch.');
        $this->assertSame(1, $job->messages()->count());

        $this->get($this->url())->assertOk()->assertSee('1 message');

        Carbon::setTestNow();
    }

    public function test_message_validation_requires_a_body_and_a_valid_direction(): void
    {
        $job = $this->job('val');

        Livewire::test(JobApplications::class)
            ->call('openView', $job->id)
            ->set('messageForm.direction', 'sideways')
            ->set('messageForm.occurred_at', '2026-11-18T12:00')
            ->set('messageForm.body', '')
            ->call('saveMessage')
            ->assertHasErrors(['messageForm.direction', 'messageForm.body']);

        $this->assertSame(0, JobApplicationMessage::query()->count());
    }

    public function test_cover_letter_and_messages_stay_off_the_jobs_api(): void
    {
        $job = $this->job('api', [
            'company' => 'ApiCo',
            'cover_letter' => 'A secret letter.',
            'cover_letter_not_needed' => false,
            'cover_letter_updated_at' => '2026-11-18 10:00:00',
        ]);
        JobApplicationMessage::query()->create([
            'job_application_id' => $job->id,
            'direction' => 'sent',
            'occurred_at' => '2026-11-17 10:00:00',
            'subject' => 'Hi',
            'body' => 'Hello',
        ]);

        $this->flushSession();
        $res = $this->getJson('/api/jobs/'.$job->id, [
            'Authorization' => 'Bearer '.config('jobs.api_token'),
            'Accept' => 'application/json',
        ]);
        $res->assertOk()->assertJsonPath('data.company', 'ApiCo');
        $keys = array_keys($res->json('data'));
        $this->assertNotContains('cover_letter', $keys);
        $this->assertNotContains('cover_letter_not_needed', $keys);
        $this->assertNotContains('cover_letter_updated_at', $keys);
        $this->assertNotContains('messages', $keys);

        $presented = JobApplicationController::present($job->fresh(), true);
        $this->assertArrayNotHasKey('cover_letter', $presented);
        $this->assertArrayNotHasKey('messages', $presented);
    }

    public function test_cover_letter_button_is_also_in_the_job_details_modal(): void
    {
        $job = $this->job('modal-cl', [
            'company' => 'ModalLetterCo',
            'link' => 'https://jobs.example.com/letter',
        ]);

        Livewire::test(JobApplications::class)
            ->call('openView', $job->id)
            ->assertSee('Cover letter')
            ->assertSee('Conversation')
            ->call('openCoverLetter', $job->id)
            ->assertSet('coverLetterJobId', $job->id)
            ->assertSet('viewingId', $job->id)
            ->assertSee('Paste your cover letter here');
    }
}
