<?php

namespace Tests\Feature;

use App\Livewire\JobApplications;
use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Support\JobApplicationWriter;
use App\Support\JobTrackerImport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class JobApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "Date added,Status,Date applied,Company,Role,Type,Rate / Salary,IR35,Location,Source / Recruiter,Link,Contact,Next step / result,Follow-up date,Notes\n";

    private function csv(string ...$rows): string
    {
        return self::HEADER.implode("\n", $rows)."\n";
    }

    private function url(): string
    {
        return '/x/'.config('jobs.page_token');
    }

    public function test_page_needs_login_and_secret_token(): void
    {
        $this->assertNotEmpty(config('jobs.page_token'));
        $this->get($this->url())->assertOk()->assertSee('Applications')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get('/x/wrong-token-wrong-token')->assertNotFound();

        $this->flushSession();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_gigs_nav_link_shows_only_for_logged_in_users(): void
    {
        $url = route('jobs.secret', config('jobs.page_token'));
        $this->get(route('read.stats'))->assertOk()->assertSee('Gigs')->assertSee($url, false);

        $this->flushSession();
        $this->get(route('login'))->assertDontSee($url, false)->assertDontSee(config('jobs.page_token'));
        $this->get(route('read.stats'))->assertRedirect(route('login'));
    }

    public function test_import_upserts_and_records_status_changes_and_removals(): void
    {
        $first = $this->csv(
            '2026-10-26,To apply,,Acme,AI Engineer,Contract,£500–£550/day,Outside,Remote,Recruiter Ltd,https://a,Jane Doe,,,',
            '2026-10-26,To apply,,Acme,AI Engineer,Contract,£600/day,Outside,Remote,Recruiter Ltd,https://b,,,,',
            '2026-09-26,Low priority (perm),,Beta,Engineer,Permanent,£90k,N/A,London,Ashby,https://c,,,,',
        );
        $stats = JobTrackerImport::import($first, 'test', Carbon::parse('2026-11-01 10:00'));
        $this->assertSame(['rows' => 3, 'created' => 3, 'updated' => 0, 'status_changes' => 0, 'removed' => 0], $stats);
        $this->assertSame(0, JobApplicationEvent::query()->count());

        $second = $this->csv(
            '2026-10-26,Applied,2026-11-07,Acme,AI Engineer,Contract,£500–£550/day,Outside,Remote,Recruiter Ltd,https://a,Jane Doe,Await response,2026-11-12,',
            '2026-10-26,To apply,,Acme,AI Engineer,Contract,£600/day,Outside,Remote,Recruiter Ltd,https://b,,,,',
        );
        $stats = JobTrackerImport::import($second, 'test', Carbon::parse('2026-11-07 12:00'));
        $this->assertSame(1, $stats['status_changes']);
        $this->assertSame(1, $stats['removed']);
        $this->assertSame(3, JobApplication::query()->count());
        $this->assertSame(1, JobApplicationEvent::query()->where('type', 'status')->where('from_value', 'To apply')->where('to_value', 'Applied')->count());
        $this->assertNotNull(JobApplication::query()->where('company', 'Beta')->first()->removed_at);

        $this->get($this->url())->assertOk()->assertSee('Jane Doe')->assertSee('New Opportunities Added')->assertDontSee('Status changed');
    }

    public function test_day_rate_parsing(): void
    {
        $this->assertSame(['min' => 500, 'max' => 500], JobApplications::parseDayRate('£500/day'));
        $this->assertSame(['min' => 450, 'max' => 550], JobApplications::parseDayRate('450-550'));
        $this->assertSame(['min' => 700, 'max' => 900], JobApplications::parseDayRate('£700–£900/day'));
        $this->assertSame(['min' => 650, 'max' => 650], JobApplications::parseDayRate('Up to £650/day'));
        $this->assertNull(JobApplications::parseDayRate('Flexible'));
        $this->assertNull(JobApplications::parseDayRate('Not stated'));
        $this->assertNull(JobApplications::parseDayRate('£95k–£120k'));
    }

    public function test_progress_log_picks_up_dated_results_from_text(): void
    {
        $app = JobApplication::query()->create([
            'key' => 'k1', 'company' => 'Praktika', 'role' => 'Dev', 'status' => 'closed',
            'date_added' => '2026-11-07', 'date_applied' => '2026-09-22', 'next_step' => 'rejected',
            'notes' => 'Rejected 12 Oct 2026 (email): no. Applied 22 Sep 2026 (confirmation).',
        ]);
        $log = JobApplications::progressLog(collect([$app]), collect(), Carbon::parse('2026-11-11'));
        $kinds = array_map(fn ($e) => $e['date'].' '.$e['kind'], $log);
        $this->assertSame(['2026-11-07 added', '2026-10-12 rejected', '2026-09-22 applied'], $kinds);
    }

    public function test_category_is_inferred_from_role_title(): void
    {
        $ai = [
            'AI Engineer',
            'Senior Applied AI Engineer',
            'NL Query Orchestration Developer (LangGraph)',
            'Senior Software Engineer – Agentic Workflows',
            'Artificial Intelligence Engineer',
            'Artificial Intelligence Consultant',
            'AI Engineer (LLM / agentic)',
            'Full-stack AI Engineer / Senior Full-Stack – AI Agent Platform',
            'ai automation engineer',
        ];
        foreach ($ai as $role) {
            $this->assertSame('ai', JobApplicationWriter::inferCategory($role), $role);
        }

        $software = [
            'Senior Full-Stack Software Engineer',
            'Senior Product Engineer',
            'Full Stack Engineer (Node.js TypeScript React)',
            'Platform maintain lead',
            'Details available on request',
            null,
            '',
        ];
        foreach ($software as $role) {
            $this->assertSame('software', JobApplicationWriter::inferCategory($role), (string) $role);
        }
    }

    public function test_weekly_counts_replies_and_decided_outcomes(): void
    {
        $added = JobApplication::query()->create([
            'key' => 'wk', 'company' => 'WeekCo', 'role' => 'Dev', 'status' => 'Offer',
            'date_added' => '2026-11-02', 'date_applied' => '2026-11-03',
        ]);
        $added->messages()->create([
            'direction' => 'received', 'occurred_at' => '2026-11-10 12:00:00', 'body' => 'Thanks',
        ]);
        $added->events()->create([
            'type' => 'status', 'to_value' => 'Offer', 'occurred_at' => '2026-11-18 09:00:00', 'source' => 'manual',
        ]);
        $passed = JobApplication::query()->create([
            'key' => 'wk2', 'company' => 'PassCo', 'role' => 'Dev', 'status' => 'To apply',
            'date_added' => '2026-11-02', 'notes' => 'Rejected 12 Nov 2026',
        ]);

        $added->load(['messages', 'events']);
        $passed->load(['messages', 'events']);
        $weeks = collect(JobApplications::weekly(collect([$added, $passed]), Carbon::parse('2026-11-20')));
        $byStart = $weeks->keyBy('start');

        $this->assertSame(2, $byStart['2026-11-02']['added']);
        $this->assertSame(1, $byStart['2026-11-02']['applied']);
        $this->assertSame(1, $byStart['2026-11-09']['responded']);
        $this->assertSame(1, $byStart['2026-11-09']['decided']);
        $this->assertSame(1, $byStart['2026-11-16']['decided']);
    }

    public function test_category_filter_limits_list_and_counts(): void
    {
        JobApplication::query()->create([
            'key' => 'k-soft', 'company' => 'FilterCoSoft', 'role' => 'Staff Platform Engineer',
            'category' => 'software', 'status' => 'Applied', 'date_added' => '2026-11-01',
        ]);
        JobApplication::query()->create([
            'key' => 'k-ai', 'company' => 'FilterCoAI', 'role' => 'Staff LLM Engineer',
            'category' => 'ai', 'status' => 'To apply', 'date_added' => '2026-11-02',
        ]);

        $this->get($this->url())->assertOk()
            ->assertSee('FilterCoSoft')->assertSee('FilterCoAI')
            ->assertSee('All')->assertSee('Software Engineer')->assertSee('AI Engineer');

        $this->get($this->url().'?category=ai')->assertOk()
            ->assertSee('FilterCoAI')->assertDontSee('FilterCoSoft');

        $this->get($this->url().'?category=software')->assertOk()
            ->assertSee('FilterCoSoft')->assertDontSee('FilterCoAI');

        $this->get($this->url().'?category=nope')->assertOk()
            ->assertSee('FilterCoSoft')->assertSee('FilterCoAI');

        Livewire::withQueryParams(['category' => 'ai'])
            ->test(JobApplications::class)
            ->assertSet('category', 'ai')
            ->assertSee('FilterCoAI')
            ->assertDontSee('FilterCoSoft')
            ->set('category', 'software')
            ->assertSee('FilterCoSoft')
            ->assertDontSee('FilterCoAI')
            ->set('category', 'all')
            ->assertSee('FilterCoSoft')
            ->assertSee('FilterCoAI');
    }

    private function job(string $key, array $attrs): JobApplication
    {
        return JobApplication::query()->create($attrs + [
            'key' => $key, 'company' => 'Co '.$key, 'role' => 'Engineer '.$key,
            'category' => 'software', 'status' => 'To apply', 'type' => 'Contract', 'date_added' => '2026-11-01',
        ]);
    }

    public function test_micah_avatar_is_stable_and_unique_per_job(): void
    {
        $a = $this->job('ava', []);
        $b = $this->job('avb', []);

        $this->assertSame($a->avatarUrl(), $a->fresh()->avatarUrl());
        $this->assertNotSame($a->avatarUrl(), $b->avatarUrl());
        $this->assertStringContainsString('/avatar', $a->avatarUrl());
        $this->assertStringContainsString((string) $a->id, $a->avatarUrl());

        $source = $a->dicebearSourceUrl();
        $this->assertStringContainsString('https://api.dicebear.com/9.x/micah/png?', $source);
        $this->assertStringContainsString('seed=job-'.$a->id, $source);
        $this->assertStringContainsString('earringsProbability=0', $source);
        $this->assertStringContainsString('glassesProbability=0', $source);
        $this->assertStringContainsString('facialHairProbability=0', $source);
        $this->assertStringContainsString('mouth=surprised%2Claughing%2Csmile%2Cpucker%2Csmirk', $source);
        $this->assertStringNotContainsString('backgroundColor', $source);
        $this->assertNotSame($a->dicebearSourceUrl(), $b->dicebearSourceUrl());

        $urls = [];
        for ($i = 1; $i <= 200; $i++) {
            $job = new JobApplication;
            $job->id = $i;
            $urls[] = $job->avatarUrl();
        }
        $this->assertCount(200, array_unique($urls));
    }

    public function test_avatar_route_caches_png_from_dicebear(): void
    {
        Storage::fake('local');
        $png = "\x89PNG\r\n\x1a\n".str_repeat('x', 120);
        $job = $this->job('ava-cache', []);

        Http::fake([
            'api.dicebear.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $url = route('jobs.avatar', ['token' => config('jobs.page_token'), 'application' => $job]);
        $first = $this->get($url);
        $first->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($png, $first->getContent());
        Storage::disk('local')->assertExists($job->avatarCachePath());
        $this->assertSame($png, Storage::disk('local')->get($job->avatarCachePath()));

        Http::fake([
            'api.dicebear.com/*' => Http::response('should-not-fetch', 500),
        ]);
        $second = $this->get($url);
        $second->assertOk();
        $this->assertSame($png, $second->getContent());
        Http::assertNothingSent();
    }

    public function test_chart_slice_modal_lists_matching_roles(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20', 'Europe/Bucharest'));
        $applied = $this->job('chart-applied', [
            'company' => 'AppliedCo', 'status' => 'Applied', 'date_applied' => '2026-11-04',
            'type' => 'Contract', 'date_added' => '2026-11-04',
        ]);
        $this->job('chart-to-apply', [
            'company' => 'OpenCo', 'status' => 'To apply', 'type' => 'Permanent',
        ]);
        $this->job('chart-contract', [
            'company' => 'ContractCo', 'status' => 'To apply', 'type' => 'Contract', 'date_added' => '2026-11-05',
        ]);

        $c = Livewire::test(JobApplications::class)
            ->call('openChartSlice', 'applied')
            ->assertSet('chartSlice', 'applied')
            ->assertSee('data-chart-modal', false)
            ->assertSeeHtml('id="chart-slice-title"');

        $this->assertSame(['AppliedCo'], $c->viewData('chartSliceApps')->pluck('company')->all());
        $this->assertSame('Applied', $c->viewData('chartSliceTitle'));

        $c->call('openChartSlice', 'contract', '2026-11-03')
            ->assertSet('chartSlice', 'contract')
            ->assertSet('chartWeek', '2026-11-02')
            ->assertSee('Contract · week of 2 Nov 2026');

        $this->assertEqualsCanonicalizing(
            ['ContractCo', 'AppliedCo'],
            $c->viewData('chartSliceApps')->pluck('company')->all()
        );

        $c->call('openView', $applied->id)
            ->assertSet('chartSlice', null)
            ->assertSet('viewingId', $applied->id);
    }

    public function test_rank_rate_reads_day_and_hourly_rates(): void
    {
        $this->assertSame(900, JobApplications::rankRate('£700–£900/day'));
        $this->assertSame(650, JobApplications::rankRate('Up to £650/day'));
        $this->assertSame(800, JobApplications::rankRate('Up to €100/hour'));
        $this->assertSame(600, JobApplications::rankRate('£75 per hour'));
        $this->assertNull(JobApplications::rankRate('Not stated'));
        $this->assertNull(JobApplications::rankRate('£95k–£120k'));
        $this->assertNull(JobApplications::rankRate(null));
    }

    public function test_next_step_queue_order_flagged_then_rate_then_unknown(): void
    {
        $this->job('low', ['rate' => '£450–£500/day']);
        $this->job('high', ['rate' => '£800–£900/day']);
        $this->job('unknown', ['rate' => 'Not stated', 'date_added' => '2026-10-01']);
        $this->job('hourly', ['rate' => '€100/hour']);
        $this->job('flag', ['role' => "\u{1F6A9} Indeed job", 'rate' => null]);
        $this->job('applied', ['rate' => '£999/day', 'status' => 'Applied', 'date_applied' => '2026-11-02']);
        $this->job('dated', ['rate' => '£990/day', 'date_applied' => '2026-11-02']);
        $this->job('closed', ['rate' => '£980/day', 'status' => 'closed']);
        $this->job('perm', ['rate' => '£95k', 'status' => 'Low priority (perm)', 'type' => 'Permanent']);
        $this->job('lead', ['rate' => '£520/day', 'status' => 'lead']);
        $this->job('gone', ['rate' => '£970/day', 'removed_at' => '2026-11-03 10:00:00']);

        $queue = JobApplications::nextStepQueue(JobApplication::query()->get());
        $this->assertSame(['flag', 'high', 'hourly', 'lead', 'low', 'unknown'], $queue->pluck('key')->all());
    }

    public function test_next_steps_panel_respects_category_and_marks_applied(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 23:30:00', 'UTC')); // 19 Nov in Bucharest
        $soft = $this->job('soft', ['company' => 'NextSoftCo', 'rate' => '£600/day']);
        $soft2 = $this->job('soft2', ['company' => 'NextSoftTwo', 'rate' => '£500/day']);
        $ai = $this->job('ai', ['company' => 'NextAiCo', 'category' => 'ai', 'rate' => '£700/day']);

        $c = Livewire::test(JobApplications::class)
            ->assertSee('Next Steps:')->assertSee('Done, I applied')
            ->assertSee('Comm. Thread')->assertSee('Mark as closed')
            ->assertSee('Cover letter & CV')
            ->assertSee('data-next-step-comm-thread', false)
            ->assertSee('data-next-step-mark-closed', false)
            ->assertSee('aria-label="Previous"', false)
            ->assertSee('aria-label="Next"', false)
            ->assertDontSee('View all')
            ->assertSee('1 / 3', false)
            ->assertSee('data-next-step-prev', false)
            ->assertSee('disabled', false)
            ->call('cycleNextStep', -1)
            ->assertSee('data-next-step-id="'.$ai->id.'"', false)
            ->call('cycleNextStep', 1)
            ->assertSee('data-next-step-id="'.$soft->id.'"', false)
            ->call('cycleNextStep', 1)
            ->assertSee('data-next-step-id="'.$soft2->id.'"', false)
            ->call('cycleNextStep', 1)
            ->assertSee('data-next-step-id="'.$soft2->id.'"', false)
            ->call('cycleNextStep', -1)
            ->call('cycleNextStep', -1)
            ->assertSee('data-next-step-id="'.$ai->id.'"', false)
            ->assertSeeHtml('href="'.$ai->threadsUrl().'"')
            ->assertViewHas('nextUp', fn ($a) => $a->id === $ai->id)
            ->set('category', 'software')
            ->assertViewHas('nextUp', fn ($a) => $a->id === $soft->id)
            ->assertSeeHtml('href="'.$soft->threadsUrl().'"')
            ->call('markApplied', $soft->id)
            ->assertViewHas('nextUp', fn ($a) => $a->id === $soft2->id)
            ->assertSee('as applied');

        $soft->refresh();
        $this->assertSame('Applied', $soft->status);
        $this->assertSame('2026-11-19', $soft->date_applied->toDateString());
        $this->assertSame(1, JobApplicationEvent::query()->where('job_application_id', $soft->id)->where('type', 'status')->where('to_value', 'Applied')->where('source', 'manual')->count());
        $this->assertSame(1, JobApplicationEvent::query()->where('job_application_id', $soft->id)->where('type', 'applied')->where('to_value', '2026-11-19')->count());

        // A second click on an already-applied role changes nothing.
        $c->call('markApplied', $soft->id)->assertSee('no longer waiting');
        $this->assertSame(2, JobApplicationEvent::query()->where('job_application_id', $soft->id)->count());

        $c->call('markClosed', $soft2->id)
            ->assertViewHas('nextUp', null)
            ->assertSee('Marked Engineer soft2 · NextSoftTwo as closed.')
            ->assertSee('Nothing left to apply to in this category');
        $this->assertTrue($soft2->fresh()->isClosed());
        Carbon::setTestNow();
    }

    public function test_job_details_modal_and_new_opportunities_section(): void
    {
        $a = $this->job('modal', [
            'company' => 'ModalCo', 'role' => 'Modal Engineer', 'rate' => '£650/day', 'ir35' => 'Outside',
            'location' => 'Remote UK', 'source' => 'ModalRecruiter', 'contact' => 'Jane Modal',
            'link' => 'https://jobs.example.com/modal', 'next_step' => 'Send CV', 'notes' => 'Modal notes here',
            'date_added' => '2026-11-07',
        ]);

        $this->get($this->url())->assertOk()
            ->assertSee('New Opportunities Added')->assertDontSee('Activity log')
            ->assertSee('data-new-opportunities', false)
            ->assertSee('Sat 7 Nov 2026')
            ->assertSee('Modal Engineer · ModalCo', false);

        Livewire::test(JobApplications::class)
            ->assertDontSee('Open application')
            ->call('openView', $a->id)
            ->assertSee('Open application')->assertSee('Jane Modal')->assertSee('Modal notes here')->assertSee('ModalRecruiter')
            ->assertSeeHtml('href="https://jobs.example.com/modal" target="_blank" rel="noopener noreferrer"')
            ->call('closeView')
            ->assertSet('viewingId', null)
            ->assertDontSee('Open application');
    }
}
