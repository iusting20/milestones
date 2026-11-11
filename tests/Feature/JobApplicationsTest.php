<?php

namespace Tests\Feature;

use App\Livewire\JobApplications;
use App\Models\JobApplication;
use App\Models\JobApplicationEvent;
use App\Support\JobTrackerImport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get($this->url())->assertOk()->assertSee('Jane Doe')->assertSee('Status changed')->assertSee('To apply → Applied');
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
}
