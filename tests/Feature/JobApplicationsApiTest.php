<?php

namespace Tests\Feature;

use App\Livewire\JobApplications;
use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Support\JobTrackerImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JobApplicationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.config('jobs.api_token'), 'Accept' => 'application/json'];
    }

    private function make(array $attrs = []): JobApplication
    {
        return JobApplication::query()->create($attrs + [
            'key' => sha1(uniqid('', true)), 'company' => 'Acme', 'role' => 'AI Engineer', 'status' => 'To apply',
            'date_added' => '2026-11-01',
        ]);
    }

    public function test_api_requires_a_valid_bearer_token(): void
    {
        $this->assertNotEmpty(config('jobs.api_token'));
        $this->assertNotSame(config('jobs.api_token'), config('jobs.page_token'));
        $app = $this->make();

        $this->flushSession();
        $this->getJson('/api/jobs')->assertUnauthorized()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->getJson('/api/jobs', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->getJson('/api/jobs/'.$app->id)->assertUnauthorized();
        $this->getJson('/api/jobs/999999')->assertUnauthorized();
        $this->postJson('/api/jobs', ['company' => 'X', 'role' => 'Y'])->assertUnauthorized();
        $this->patchJson('/api/jobs/'.$app->id, ['status' => 'Applied'], ['Authorization' => 'Bearer '.config('jobs.page_token')])->assertUnauthorized();
        $this->assertSame(1, JobApplication::query()->count());
        $this->assertSame('To apply', $app->fresh()->status);
    }

    public function test_list_and_show(): void
    {
        $this->flushSession();
        $a = $this->make();
        $this->make(['company' => 'Beta', 'removed_at' => now()]);
        JobApplicationEvent::query()->create(['job_application_id' => $a->id, 'type' => 'status', 'from_value' => null, 'to_value' => 'To apply', 'occurred_at' => now()]);

        $this->getJson('/api/jobs', $this->auth())->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.company', 'Acme')
            ->assertJsonPath('data.0.date_added', '2026-11-01')
            ->assertJsonMissingPath('data.0.events')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->getJson('/api/jobs?with=events', $this->auth())->assertOk()->assertJsonCount(1, 'data.0.events');
        $this->getJson('/api/jobs?include_removed=1', $this->auth())->assertJsonPath('meta.count', 2);
        $this->getJson('/api/jobs?status=to%20APPLY', $this->auth())->assertJsonPath('meta.count', 1);
        $this->getJson('/api/jobs/'.$a->id, $this->auth())->assertOk()->assertJsonPath('data.id', $a->id)->assertJsonCount(1, 'data.events');
        $this->getJson('/api/jobs/999999', $this->auth())->assertNotFound();
    }

    public function test_create_with_defaults_and_event(): void
    {
        $this->flushSession();
        $res = $this->postJson('/api/jobs', [
            'company' => 'Sportswear Co', 'role' => 'Full Stack', 'type' => 'contract', 'ir35' => 'outside',
            'rate' => '£550/day', 'next_step' => 'Await response',
        ], $this->auth());

        $res->assertCreated()
            ->assertJsonPath('data.status', 'To apply')
            ->assertJsonPath('data.type', 'Contract')
            ->assertJsonPath('data.ir35', 'Outside')
            ->assertJsonPath('data.origin', 'api')
            ->assertJsonPath('data.date_added', now('Europe/Bucharest')->toDateString())
            ->assertJsonPath('events.0.type', 'created')
            ->assertJsonPath('events.0.source', 'api');

        $app = JobApplication::query()->findOrFail($res->json('data.id'));
        $this->assertStringStartsWith('direct-', $app->key);
        $this->assertSame(['created', 'next_step'], $app->events()->orderBy('id')->pluck('type')->all());
        $this->assertSame(['api'], $app->events()->pluck('source')->unique()->values()->all());

        // Two identical creates must not collide on the unique key.
        $this->postJson('/api/jobs', ['company' => 'Same', 'role' => 'Same'], $this->auth())->assertCreated();
        $this->postJson('/api/jobs', ['company' => 'Same', 'role' => 'Same'], $this->auth())->assertCreated();
        $this->assertSame(3, JobApplication::query()->distinct()->count('key'));
    }

    public function test_update_logs_each_change(): void
    {
        $this->flushSession();
        $app = $this->make();

        $res = $this->patchJson('/api/jobs/'.$app->id, [
            'status' => 'applied', 'date_applied' => '2026-11-07', 'next_step' => 'Await response',
            'follow_up_date' => '2026-11-12', 'notes' => 'Sent CV', 'location' => 'Remote',
        ], $this->auth());

        $res->assertOk()->assertJsonPath('data.status', 'Applied')->assertJsonPath('data.follow_up_date', '2026-11-12');
        $types = collect($res->json('events'))->pluck('type')->all();
        $this->assertSame(['status', 'applied', 'next_step', 'follow_up', 'notes', 'updated'], $types);
        $this->assertDatabaseHas('job_application_events', ['job_application_id' => $app->id, 'type' => 'status', 'from_value' => 'To apply', 'to_value' => 'Applied', 'source' => 'api']);
        $this->assertDatabaseHas('job_application_events', ['type' => 'updated', 'note' => 'Changed location']);

        // Same values again: nothing logged.
        $this->patchJson('/api/jobs/'.$app->id, ['status' => 'Applied', 'follow_up_date' => '2026-11-12'], $this->auth())
            ->assertOk()->assertJsonCount(0, 'events');
        $this->assertSame(6, JobApplicationEvent::query()->count());

        // Shows up in the page's progress log.
        $this->withSession([\App\Http\Middleware\EnsureAuthenticated::SESSION_KEY => true])
            ->get('/x/'.config('jobs.page_token'))->assertOk()->assertSee('To apply → Applied')->assertSee('via API');
    }

    public function test_validation(): void
    {
        $this->flushSession();
        $app = $this->make();

        $this->postJson('/api/jobs', ['role' => 'Dev'], $this->auth())->assertUnprocessable()->assertJsonValidationErrors('company');
        $this->postJson('/api/jobs', ['company' => 'A', 'role' => 'B', 'date_applied' => '07/11/2026'], $this->auth())
            ->assertUnprocessable()->assertJsonValidationErrors('date_applied');
        $this->postJson('/api/jobs', ['company' => 'A', 'role' => 'B', 'type' => 'Freelance'], $this->auth())
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->postJson('/api/jobs', ['company' => 'A', 'role' => 'B', 'colour' => 'red'], $this->auth())
            ->assertUnprocessable()->assertJsonValidationErrors('fields');
        $this->patchJson('/api/jobs/'.$app->id, ['company' => ''], $this->auth())->assertUnprocessable()->assertJsonValidationErrors('company');
        $this->patchJson('/api/jobs/'.$app->id, ['follow_up_date' => '2026-13-01'], $this->auth())->assertUnprocessable();

        // Unknown statuses are kept as typed.
        $this->patchJson('/api/jobs/'.$app->id, ['status' => 'Second interview'], $this->auth())->assertOk()->assertJsonPath('data.status', 'Second interview');
        $this->assertSame(1, JobApplication::query()->count());
    }

    public function test_page_form_creates_and_edits_with_manual_events(): void
    {
        Livewire::test(JobApplications::class)
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->assertSet('form.status', 'To apply')
            ->assertSet('form.category', 'software')
            ->set('form.company', '')
            ->set('form.role', 'Dev')
            ->call('save')
            ->assertHasErrors('form.company')
            ->set('form.company', 'Praktika')
            ->set('form.type', 'Contract')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $app = JobApplication::query()->where('company', 'Praktika')->firstOrFail();
        $this->assertSame('manual', $app->origin);
        $this->assertSame('created', $app->events()->first()->type);
        $this->assertSame('manual', $app->events()->first()->source);

        Livewire::test(JobApplications::class)
            ->call('openEdit', $app->id)
            ->assertSet('form.company', 'Praktika')
            ->set('form.status', 'closed')
            ->set('form.next_step', 'Rejected 12 Oct 2026')
            ->set('form.follow_up_date', 'tomorrow')
            ->call('save')
            ->assertHasErrors('form.follow_up_date')
            ->set('form.follow_up_date', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Status changed')
            ->assertSee('by hand');

        $this->assertSame('closed', $app->fresh()->status);
        $this->assertDatabaseHas('job_application_events', ['job_application_id' => $app->id, 'type' => 'status', 'from_value' => 'To apply', 'to_value' => 'closed', 'source' => 'manual']);
        $this->assertDatabaseHas('job_application_events', ['job_application_id' => $app->id, 'type' => 'next_step', 'source' => 'manual']);
    }

    public function test_page_without_login_is_still_protected(): void
    {
        $this->flushSession();
        $this->get('/x/'.config('jobs.page_token'))->assertRedirect(route('login'));
        // The API token does not open the page.
        $this->get('/x/'.config('jobs.page_token'), ['Authorization' => 'Bearer '.config('jobs.api_token')])->assertRedirect(route('login'));
    }

    public function test_sheet_import_does_not_remove_directly_created_rows(): void
    {
        $this->flushSession();
        $id = $this->postJson('/api/jobs', ['company' => 'Direct', 'role' => 'Dev'], $this->auth())->json('data.id');
        JobTrackerImport::import("Date added,Status,Date applied,Company,Role,Type,Rate / Salary,IR35,Location,Source / Recruiter,Link,Contact,Next step / result,Follow-up date,Notes\n2026-10-26,To apply,,Acme,AI,Contract,,,,,,,,,\n", 'test');
        $this->assertNull(JobApplication::query()->findOrFail($id)->removed_at);
        $this->assertSame(2, JobApplication::query()->whereNull('removed_at')->count());
    }
    public function test_category_is_accepted_returned_and_filterable(): void
    {
        $this->flushSession();

        $soft = $this->postJson('/api/jobs', ['company' => 'SoftCo', 'role' => 'Platform Engineer'], $this->auth());
        $soft->assertCreated()
            ->assertJsonPath('data.category', 'software')
            ->assertJsonPath('data.company', 'SoftCo');

        $ai = $this->postJson('/api/jobs', [
            'company' => 'AiCo', 'role' => 'Applied AI Engineer', 'category' => 'AI Engineer',
        ], $this->auth());
        $ai->assertCreated()->assertJsonPath('data.category', 'ai');

        $this->postJson('/api/jobs', ['company' => 'X', 'role' => 'Y', 'category' => 'design'], $this->auth())
            ->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->getJson('/api/jobs', $this->auth())->assertOk()
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.category', 'software');

        $this->getJson('/api/jobs?category=ai', $this->auth())
            ->assertOk()->assertJsonPath('meta.count', 1)->assertJsonPath('data.0.company', 'AiCo');
        $this->getJson('/api/jobs?category=SOFTWARE', $this->auth())
            ->assertOk()->assertJsonPath('meta.count', 1)->assertJsonPath('data.0.company', 'SoftCo');
        $this->getJson('/api/jobs?category=nope', $this->auth())
            ->assertOk()->assertJsonPath('meta.count', 0);

        $this->patchJson('/api/jobs/'.$soft->json('data.id'), ['category' => 'ai'], $this->auth())
            ->assertOk()->assertJsonPath('data.category', 'ai');
        $this->assertDatabaseHas('job_application_events', [
            'job_application_id' => $soft->json('data.id'),
            'type' => 'updated',
            'note' => 'Changed category',
            'source' => 'api',
        ]);

        $this->patchJson('/api/jobs/'.$soft->json('data.id'), ['category' => ''], $this->auth())
            ->assertUnprocessable()->assertJsonValidationErrors('category');
    }
}
