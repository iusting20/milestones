<?php

namespace Tests\Feature;

use App\Livewire\ActivityStats;
use App\Models\Book;
use App\Models\Project;
use App\Models\ReadingSession;
use App\Models\RoadmapItem;
use App\Models\TaskLog;
use App\Models\TodoItem;
use App\Models\WeekBoardItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_and_year_stats_group_books_and_board_activity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 12:00:00', 'Europe/Bucharest'));

        $book = Book::query()->create([
            'title' => 'Finished book',
            'status' => Book::STATUS_FINISHED,
            'kind' => Book::KIND_BOOK,
            'position' => 1,
        ]);
        ReadingSession::query()->create([
            'book_id' => $book->id,
            'started_at' => Carbon::parse('2026-03-10 10:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-10 11:30:00', 'Europe/Bucharest')->utc(),
        ]);

        $article = Book::query()->create([
            'title' => 'An article',
            'status' => Book::STATUS_FINISHED,
            'kind' => Book::KIND_ARTICLE,
            'position' => 2,
        ]);
        ReadingSession::query()->create([
            'book_id' => $article->id,
            'started_at' => Carbon::parse('2026-03-11 10:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-11 12:00:00', 'Europe/Bucharest')->utc(),
        ]);

        TodoItem::query()->create([
            'date' => '2026-03-16',
            'title' => 'Ship it',
            'is_completed' => true,
            'position' => 1,
        ]);
        TodoItem::query()->create([
            'date' => '2026-03-16',
            'title' => '---',
            'is_completed' => true,
            'is_separator' => true,
            'position' => 2,
        ]);

        TaskLog::query()->create([
            'title' => 'Ship it',
            'started_at' => Carbon::parse('2026-03-16 09:00:00', 'Europe/Bucharest')->utc(),
            'finished_at' => Carbon::parse('2026-03-16 10:00:00', 'Europe/Bucharest')->utc(),
        ]);

        RoadmapItem::query()->create([
            'title' => 'Timeline one',
            'started_on' => '2026-01-01',
            'completed_on' => '2026-03-20',
        ]);

        $project = Project::query()->create([
            'hub' => 'partners',
            'name' => 'Acme',
            'slug' => 'acme',
            'position' => 1,
        ]);
        WeekBoardItem::query()->create([
            'project_id' => $project->id,
            'title' => 'Card',
            'status' => 'done',
            'week' => '2026-W12',
            'position' => 1,
        ]);

        RoadmapItem::query()->create([
            'title' => 'Last year',
            'started_on' => '2025-01-01',
            'completed_on' => '2025-12-01',
        ]);

        Livewire::test(ActivityStats::class)
            ->assertSee('Stats')
            ->assertSeeHtml('data-stat-row="2026-03"')
            ->assertSee('1h 30m')
            ->assertSee('1h')
            ->assertDontSeeHtml('data-stat-row="2026-12"')
            ->call('showYears')
            ->assertSeeHtml('data-stat-row="2025"')
            ->assertSeeHtml('data-stat-row="2026"');

        $march = collect(Livewire::test(ActivityStats::class)->viewData('breakdown')['rows'])
            ->firstWhere('key', '2026-03');

        $this->assertSame(1, $march['books']);
        $this->assertSame(90, $march['readMinutes']);
        $this->assertSame(1, $march['milestones']);
        $this->assertSame(60, $march['loggedMinutes']);
        $this->assertSame(1, $march['timelines']);
        $this->assertSame(1, $march['weekBoard']);

        $year = collect(Livewire::test(ActivityStats::class)->call('showYears')->viewData('breakdown')['rows'])
            ->firstWhere('key', '2026');

        $this->assertSame(1, $year['timelines']);
        $this->assertSame(1, collect(Livewire::test(ActivityStats::class)->call('showYears')->viewData('breakdown')['rows'])->firstWhere('key', '2025')['timelines']);
    }
}
