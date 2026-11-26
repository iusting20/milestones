<?php

namespace Tests\Feature;

use App\Livewire\JobApplications;
use App\Livewire\JobCommThread;
use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Models\JobApplicationMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JobApplicationCloseAndThreadsTest extends TestCase
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

    public function test_gigs_page_has_mark_as_closed_and_comm_threads_and_is_not_indexed(): void
    {
        $open = $this->job('open', ['company' => 'OpenCo', 'role' => 'Open Role']);
        $closed = $this->job('already', ['company' => 'ShutCo', 'role' => 'Shut Role', 'status' => 'closed']);

        $this->get($this->url())->assertOk()
            ->assertSee('Mark as closed')
            ->assertSee('Reopen')
            ->assertSee('Comm. Threads')
            ->assertSee('data-mark-closed="'.$open->id.'"', false)
            ->assertSee('data-reopen="'.$closed->id.'"', false)
            ->assertSee('data-comm-threads="'.$open->id.'"', false)
            ->assertSee('data-closed-badge', false)
            ->assertSee('Shut Role · ShutCo')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_mark_as_closed_and_reopen_restore_previous_status(): void
    {
        $job = $this->job('round', ['company' => 'RoundCo', 'role' => 'Round Role', 'status' => 'Applied', 'date_applied' => '2026-11-10']);

        Livewire::test(JobApplications::class)
            ->call('markClosed', $job->id)
            ->assertSee('Marked Round Role · RoundCo as closed.')
            ->assertSee('data-reopen="'.$job->id.'"', false)
            ->assertDontSee('data-mark-closed="'.$job->id.'"', false);

        $job->refresh();
        $this->assertTrue($job->isClosed());
        $this->assertSame('closed', $job->status);
        $this->assertSame(1, JobApplicationEvent::query()
            ->where('job_application_id', $job->id)
            ->where('type', 'status')
            ->where('from_value', 'Applied')
            ->where('to_value', 'closed')
            ->where('source', 'manual')
            ->count());

        Livewire::test(JobApplications::class)
            ->call('reopen', $job->id)
            ->assertSee('Reopened Round Role · RoundCo as Applied.')
            ->assertSee('data-mark-closed="'.$job->id.'"', false);

        $job->refresh();
        $this->assertFalse($job->isClosed());
        $this->assertSame('Applied', $job->status);
    }

    public function test_reopen_without_history_uses_applied_or_to_apply(): void
    {
        $applied = $this->job('had-applied', ['status' => 'closed', 'date_applied' => '2026-11-08']);
        $fresh = $this->job('never', ['status' => 'closed']);

        Livewire::test(JobApplications::class)
            ->call('reopen', $applied->id)
            ->call('reopen', $fresh->id);

        $this->assertSame('Applied', $applied->fresh()->status);
        $this->assertSame('To apply', $fresh->fresh()->status);
    }

    public function test_closed_filter_does_not_hide_closed_gigs_by_default(): void
    {
        $this->job('open-one', ['company' => 'VisibleOpen']);
        $this->job('closed-one', ['company' => 'VisibleClosed', 'status' => 'closed']);

        $c = Livewire::test(JobApplications::class)
            ->assertSee('VisibleOpen')
            ->assertSee('VisibleClosed')
            ->assertSee('Open (1)')
            ->assertSee('Closed (1)');
        $this->assertEqualsCanonicalizing(['VisibleOpen', 'VisibleClosed'], $c->viewData('listed')->pluck('company')->all());

        $c->set('closedFilter', 'open');
        $this->assertSame(['VisibleOpen'], $c->viewData('listed')->pluck('company')->all());

        $c->set('closedFilter', 'closed');
        $this->assertSame(['VisibleClosed'], $c->viewData('listed')->pluck('company')->all());

        $c->set('closedFilter', 'all');
        $this->assertEqualsCanonicalizing(['VisibleOpen', 'VisibleClosed'], $c->viewData('listed')->pluck('company')->all());
    }

    public function test_closed_gigs_are_excluded_from_upcoming_follow_ups(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26', 'Europe/Bucharest'));
        $this->job('due-open', ['company' => 'FollowOpen', 'follow_up_date' => '2026-11-28']);
        $this->job('due-closed', ['company' => 'FollowClosed', 'follow_up_date' => '2026-11-27', 'status' => 'closed']);

        $component = Livewire::test(JobApplications::class)
            ->assertSee('FollowOpen')
            ->assertSee('FollowClosed');
        $this->assertSame(['FollowOpen'], $component->viewData('followUps')->pluck('company')->all());
        Carbon::setTestNow();
    }

    public function test_threads_page_renders_and_is_not_indexed(): void
    {
        $job = $this->job('page', ['company' => 'ThreadCo', 'role' => 'Thread Role']);

        $this->get($job->threadsUrl())->assertOk()
            ->assertSee('Thread Role')
            ->assertSee('ThreadCo')
            ->assertSee('Cover letter')
            ->assertSee('Comm. Threads')
            ->assertSee('Paste the message here')
            ->assertSee('Date')
            ->assertSee('Time')
            ->assertSee('Mark as closed')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_threads_page_saves_cover_letter_and_preserves_existing_text(): void
    {
        $job = $this->job('letter', [
            'company' => 'LetterCo',
            'role' => 'Letter Role',
            'cover_letter' => 'Existing letter body.',
            'cover_letter_updated_at' => '2026-11-18 10:00:00',
        ]);

        Livewire::test(JobCommThread::class, ['application' => $job])
            ->assertSet('coverLetterBody', 'Existing letter body.')
            ->assertSee('Existing letter body.')
            ->set('coverLetterBody', "Existing letter body.\n\nP.S. Added a line.")
            ->call('saveCoverLetter')
            ->assertSee('Saved cover letter for Letter Role · LetterCo');

        $job->refresh();
        $this->assertSame("Existing letter body.\n\nP.S. Added a line.", $job->cover_letter);
        $this->assertNotNull($job->cover_letter_updated_at);
    }

    public function test_conversation_history_can_be_added_with_optional_time_edited_and_deleted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 18:00:00', 'Europe/Bucharest'));
        $job = $this->job('msg', ['company' => 'MsgCo', 'role' => 'Msg Role']);

        $c = Livewire::test(JobCommThread::class, ['application' => $job])
            ->assertSee('Comm. Threads')
            ->assertSee('No messages yet')
            ->set('messageForm.direction', 'sent')
            ->set('messageForm.occurred_on', '2026-11-17')
            ->set('messageForm.occurred_time', '09:15')
            ->set('messageForm.subject', 'Application for Msg Role')
            ->set('messageForm.body', 'Hello, please find my CV attached.')
            ->call('saveMessage')
            ->assertSee('Added a conversation entry for Msg Role · MsgCo')
            ->assertSee('Sent by me')
            ->assertSee('Application for Msg Role')
            ->assertSee('Hello, please find my CV attached.')
            ->assertSee('Tue 17 Nov 2026, 09:15')
            ->assertDontSee('No messages yet');

        $this->assertSame(1, $job->messages()->count());
        $message = $job->messages()->first();
        $this->assertSame('sent', $message->direction);
        $this->assertTrue($message->has_time);
        $this->assertSame('Application for Msg Role', $message->subject);
        $this->assertSame('2026-11-17 07:15:00', $message->occurred_at->copy()->utc()->format('Y-m-d H:i:s'));

        $c->set('messageForm.direction', 'received')
            ->set('messageForm.occurred_on', '2026-11-18')
            ->set('messageForm.occurred_time', '')
            ->set('messageForm.subject', '')
            ->set('messageForm.body', 'Thanks, we will be in touch.')
            ->call('saveMessage')
            ->assertSee('Received')
            ->assertSee('Thanks, we will be in touch.')
            ->assertSee('Wed 18 Nov 2026')
            ->assertDontSee('Wed 18 Nov 2026, 00:00');

        $this->assertSame(['sent', 'received'], $job->messages()->orderBy('occurred_at')->pluck('direction')->all());
        $reply = $job->messages()->where('direction', 'received')->first();
        $this->assertFalse($reply->has_time);

        $firstId = $message->id;
        $c->call('startEditMessage', $firstId)
            ->assertSet('editingMessageId', $firstId)
            ->assertSet('messageForm.occurred_on', '2026-11-17')
            ->assertSet('messageForm.occurred_time', '09:15')
            ->set('messageForm.body', 'Hello, please find my CV and portfolio attached.')
            ->call('saveMessage')
            ->assertSee('Updated the conversation entry.')
            ->assertSee('Hello, please find my CV and portfolio attached.')
            ->assertDontSee('Hello, please find my CV attached.');

        $c->call('deleteMessage', $reply->id)
            ->assertSee('Removed the conversation entry.')
            ->assertDontSee('Thanks, we will be in touch.');
        $this->assertSame(1, $job->messages()->count());

        $this->get($this->url())->assertOk()
            ->assertSee('Comm. Threads')
            ->assertSee('(1)');

        Carbon::setTestNow();
    }

    public function test_existing_messages_keep_their_time_after_has_time_default(): void
    {
        $job = $this->job('legacy');
        $message = JobApplicationMessage::query()->create([
            'job_application_id' => $job->id,
            'direction' => 'sent',
            'occurred_at' => Carbon::parse('2026-11-17 09:15:00', 'Europe/Bucharest')->utc(),
            'subject' => 'Legacy',
            'body' => 'Old message with a time.',
        ]);

        $this->assertTrue($message->fresh()->has_time);

        Livewire::test(JobCommThread::class, ['application' => $job])
            ->assertSee('Old message with a time.')
            ->assertSee('Tue 17 Nov 2026, 09:15');
    }

    public function test_message_validation_requires_a_date_body_and_a_valid_direction(): void
    {
        $job = $this->job('val');

        Livewire::test(JobCommThread::class, ['application' => $job])
            ->set('messageForm.direction', 'sideways')
            ->set('messageForm.occurred_on', '')
            ->set('messageForm.occurred_time', 'not-a-time')
            ->set('messageForm.body', '')
            ->call('saveMessage')
            ->assertHasErrors(['messageForm.direction', 'messageForm.occurred_on', 'messageForm.occurred_time', 'messageForm.body']);

        $this->assertSame(0, JobApplicationMessage::query()->count());
    }

    public function test_threads_page_needs_login_and_secret_token(): void
    {
        $job = $this->job('auth');
        $url = $job->threadsUrl();

        $this->flushSession();
        $this->get($url)->assertRedirect(route('login'));

        $this->withSession([\App\Http\Middleware\EnsureAuthenticated::SESSION_KEY => true]);
        $this->get('/x/wrong-token/'.$job->id.'/threads')->assertNotFound();
        $this->get($url)->assertOk();
    }
}
