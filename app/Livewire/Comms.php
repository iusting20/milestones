<?php

namespace App\Livewire;

use App\Enums\CommunicationType;
use App\Models\CommNote;
use App\Models\Communication;
use App\Models\Person;
use App\Models\Project;
use App\Support\Clock;
use App\Support\Onboarding;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class Comms extends Component
{
    #[Url(as: 'month', history: true)]
    public string $month = '';

    public string $occurredOn = '';

    public string $audience = 'clients';

    public string $projectId = '';

    public string $personId = '';

    public string $newPersonName = '';

    public string $type = '';

    public bool $adding = false;

    public bool $addingPerson = false;

    public bool $notesOpen = false;

    public ?int $notesProjectId = null;

    public ?int $notesPersonId = null;

    public string $notesSubjectName = '';

    public ?int $noteId = null;

    public string $noteBody = '';

    public string $noteDate = '';

    public ?string $noteUpdatedAt = null;

    public bool $noteDirty = false;

    public int $noteIndex = 0;

    public int $noteCount = 0;

    public function mount(): void
    {
        if ($this->month === '' || ! $this->parseMonth($this->month)) {
            $this->month = Clock::currentMonth();
        }

        if ($this->occurredOn === '') {
            $this->occurredOn = Clock::todayString();
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
        $this->occurredOn = Clock::todayString();
    }

    public function selectDay(string $date): void
    {
        if (! $this->parseDate($date)) {
            return;
        }

        $this->occurredOn = $date;
        $this->adding = true;
    }

    public function closeEntry(): void
    {
        $this->adding = false;
        $this->addingPerson = false;
        $this->newPersonName = '';
    }

    public function setAudience(string $audience): void
    {
        if (! in_array($audience, ['clients', 'assets', 'people'], true)) {
            return;
        }

        if ($this->audience !== $audience) {
            $this->projectId = '';
            $this->personId = '';
            $this->addingPerson = false;
            $this->newPersonName = '';
        }

        $this->audience = $audience;
    }

    public function selectProject(int $projectId): void
    {
        $hub = match ($this->audience) {
            'assets' => 'assets',
            'clients' => 'partners',
            default => null,
        };

        if ($hub === null || ! $this->projectsQuery($hub)->whereKey($projectId)->exists()) {
            return;
        }

        $this->projectId = (string) $projectId;
        $this->personId = '';
    }

    public function selectPerson(int $personId): void
    {
        if (! Person::query()->whereKey($personId)->exists()) {
            return;
        }

        $this->audience = 'people';
        $this->personId = (string) $personId;
        $this->projectId = '';
    }

    public function setType(string $type): void
    {
        if (CommunicationType::tryFrom($type) === null) {
            return;
        }

        $this->type = $type;
    }

    public function startAddingPerson(): void
    {
        $this->audience = 'people';
        $this->addingPerson = true;
    }

    public function addPerson(): void
    {
        $name = trim($this->newPersonName);

        if ($name === '' || Str::length($name) > 255) {
            return;
        }

        $person = Person::query()->create([
            'name' => $name,
        ]);

        $this->audience = 'people';
        $this->personId = (string) $person->id;
        $this->projectId = '';
        $this->newPersonName = '';
        $this->addingPerson = false;
    }

    public function updatedOccurredOn(string $value): void
    {
        $occurredOn = $this->parseDate($value);

        if (! $occurredOn) {
            return;
        }

        $this->month = $occurredOn->format('Y-m');
    }

    public function addEntry(): void
    {
        $occurredOn = $this->parseDate($this->occurredOn);
        $type = CommunicationType::tryFrom($this->type);

        if (! $occurredOn || ! $type) {
            return;
        }

        if ($this->audience === 'people') {
            $personId = ctype_digit($this->personId) ? (int) $this->personId : 0;
            $person = $personId === 0 ? null : Person::query()->whereKey($personId)->first();

            if (! $person) {
                return;
            }

            Communication::query()->create([
                'person_id' => $person->id,
                'type' => $type,
                'occurred_on' => $occurredOn->toDateString(),
            ]);
        } else {
            $projectId = ctype_digit($this->projectId) ? (int) $this->projectId : 0;
            $hub = $this->audience === 'assets' ? 'assets' : 'partners';
            $project = $projectId === 0 ? null : $this->projectsQuery($hub)->whereKey($projectId)->first();

            if (! $project) {
                return;
            }

            Communication::query()->create([
                'project_id' => $project->id,
                'type' => $type,
                'occurred_on' => $occurredOn->toDateString(),
            ]);
        }

        $this->month = $occurredOn->format('Y-m');
        $this->adding = false;
        Onboarding::complete('comms.entry');
        $this->addingPerson = false;
        $this->newPersonName = '';
    }

    public function deleteEntry(int $id): void
    {
        Communication::query()->whereKey($id)->delete();
    }

    public function openNotes(string $kind, int $id): void
    {
        if ($kind === 'project') {
            $project = $this->findNoteProject($id);
            if (! $project) {
                return;
            }

            $this->notesProjectId = $project->id;
            $this->notesPersonId = null;
            $this->notesSubjectName = $project->name;
        } elseif ($kind === 'person') {
            $person = Person::query()->whereKey($id)->first();
            if (! $person) {
                return;
            }

            $this->notesPersonId = $person->id;
            $this->notesProjectId = null;
            $this->notesSubjectName = $person->name;
        } else {
            return;
        }

        $this->notesOpen = true;
        $this->loadLatestNote();
    }

    public function closeNotes(): void
    {
        $this->notesOpen = false;
        $this->notesProjectId = null;
        $this->notesPersonId = null;
        $this->notesSubjectName = '';
        $this->resetNoteForm();
    }

    public function startNewNote(): void
    {
        if (! $this->hasNotesSubject()) {
            return;
        }

        $this->noteId = null;
        $this->noteBody = '';
        $this->noteDate = Clock::todayString();
        $this->noteUpdatedAt = null;
        $this->noteDirty = false;
        $this->noteIndex = 0;
        $this->noteCount = $this->notesQuery()->count();
    }

    public function saveNote(): void
    {
        $attributes = $this->notesSubjectAttributes();
        if ($attributes === null) {
            return;
        }

        $body = trim($this->noteBody);
        if ($body === '') {
            return;
        }

        if ($this->noteId) {
            $note = $this->notesQuery()->whereKey($this->noteId)->first();
            if (! $note) {
                return;
            }

            $note->update(['body' => $body]);
        } else {
            $note = CommNote::query()->create([
                ...$attributes,
                'body' => $body,
                'note_date' => Clock::todayString(),
            ]);
        }

        $this->loadNote($note);
    }

    public function previousNote(): void
    {
        $this->stepNote(1);
    }

    public function nextNote(): void
    {
        $this->stepNote(-1);
    }

    public function updatedNoteBody(): void
    {
        $this->noteDirty = true;
    }

    public function monthStart(): Carbon
    {
        return $this->parseMonth($this->month) ?? Clock::today()->startOfMonth()->startOfDay();
    }

    public function lastCommLabel(Carbon $occurredOn): string
    {
        return 'Last comm.: '.$this->elapsedLabel($occurredOn);
    }

    public function lastCommClass(?Carbon $occurredOn): string
    {
        $days = $occurredOn === null ? null : $this->elapsedDays($occurredOn);

        if ($days === null || $days >= 14) {
            return 'text-base font-medium text-red-400';
        }

        if ($days >= 7) {
            return 'text-base font-medium text-mark';
        }

        return 'text-base font-medium text-muted';
    }

    public function lastReviewLabel(Carbon $occurredOn): string
    {
        return 'Last review meeting: '.$this->elapsedLabel($occurredOn);
    }

    private function elapsedDays(Carbon $occurredOn): int
    {
        return (int) $occurredOn->copy()->startOfDay()->diff(Clock::today())->days;
    }

    private function elapsedLabel(Carbon $occurredOn): string
    {
        $days = $this->elapsedDays($occurredOn);

        if ($days === 0) {
            return 'today';
        }

        $weeks = intdiv($days, 7);
        $remainder = $days % 7;
        $parts = [];

        if ($weeks === 1) {
            $parts[] = '1 week';
        } elseif ($weeks > 1) {
            $parts[] = $weeks.' weeks';
        }

        if ($remainder === 1) {
            $parts[] = '1 day';
        } elseif ($remainder > 1) {
            $parts[] = $remainder.' days';
        }

        return implode(' + ', $parts).' ago';
    }

    public function render()
    {
        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);
        $clients = $this->clientsQuery()->get();
        $assets = $this->projectsQuery('assets')->get();
        $people = Person::query()->orderBy('name')->orderBy('id')->get();

        $projectIds = $clients->pluck('id')->merge($assets->pluck('id'));
        $personIds = $people->pluck('id');

        $entries = Communication::query()
            ->with(['project', 'person'])
            ->whereDate('occurred_on', '>=', $gridStart->toDateString())
            ->whereDate('occurred_on', '<=', $gridEnd->toDateString())
            ->where(function (Builder $query) use ($projectIds, $personIds): void {
                $query->whereIn('project_id', $projectIds)
                    ->orWhereIn('person_id', $personIds);
            })
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $selectedDate = $this->parseDate($this->occurredOn)?->toDateString();

        $history = Communication::query()
            ->where(function (Builder $query) use ($projectIds, $personIds): void {
                $query->whereIn('project_id', $projectIds)
                    ->orWhereIn('person_id', $personIds);
            })
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        [$latestByProject, $latestReviewByProject] = $this->latestBy($history->whereNotNull('project_id'), 'project_id');
        [$latestByPerson, $latestReviewByPerson] = $this->latestBy($history->whereNotNull('person_id'), 'person_id');

        return view('livewire.comms', [
            'monthLabel' => strtoupper($monthStart->format('F Y')),
            'today' => Clock::todayString(),
            'clients' => $clients,
            'projectsForAudience' => $this->audience === 'assets' ? $assets : $clients,
            'people' => $people,
            'weeks' => $this->weeks($gridStart, $gridEnd, $entries),
            'selectedEntries' => $selectedDate
                ? $entries->filter(fn (Communication $entry) => $entry->occurred_on->toDateString() === $selectedDate)->values()
                : collect(),
            'panels' => [
                [
                    'title' => 'Clients',
                    'kind' => 'project',
                    'rows' => $this->byStaleness($clients, $latestByProject),
                    'latest' => $latestByProject,
                    'reviews' => $latestReviewByProject,
                    'empty' => 'No clients',
                ],
                [
                    'title' => 'Key people',
                    'kind' => 'person',
                    'rows' => $this->byStaleness($people, $latestByPerson),
                    'latest' => $latestByPerson,
                    'reviews' => $latestReviewByPerson,
                    'empty' => 'No key people',
                ],
                [
                    'title' => 'Assets',
                    'kind' => 'project',
                    'rows' => $this->byStaleness($assets, $latestByProject),
                    'latest' => $latestByProject,
                    'reviews' => $latestReviewByProject,
                    'empty' => 'No assets',
                ],
            ],
            'types' => CommunicationType::cases(),
        ]);
    }

    /**
     * @param  Collection<int, Project|Person>  $rows
     * @param  Collection<int, Communication>  $latest
     * @return Collection<int, Project|Person>
     */
    private function byStaleness(Collection $rows, Collection $latest): Collection
    {
        return $rows->sortBy(function (Project|Person $row) use ($latest): array {
            $occurredOn = $latest->get($row->id)?->occurred_on;

            return [
                $occurredOn === null ? 0 : 1,
                $occurredOn?->timestamp ?? 0,
                $row->position ?? 0,
                $row->name,
                $row->id,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, Communication>  $history
     * @return array{0: Collection<int, Communication>, 1: Collection<int, Communication>}
     */
    private function latestBy(Collection $history, string $key): array
    {
        return [
            $history->unique($key)->keyBy($key),
            $history->where('type', CommunicationType::ReviewPlanning)->unique($key)->keyBy($key),
        ];
    }

    /**
     * @param  Collection<int, Communication>  $entries
     * @return list<list<array{date: Carbon, entries: Collection<int, Communication>}>>
     */
    private function weeks(Carbon $gridStart, Carbon $gridEnd, Collection $entries): array
    {
        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $days = [];

            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->copy();
                $key = $date->toDateString();
                $days[] = [
                    'date' => $date,
                    'entries' => $entries
                        ->filter(fn (Communication $entry) => $entry->occurred_on->toDateString() === $key)
                        ->values(),
                ];
                $cursor->addDay();
            }

            $weeks[] = $days;
        }

        return $weeks;
    }

    /**
     * @return Builder<Project>
     */
    private function clientsQuery(): Builder
    {
        return $this->projectsQuery('partners');
    }

    /**
     * @return Builder<Project>
     */
    private function projectsQuery(string $hub): Builder
    {
        return Project::query()
            ->where('hub', $hub)
            ->where('is_delimiter', false)
            ->where('is_archived', false)
            ->where('track_comms', true)
            ->orderBy('position')
            ->orderBy('id');
    }

    private function parseMonth(string $month): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $month.'-01')?->startOfMonth()->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDate(string $date): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date)?->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if (! $parsed || $parsed->toDateString() !== $date) {
            return null;
        }

        return $parsed;
    }

    private function findNoteProject(int $projectId): ?Project
    {
        return Project::query()
            ->whereKey($projectId)
            ->where('is_delimiter', false)
            ->whereIn('hub', ['partners', 'assets'])
            ->first();
    }

    private function hasNotesSubject(): bool
    {
        return $this->notesProjectId !== null || $this->notesPersonId !== null;
    }

    /**
     * @return array{project_id: int, person_id: null}|array{project_id: null, person_id: int}|null
     */
    private function notesSubjectAttributes(): ?array
    {
        if ($this->notesProjectId) {
            $project = $this->findNoteProject($this->notesProjectId);
            if (! $project) {
                return null;
            }

            return ['project_id' => $project->id, 'person_id' => null];
        }

        if ($this->notesPersonId) {
            $person = Person::query()->whereKey($this->notesPersonId)->first();
            if (! $person) {
                return null;
            }

            return ['project_id' => null, 'person_id' => $person->id];
        }

        return null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<CommNote>
     */
    private function notesQuery(): Builder
    {
        $query = CommNote::query()
            ->orderByDesc('note_date')
            ->orderByDesc('id');

        if ($this->notesPersonId) {
            return $query->where('person_id', $this->notesPersonId)->whereNull('project_id');
        }

        if ($this->notesProjectId) {
            return $query->where('project_id', $this->notesProjectId)->whereNull('person_id');
        }

        return $query->whereRaw('0 = 1');
    }

    private function loadLatestNote(): void
    {
        if (! $this->hasNotesSubject()) {
            $this->resetNoteForm();

            return;
        }

        $note = $this->notesQuery()->first();
        if ($note) {
            $this->loadNote($note);

            return;
        }

        $this->noteId = null;
        $this->noteBody = '';
        $this->noteDate = '';
        $this->noteUpdatedAt = null;
        $this->noteDirty = false;
        $this->noteIndex = 0;
        $this->noteCount = 0;
    }

    private function loadNote(CommNote $note): void
    {
        $notes = $this->notesQuery()->get(['id']);
        $index = $notes->search(fn (CommNote $row) => $row->id === $note->id);
        if ($index === false) {
            $index = 0;
        }

        $this->noteId = $note->id;
        $this->noteBody = $note->body;
        $this->noteDate = $note->note_date?->toDateString() ?? '';
        $this->noteUpdatedAt = $note->updated_at
            ? $note->updated_at->timezone(Clock::timezone())->format('j M Y, H:i')
            : null;
        $this->noteDirty = false;
        $this->noteIndex = (int) $index + 1;
        $this->noteCount = $notes->count();
    }

    private function stepNote(int $delta): void
    {
        if (! $this->hasNotesSubject()) {
            return;
        }

        $notes = $this->notesQuery()->get();
        if ($notes->isEmpty()) {
            return;
        }

        if ($this->noteId === null) {
            if ($delta > 0) {
                $this->loadNote($notes->first());
            }

            return;
        }

        $index = $notes->search(fn (CommNote $row) => $row->id === $this->noteId);
        if ($index === false) {
            $this->loadNote($notes->first());

            return;
        }

        $next = $index + $delta;
        if ($next < 0 || $next >= $notes->count()) {
            return;
        }

        $this->loadNote($notes[$next]);
    }

    private function resetNoteForm(): void
    {
        $this->noteId = null;
        $this->noteBody = '';
        $this->noteDate = '';
        $this->noteUpdatedAt = null;
        $this->noteDirty = false;
        $this->noteIndex = 0;
        $this->noteCount = 0;
    }
}
