<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\RoadmapItem;
use App\Support\Clock;
use App\Support\Onboarding;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Roadmaps extends Component
{
    public const PENDING_COLOR_COUNT = 8;

    /**
     * @var list<string>
     */
    private const PENDING_COLORS = [
        '#ffc107',
        '#ff9800',
        '#ff5722',
        '#e91e63',
        '#9c27b0',
        '#3f51b5',
        '#2196f3',
        '#00bcd4',
    ];

    private const DONE_COLOR = '#7cb518';

    #[Url(as: 'month', history: true)]
    public string $month = '';

    public string $newTitle = '';

    /** timeline or deliverable. A deliverable is a single day with a star marker. */
    public string $kind = 'timeline';

    public string $startedOn = '';

    /** Optional scheduled finish day for the "Start a project" form. */
    public string $finishOn = '';

    public string $newLinkTitle = '';

    public string $newLinkUrl = '';

    /** Associated client or asset (projects.id), or empty for none. */
    public string $newProjectId = '';

    public bool $creating = false;

    /** Set when the create/edit modal is editing an existing item. */
    public ?int $editingId = null;

    public function mount(): void
    {
        if ($this->month === '' || ! $this->parseMonth($this->month)) {
            $this->month = Clock::currentMonth();
        }

        if ($this->startedOn === '') {
            $this->startedOn = Clock::todayString();
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->copy()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->copy()->addMonthNoOverflow()->format('Y-m');
    }

    public function goToThisMonth(): void
    {
        $this->month = Clock::currentMonth();
        $this->startedOn = Clock::todayString();
    }

    public function showMonth(string $month): void
    {
        if (! $this->parseMonth($month)) {
            return;
        }

        $this->month = $month;
    }

    public function selectDay(string $date): void
    {
        if (! $this->parseDate($date)) {
            return;
        }

        $this->startedOn = $date;
    }

    public function openCreate(string $kind = 'timeline'): void
    {
        $this->resetFormFields();
        $this->kind = $kind === 'deliverable' ? 'deliverable' : 'timeline';
        $this->startedOn = Clock::todayString();
        $this->editingId = null;
        $this->creating = true;
    }

    public function openEdit(int $id): void
    {
        $item = RoadmapItem::query()->findOrFail($id);

        $this->resetErrorBag();
        $this->editingId = $item->id;
        $this->newTitle = $item->title;
        $this->kind = $item->isDeliverable() ? 'deliverable' : 'timeline';
        $this->startedOn = $item->started_on->toDateString();
        $this->finishOn = $item->finish_on?->toDateString() ?? '';
        $this->newLinkTitle = $item->link_title ?? '';
        $this->newLinkUrl = $item->link_url ?? '';
        $this->newProjectId = $item->project_id ? (string) $item->project_id : '';
        $this->creating = true;
    }

    public function closeCreate(): void
    {
        $this->creating = false;
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function addItem(): void
    {
        $title = trim($this->newTitle);
        $startedOn = $this->parseDate($this->startedOn);

        if ($title === '' || ! $startedOn) {
            return;
        }

        $link = $this->normalizedLink();

        if ($link === false) {
            $this->addError('newLinkUrl', 'Enter a valid URL.');

            return;
        }

        [$linkTitle, $linkUrl] = $link;

        $isDeliverable = $this->kind === 'deliverable';
        $finishOn = $isDeliverable || trim($this->finishOn) === '' ? null : $this->parseDate($this->finishOn);

        if ($finishOn !== null && $finishOn->lt($startedOn)) {
            $finishOn = $startedOn->copy();
        }

        $payload = [
            'title' => $title,
            'kind' => $isDeliverable ? 'deliverable' : 'timeline',
            'started_on' => $startedOn->toDateString(),
            'finish_on' => $finishOn?->toDateString(),
            'link_title' => $linkTitle,
            'link_url' => $linkUrl,
            'project_id' => $this->resolvedProjectId(),
        ];

        if ($this->editingId) {
            $item = RoadmapItem::query()->findOrFail($this->editingId);
            $item->update($payload);
        } else {
            $payload['completed_on'] = null;
            RoadmapItem::query()->create($payload);
            Onboarding::complete('timeline.create');
        }

        $this->resetFormFields();
        $this->creating = false;
        $this->editingId = null;
        $this->month = $startedOn->format('Y-m');
    }

    public function toggleComplete(int $id): void
    {
        $item = RoadmapItem::query()->findOrFail($id);
        $today = Clock::today();

        if ($item->completed_on !== null) {
            $item->update(['completed_on' => null]);

            return;
        }

        $start = $item->rangeStart();
        $completedOn = $today->lt($start) ? $start : $today;

        $item->update(['completed_on' => $completedOn->toDateString()]);
        Onboarding::complete('timeline.complete');
    }

    public function updateStartedOn(int $id, string $date): void
    {
        $startedOn = $this->parseDate($date);

        if (! $startedOn) {
            return;
        }

        $item = RoadmapItem::query()->findOrFail($id);
        $payload = ['started_on' => $startedOn->toDateString()];

        if ($item->completed_on !== null && $item->completed_on->lt($startedOn)) {
            $payload['completed_on'] = $startedOn->toDateString();
        }

        if ($item->finish_on !== null && $item->finish_on->lt($startedOn)) {
            $payload['finish_on'] = $startedOn->toDateString();
        }

        $item->update($payload);
    }

    public function updateCompletedOn(int $id, ?string $date = null): void
    {
        $item = RoadmapItem::query()->findOrFail($id);

        if ($date === null || trim($date) === '') {
            $item->update(['completed_on' => null]);

            return;
        }

        $completedOn = $this->parseDate($date);

        if (! $completedOn) {
            return;
        }

        $start = $item->rangeStart();

        if ($completedOn->lt($start)) {
            $completedOn = $start;
        }

        $item->update(['completed_on' => $completedOn->toDateString()]);
    }

    /**
     * Set or clear the scheduled finish day; it can't fall before the start day.
     */
    public function updateFinishOn(int $id, ?string $date = null): void
    {
        $item = RoadmapItem::query()->findOrFail($id);

        if ($item->isDeliverable()) {
            return;
        }

        if ($date === null || trim($date) === '') {
            $item->update(['finish_on' => null]);

            return;
        }

        $finishOn = $this->parseDate($date);

        if (! $finishOn) {
            return;
        }

        $start = $item->rangeStart();

        if ($finishOn->lt($start)) {
            $finishOn = $start;
        }

        $item->update(['finish_on' => $finishOn->toDateString()]);
        Onboarding::complete('timeline.finish');
    }

    public function deleteItem(int $id): void
    {
        RoadmapItem::query()->whereKey($id)->delete();
    }

    public function monthStart(): Carbon
    {
        return $this->parseMonth($this->month) ?? Clock::today()->startOfMonth()->startOfDay();
    }

    public function render()
    {
        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $today = Clock::today();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $items = RoadmapItem::query()
            ->with('project')
            ->whereDate('started_on', '<=', $gridEnd->toDateString())
            ->where(function ($query) use ($gridStart): void {
                $query->whereNull('completed_on')
                    ->orWhereDate('completed_on', '>=', $gridStart->toDateString())
                    ->orWhereDate('finish_on', '>=', $gridStart->toDateString());
            })
            ->orderBy('started_on')
            ->orderBy('id')
            ->get();

        $visibleItems = $this->sortByUrgency(
            $items->filter(fn (RoadmapItem $item) => $this->overlaps($item, $monthStart, $monthEnd, $today))
        );

        return view('livewire.roadmaps', [
            'monthLabel' => strtoupper($monthStart->format('F Y')),
            'isCurrentMonth' => $monthStart->format('Y-m') === Clock::currentMonth(),
            'yearMinimap' => $this->yearMinimap($monthStart, $today),
            'today' => $today->toDateString(),
            'weeks' => $this->weeks($gridStart, $gridEnd, $monthStart, $monthEnd, $items, $today),
            'visibleItems' => $visibleItems,
            'deliverableItems' => $visibleItems->filter(fn (RoadmapItem $item): bool => $item->isDeliverable())->values(),
            'timelineItems' => $visibleItems->reject(fn (RoadmapItem $item): bool => $item->isDeliverable())->values(),
            'clients' => $this->associableProjects('partners'),
            'assets' => $this->associableProjects('assets'),
            'activeCount' => RoadmapItem::query()
                ->whereDate('started_on', '<=', $today->toDateString())
                ->whereNull('completed_on')
                ->count(),
            'completedCount' => RoadmapItem::query()->whereNotNull('completed_on')->count(),
            'notStartedCount' => RoadmapItem::query()
                ->whereDate('started_on', '>', $today->toDateString())
                ->whereNull('completed_on')
                ->count(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, active: bool, weeks: list<list<array{inMonth: bool, colors: list<string>}>>}>
     */
    private function yearMinimap(Carbon $monthStart, Carbon $today): array
    {
        $year = (int) $monthStart->year;
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->startOfDay();

        $items = RoadmapItem::query()
            ->whereDate('started_on', '<=', $yearEnd->toDateString())
            ->where(function ($query) use ($yearStart): void {
                $query->whereNull('completed_on')
                    ->orWhereDate('completed_on', '>=', $yearStart->toDateString())
                    ->orWhereDate('finish_on', '>=', $yearStart->toDateString());
            })
            ->orderBy('started_on')
            ->orderBy('id')
            ->get();

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $gridEnd = $start->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            $weeks = [];

            while ($cursor->lte($gridEnd)) {
                $days = [];

                for ($i = 0; $i < 7; $i++) {
                    $day = $cursor->copy()->addDays($i);
                    $days[] = [
                        'inMonth' => $day->month === $month,
                        'colors' => $day->month === $month ? $this->minimapColors($items, $day, $today) : [],
                    ];
                }

                $weeks[] = $days;
                $cursor->addWeek();
            }

            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => strtoupper($start->format('M')),
                'active' => $start->format('Y-m') === $monthStart->format('Y-m'),
                'weeks' => $weeks,
            ];
        }

        return $months;
    }

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return list<string>
     */
    private function minimapColors(Collection $items, Carbon $day, Carbon $today): array
    {
        $colors = [];

        foreach ($items as $item) {
            if ($item->rangeStart()->gt($day) || $item->rangeEnd($today)->lt($day)) {
                continue;
            }

            $colors[] = $item->isPending()
                ? self::PENDING_COLORS[$this->colorIndexFor($item->id)]
                : self::DONE_COLOR;
        }

        return array_values(array_unique($colors));
    }

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return list<array{days: list<Carbon>, lanes: list<list<array{id: int, title: string, pending: bool, colorIndex: int, col: int, span: int, continuesLeft: bool, continuesRight: bool, markers: list<array{type: string, at: int, date: string}>, fromDot: bool, toDot: bool}>>}>
     */
    private function weeks(Carbon $gridStart, Carbon $gridEnd, Carbon $monthStart, Carbon $monthEnd, Collection $items, Carbon $today): array
    {
        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $days[] = $cursor->copy()->addDays($i);
            }

            $weeks[] = [
                'days' => $days,
                'lanes' => $this->lanesForWeek($days[0], $days[6], $monthStart, $monthEnd, $items, $today),
            ];

            $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return list<list<array{id: int, title: string, pending: bool, colorIndex: int, col: int, span: int, continuesLeft: bool, continuesRight: bool, markers: list<array{type: string, at: int, date: string}>, fromDot: bool, toDot: bool}>>
     */
    private function lanesForWeek(Carbon $weekStart, Carbon $weekEnd, Carbon $monthStart, Carbon $monthEnd, Collection $items, Carbon $today): array
    {
        $segments = [];

        foreach ($items as $item) {
            $originalStart = $item->rangeStart();
            $originalEnd = $item->rangeEnd($today);
            $rangeStart = $originalStart->lt($monthStart) ? $monthStart->copy() : $originalStart;
            $rangeEnd = $originalEnd->gt($monthEnd) ? $monthEnd->copy() : $originalEnd;

            if ($rangeEnd->lt($weekStart) || $rangeStart->gt($weekEnd)) {
                continue;
            }

            $segStart = $rangeStart->greaterThan($weekStart) ? $rangeStart : $weekStart;
            $segEnd = $rangeEnd->lessThan($weekEnd) ? $rangeEnd : $weekEnd;
            $col = $segStart->dayOfWeekIso;
            $span = (int) $segStart->diffInDays($segEnd) + 1;
            $markers = $this->markersFor($item, $originalStart, $segStart, $segEnd);
            $hasStartMarker = collect($markers)->contains(fn (array $m): bool => in_array($m['type'], ['start', 'star'], true) && $m['at'] === 0);
            $hasEndMarker = collect($markers)->contains(fn (array $m): bool => ! in_array($m['type'], ['start', 'star'], true) && $m['at'] === $span - 1);

            $segments[] = [
                'id' => $item->id,
                'title' => $item->title,
                'pending' => $item->isPending(),
                'colorIndex' => $this->colorIndexFor($item->id),
                'col' => $col,
                'span' => $span,
                'continuesLeft' => $originalStart->lt($segStart),
                'continuesRight' => $originalEnd->gt($segEnd),
                'markers' => $markers,
                'fromDot' => $hasStartMarker && ! ($span === 1 && $hasEndMarker),
                'toDot' => $hasEndMarker && ! ($span === 1 && $hasStartMarker),
            ];
        }

        usort($segments, function (array $a, array $b): int {
            return [$a['col'], -$a['span'], $a['id']] <=> [$b['col'], -$b['span'], $b['id']];
        });

        $lanes = [];

        foreach ($segments as $segment) {
            $placed = false;
            $segEnd = $segment['col'] + $segment['span'] - 1;

            foreach ($lanes as $index => $lane) {
                $fits = true;

                foreach ($lane as $existing) {
                    $existingEnd = $existing['col'] + $existing['span'] - 1;

                    if ($segment['col'] <= $existingEnd && $existing['col'] <= $segEnd) {
                        $fits = false;
                        break;
                    }
                }

                if ($fits) {
                    $lanes[$index][] = $segment;
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $lanes[] = [$segment];
            }
        }

        return $lanes;
    }

    /**
     * Icons drawn on one week segment, positioned by day offset ("at") within it:
     * start (start day), finish (scheduled finish day, same icon as start) and
     * done (checkmark on the completion day). Done is listed last so it sits on top.
     *
     * @return list<array{type: string, at: int, date: string}>
     */
    private function markersFor(RoadmapItem $item, Carbon $start, Carbon $segStart, Carbon $segEnd): array
    {
        $markers = [];
        $within = fn (Carbon $day): bool => ! $day->lt($segStart) && ! $day->gt($segEnd);
        $offset = fn (Carbon $day): int => (int) $segStart->diffInDays($day);

        if ($within($start)) {
            $markers[] = [
                'type' => $item->isDeliverable() ? 'star' : 'start',
                'at' => $offset($start),
                'date' => $start->toDateString(),
            ];
        }

        if ($item->isDeliverable()) {
            return $markers;
        }

        $finish = $item->scheduledFinish();
        $completed = $item->completed_on?->copy()->startOfDay();

        if ($finish !== null && $within($finish) && ! ($completed !== null && $completed->equalTo($finish))) {
            $markers[] = ['type' => 'finish', 'at' => $offset($finish), 'date' => $finish->toDateString()];
        }

        if ($completed !== null && $within($completed)) {
            $markers[] = ['type' => 'done', 'at' => $offset($completed), 'date' => $completed->toDateString()];
        }

        return $markers;
    }

    /**
     * Soonest due date first. A deliverable is due on its single day. A timeline with no finish date
     * sorts after dated ones, earliest start first.
     *
     * @param  Collection<int, RoadmapItem>  $items
     * @return Collection<int, RoadmapItem>
     */
    private function sortByUrgency(Collection $items): Collection
    {
        return $items
            ->sortBy(function (RoadmapItem $item): array {
                $due = $item->isDeliverable() ? $item->started_on : $item->finish_on;

                return [
                    $due === null ? 1 : 0,
                    $due?->toDateString() ?? '',
                    $item->started_on->toDateString(),
                    $item->id,
                ];
            })
            ->values();
    }

    private function overlaps(RoadmapItem $item, Carbon $from, Carbon $to, Carbon $today): bool
    {
        return ! $item->rangeEnd($today)->lt($from) && ! $item->rangeStart()->gt($to);
    }

    public function colorIndexFor(int $id): int
    {
        return (($id % self::PENDING_COLOR_COUNT) + self::PENDING_COLOR_COUNT) % self::PENDING_COLOR_COUNT;
    }

    private function resetFormFields(): void
    {
        $this->newTitle = '';
        $this->kind = 'timeline';
        $this->finishOn = '';
        $this->newLinkTitle = '';
        $this->newLinkUrl = '';
        $this->newProjectId = '';
        $this->resetErrorBag();
    }

    /**
     * @return array{0: ?string, 1: ?string}|false
     */
    private function normalizedLink(): array|false
    {
        $title = trim($this->newLinkTitle);
        $url = trim($this->newLinkUrl);

        if ($url === '') {
            return [null, null];
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return [$title !== '' ? $title : null, $url];
    }

    private function resolvedProjectId(): ?int
    {
        if ($this->newProjectId === '' || $this->newProjectId === '0') {
            return null;
        }

        $id = (int) $this->newProjectId;
        $currentId = $this->editingId
            ? RoadmapItem::query()->whereKey($this->editingId)->value('project_id')
            : null;

        $exists = Project::query()
            ->whereKey($id)
            ->whereIn('hub', ['partners', 'assets'])
            ->where('is_delimiter', false)
            ->where(function ($query) use ($currentId): void {
                $query->where('is_archived', false);

                if ($currentId) {
                    $query->orWhereKey($currentId);
                }
            })
            ->exists();

        return $exists ? $id : null;
    }

    /**
     * @return Collection<int, Project>
     */
    private function associableProjects(string $hub): Collection
    {
        $query = Project::query()
            ->where('hub', $hub)
            ->where('is_delimiter', false)
            ->where(function ($q): void {
                $q->where('is_archived', false);

                if ($this->editingId) {
                    $currentId = RoadmapItem::query()->whereKey($this->editingId)->value('project_id');

                    if ($currentId) {
                        $q->orWhereKey($currentId);
                    }
                }
            })
            ->orderBy('position')
            ->orderBy('name');

        return $query->get();
    }

    private function parseMonth(string $month): ?Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            return null;
        }

        $year = (int) $m[1];
        $monthNumber = (int) $m[2];

        if ($monthNumber < 1 || $monthNumber > 12) {
            return null;
        }

        return Carbon::create($year, $monthNumber, 1)->startOfDay();
    }

    private function parseDate(string $date): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);

            return $parsed ? $parsed->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
