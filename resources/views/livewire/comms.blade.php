<div class="mx-auto max-w-5xl px-3 py-6 sm:px-4">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <button type="button" wire:click="previousMonth"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Previous month">‹</button>

        <h1 class="inline-block bg-mark px-3 py-1 text-sm font-bold uppercase tracking-wide text-black sm:text-base">
            Comms - {{ $monthLabel }}
        </h1>

        <button type="button" wire:click="nextMonth"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Next month">›</button>

        <button type="button" wire:click="goToThisMonth"
                class="ml-auto rounded border border-white/10 px-3 py-1.5 text-sm text-link no-underline hover:bg-panel">
            Today
        </button>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[11px] uppercase tracking-[0.18em] text-muted">
        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-check"></span> quick update</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-mark"></span> review & planning meeting</span>
    </div>

    <div class="overflow-hidden rounded-lg border border-white/10 bg-sidebar/80">
        <div class="grid grid-cols-7 border-b border-white/10 text-center text-[10px] font-semibold uppercase tracking-[0.2em] text-muted">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                <div class="px-1 py-2">{{ $weekday }}</div>
            @endforeach
        </div>

        <div class="divide-y divide-white/5">
            @foreach ($weeks as $week)
                <div wire:key="week-{{ $week[0]['date']->toDateString() }}" class="px-1 py-1.5 sm:px-2">
                    <div class="grid grid-cols-7">
                        @foreach ($week as $day)
                            <button type="button"
                                    wire:key="day-{{ $day['date']->toDateString() }}"
                                    wire:click="selectDay('{{ $day['date']->toDateString() }}')"
                                    @class([
                                        'relative mx-auto flex h-8 w-8 cursor-pointer items-center justify-center rounded-full text-xs tabular-nums transition',
                                        'text-faint' => $day['date']->format('Y-m') !== $this->month,
                                        'text-body' => $day['date']->format('Y-m') === $this->month && $day['date']->toDateString() !== $today,
                                        'bg-check text-white font-semibold' => $day['date']->toDateString() === $today,
                                        'ring-1 ring-mark' => $occurredOn === $day['date']->toDateString() && $day['date']->toDateString() !== $today,
                                    ])>
                                {{ $day['date']->day }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-1 grid grid-cols-7 gap-x-0.5">
                        @foreach ($week as $day)
                            <div wire:key="chips-{{ $day['date']->toDateString() }}" class="min-h-2 space-y-0.5">
                                @foreach ($day['entries']->take(3) as $entry)
                                    <div title="{{ $entry->subjectName() }} · {{ $entry->type->label() }}"
                                         @class([
                                             'truncate rounded-sm px-1 py-0.5 text-center text-[10px] leading-tight',
                                             'bg-check/20 text-link' => $entry->type === \App\Enums\CommunicationType::QuickUpdate,
                                             'bg-mark/20 text-mark' => $entry->type === \App\Enums\CommunicationType::ReviewPlanning,
                                         ])>
                                        {{ $entry->subjectName() }}
                                    </div>
                                @endforeach
                                @if ($day['entries']->count() > 3)
                                    <div class="px-1 text-center text-[10px] text-muted">+{{ $day['entries']->count() - 3 }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($adding)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             wire:keydown.escape.window="closeEntry"
             role="dialog"
             aria-modal="true"
             aria-labelledby="add-entry-title">
            <div class="absolute inset-0 bg-black/60" wire:click="closeEntry" aria-hidden="true"></div>

            <div class="relative z-10 max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="add-entry-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        Add entry
                    </h2>
                    <button type="button"
                            wire:click="closeEntry"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body"
                            aria-label="Close">×</button>
                </div>

                <form wire:submit="addEntry" class="flex flex-col gap-3">
                    <label class="text-xs uppercase tracking-[0.14em] text-muted">
                        Day
                        <input type="date"
                               wire:model="occurredOn"
                               class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                    </label>
                    <div>
                        <p id="who-label" class="text-xs uppercase tracking-[0.14em] text-muted">who?</p>
                        <div class="mt-1 flex flex-wrap gap-1.5" role="group" aria-labelledby="who-label">
                            @foreach (['clients' => 'clients', 'assets' => 'assets', 'people' => 'key people'] as $audienceKey => $audienceLabel)
                                <button type="button"
                                        wire:key="audience-{{ $audienceKey }}"
                                        wire:click="setAudience('{{ $audienceKey }}')"
                                        aria-pressed="{{ $audience === $audienceKey ? 'true' : 'false' }}"
                                        @class([
                                            'rounded border px-3 py-1.5 text-sm normal-case tracking-normal',
                                            'border-link bg-check/20 text-link' => $audience === $audienceKey,
                                            'border-white/10 text-muted hover:text-body' => $audience !== $audienceKey,
                                        ])>
                                    {{ $audienceLabel }}
                                </button>
                            @endforeach
                        </div>
                        @if ($audience === 'people')
                            <select wire:model="personId"
                                    class="mt-2 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                                <option value="">Choose a person</option>
                                @foreach ($people as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <button type="button"
                                    wire:click="startAddingPerson"
                                    class="mt-2 rounded border border-dashed border-white/20 px-3 py-1.5 text-sm text-link hover:border-link/40">
                                Add person
                            </button>
                        @else
                            <select wire:model="projectId"
                                    class="mt-2 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                                <option value="">{{ $audience === 'assets' ? 'Choose an asset' : 'Choose a client' }}</option>
                                @foreach ($projectsForAudience as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if ($audience === 'people' && $addingPerson)
                            <div class="mt-2 flex gap-1.5">
                                <input type="text"
                                       wire:model="newPersonName"
                                       wire:keydown.enter.prevent="addPerson"
                                       placeholder="Name"
                                       x-data
                                       x-init="$nextTick(() => $el.focus())"
                                       class="min-w-0 flex-1 rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                                <button type="button"
                                        wire:click="addPerson"
                                        class="rounded border border-white/15 bg-panel px-3 py-1.5 text-sm font-medium hover:border-link/40">
                                    Add
                                </button>
                            </div>
                        @endif
                    </div>
                    <div>
                        <p id="type-label" class="text-xs uppercase tracking-[0.14em] text-muted">Type</p>
                        <div class="mt-1 flex flex-wrap gap-1.5" role="group" aria-labelledby="type-label">
                            @foreach ($types as $communicationType)
                                <button type="button"
                                        wire:key="type-{{ $communicationType->value }}"
                                        wire:click="setType('{{ $communicationType->value }}')"
                                        aria-pressed="{{ $type === $communicationType->value ? 'true' : 'false' }}"
                                        @class([
                                            'rounded border px-3 py-1.5 text-sm normal-case tracking-normal',
                                            'border-link bg-check/20 text-link' => $type === $communicationType->value && $communicationType === \App\Enums\CommunicationType::QuickUpdate,
                                            'border-mark bg-mark/20 text-mark' => $type === $communicationType->value && $communicationType === \App\Enums\CommunicationType::ReviewPlanning,
                                            'border-white/10 text-muted hover:text-body' => $type !== $communicationType->value,
                                        ])>
                                    {{ $communicationType->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-2 flex justify-end gap-2">
                        <button type="button"
                                wire:click="closeEntry"
                                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">
                            Cancel
                        </button>
                        <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium hover:border-link/40">
                            Add entry
                        </button>
                    </div>
                </form>

                @if ($selectedEntries->isNotEmpty())
                    <ul class="mt-4 space-y-1 border-t border-white/10 pt-3">
                        @foreach ($selectedEntries as $entry)
                            <li wire:key="selected-{{ $entry->id }}" class="group flex items-center gap-2 rounded px-1 py-1.5">
                                <span @class([
                                    'h-2 w-2 shrink-0 rounded-sm',
                                    'bg-check' => $entry->type === \App\Enums\CommunicationType::QuickUpdate,
                                    'bg-mark' => $entry->type === \App\Enums\CommunicationType::ReviewPlanning,
                                ])></span>
                                <span class="min-w-0 flex-1 truncate text-sm text-body">{{ $entry->subjectName() }}</span>
                                <span class="shrink-0 text-xs text-muted">{{ $entry->type->label() }}</span>
                                <button type="button"
                                        wire:click="deleteEntry({{ $entry->id }})"
                                        wire:confirm="Delete this communication?"
                                        class="shrink-0 px-1 text-xs text-muted hover:text-red-400"
                                        aria-label="Delete {{ $entry->subjectName() }}">×</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    <div class="mt-8 space-y-4">
        @foreach ($panels as $panel)
            <section wire:key="panel-{{ $panel['title'] }}" class="rounded-lg border border-white/10 bg-sidebar/80 p-4">
                <h2 class="text-center text-xs font-bold uppercase tracking-widest text-muted">{{ $panel['title'] }}</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full border-separate border-spacing-0">
                        <tbody>
                            @forelse ($panel['rows'] as $row)
                                @php
                                    $latest = $panel['latest']->get($row->id);
                                    $latestReview = $panel['reviews']->get($row->id);
                                @endphp
                                <tr wire:key="panel-row-{{ $panel['title'] }}-{{ $row->id }}" class="border-b border-white/5">
                                    <td class="border-b border-white/5 py-2 pr-4 text-left text-sm text-body">{{ $row->name }}</td>
                                    <td class="whitespace-nowrap border-b border-white/5 py-2 pr-8 text-left {{ $this->lastCommClass($latest?->occurred_on) }}">{{ $latest ? $this->lastCommLabel($latest->occurred_on) : 'Last comm.: none yet' }}</td>
                                    @if ($latestReview)
                                        <td class="whitespace-nowrap border-b border-white/5 py-2 text-left text-base font-medium text-muted">{{ $this->lastReviewLabel($latestReview->occurred_on) }}</td>
                                    @else
                                        <td class="whitespace-nowrap border-b border-white/5 py-2 text-left text-base font-medium text-faint">Last review meeting: none yet</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-2 text-sm text-muted">{{ $panel['empty'] }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</div>
