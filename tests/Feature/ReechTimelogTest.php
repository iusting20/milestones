<?php

namespace Tests\Feature;

use App\Livewire\TaskLogBoard;
use App\Models\Project;
use App\Models\TaskLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ReechTimelogTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://reech.test/api/work-sessions';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'reech_timelog.url' => self::URL,
            'reech_timelog.token' => 'test-reech-token',
        ]);
    }

    private function fakeReech(array $sessions): void
    {
        Http::fake([self::URL.'*' => Http::response(['timezone' => 'Europe/Bucharest', 'sessions' => $sessions, 'running' => null])]);
    }

    public function test_reech_sessions_show_in_day_lists_and_week_totals_read_only(): void
    {
        $this->fakeReech([
            // Tue 17 Mar 2026, Bucharest (+02:00).
            ['id' => 7, 'type' => 'content', 'started_at' => '2026-03-17T10:00:00+02:00', 'ended_at' => '2026-03-17T10:45:00+02:00', 'minutes' => 45],
            ['id' => 8, 'type' => 'engagement', 'started_at' => '2026-03-17T11:00:00+02:00', 'ended_at' => '2026-03-17T11:30:00+02:00', 'minutes' => 30],
            // Sub-minute click: ignored.
            ['id' => 9, 'type' => 'engagement', 'started_at' => '2026-03-17T12:00:00+02:00', 'ended_at' => '2026-03-17T12:00:20+02:00', 'minutes' => 0],
            ['id' => 10, 'type' => 'unknown', 'started_at' => '2026-03-17T13:00:00+02:00', 'ended_at' => '2026-03-17T13:30:00+02:00'],
        ]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Emails',
            'started_at' => Carbon::parse('2026-03-17 06:00:00'),
            'finished_at' => Carbon::parse('2026-03-17 06:20:00'),
        ]);

        $component = Livewire::withQueryParams(['week' => '2026-W12'])->test(TaskLogBoard::class);

        $tue = collect($component->viewData('days'))->firstWhere('date', '2026-03-17');
        $this->assertSame(['Emails', 'Make Products - Content', 'Make Products - Engage'], $tue['logs']->pluck('title')->all());
        $this->assertSame(95, $tue['totalMinutes']);
        $this->assertSame('10:00', $tue['logs'][1]['started']);
        $this->assertSame(95, $component->viewData('stats')['totalMinutes']);
        $this->assertSame(3, $component->viewData('stats')['sessionCount']);
        $this->assertCount(3, $tue['clockArcs']);
        $outlined = collect($tue['clockArcs'])->where('hollow', true);
        $this->assertSame(['Make Products - Content', 'Make Products - Engage'], $outlined->pluck('title')->all());
        $this->assertTrue($outlined->every(fn (array $arc): bool => $arc['color'] === '#ff9800' && str_ends_with($arc['path'], 'Z')));
        $this->assertCount(1, collect($tue['clockArcs'])->where('hollow', false));

        $component->assertSeeHtml('data-reech-session')
            ->assertDontSeeHtml('data-reech-unavailable')
            ->call('startEditingTime', -7, 'started', 'day')
            ->assertSet('editingLogId', null);

        $this->assertSame(1, TaskLog::query()->count());

        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer test-reech-token')
            && str_contains($r->url(), 'from=2026-03-16') && str_contains($r->url(), 'to=2026-03-22'));
    }

    public function test_reech_sessions_count_under_a_make_products_project_or_others(): void
    {
        $this->fakeReech([
            ['id' => 7, 'type' => 'content', 'started_at' => '2026-03-17T10:00:00+02:00', 'ended_at' => '2026-03-17T10:45:00+02:00'],
        ]);

        $activity = Livewire::withQueryParams(['week' => '2026-W12'])->test(TaskLogBoard::class)->viewData('activity');
        $this->assertSame(['Others' => 45], collect($activity['assets']['rows'])->pluck('minutes', 'name')->all());

        Project::query()->create(['hub' => 'assets', 'name' => 'Make Products', 'slug' => 'mp', 'position' => 0]);

        $activity = Livewire::withQueryParams(['week' => '2026-W12'])->test(TaskLogBoard::class)->viewData('activity');
        $this->assertSame(['Make Products' => 45], collect($activity['assets']['rows'])->pluck('minutes', 'name')->all());
        $this->assertSame(0, $activity['partners']['totalMinutes']);
        $this->assertSame(45, $activity['partners']['otherHubMinutes']);
    }

    public function test_page_still_renders_when_reech_is_down(): void
    {
        Http::fake([self::URL.'*' => Http::response('oops', 500)]);

        TaskLog::query()->create([
            'todo_item_id' => null,
            'title' => 'Emails',
            'started_at' => Carbon::parse('2026-03-17 06:00:00'),
            'finished_at' => Carbon::parse('2026-03-17 06:20:00'),
        ]);

        Livewire::withQueryParams(['week' => '2026-W12'])->test(TaskLogBoard::class)
            ->assertOk()
            ->assertSee('Emails')
            ->assertSeeHtml('data-reech-unavailable');

        Http::fake(fn () => throw new ConnectionException('timeout'));
        cache()->flush();

        Livewire::withQueryParams(['week' => '2026-W12'])->test(TaskLogBoard::class)
            ->assertOk()
            ->assertSeeHtml('data-reech-unavailable');
    }

    public function test_not_configured_makes_no_request_and_shows_no_note(): void
    {
        config(['reech_timelog.url' => null]);
        Http::fake();

        Livewire::withQueryParams(['week' => '2026-W12'])->test(TaskLogBoard::class)
            ->assertOk()
            ->assertDontSeeHtml('data-reech-unavailable');

        Http::assertNothingSent();
    }

    public function test_activity_charts_show_other_hub_time_so_each_adds_up_to_the_week(): void
    {
        config(['reech_timelog.url' => null]);
        Project::query()->create(['hub' => 'partners', 'name' => 'Tudia', 'slug' => 'tudia', 'position' => 0]);
        Project::query()->create(['hub' => 'assets', 'name' => 'Impactful Tech', 'slug' => 'it', 'position' => 0]);

        foreach ([['Impactful Tech - admin', 142], ['Tudia - fix', 35], ['fix factura', 54]] as [$title, $minutes]) {
            TaskLog::query()->create(['todo_item_id' => null, 'title' => $title, 'started_at' => Carbon::parse('2026-03-17 08:00:00'), 'finished_at' => Carbon::parse('2026-03-17 08:00:00')->addMinutes($minutes)]);
        }

        $component = Livewire::withQueryParams(['week' => '2026-W12'])->test(TaskLogBoard::class);
        $activity = $component->viewData('activity');

        $this->assertSame(89, $activity['partners']['totalMinutes']);
        $this->assertSame(142, $activity['partners']['otherHubMinutes']);
        $this->assertSame(196, $activity['assets']['totalMinutes']);
        $this->assertSame(35, $activity['assets']['otherHubMinutes']);
        $this->assertSame(231, $activity['partners']['weekMinutes']);
        $this->assertSame(231, $activity['assets']['weekMinutes']);
        $this->assertSame(231, $component->viewData('stats')['totalMinutes']);

        $component->assertSeeHtml('data-activity-other-hub="partners"')
            ->assertSee('Asset-only work (no client)')
            ->assertSee('Client-only work (no asset)')
            ->assertSee('week 3h 51m');
    }
}
