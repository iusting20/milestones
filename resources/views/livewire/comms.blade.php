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
                            <div wire:key="chips-{{ $day['date']->toDateString() }}" class="min-h-2 space-y-0.5" x-data="{ open: false }">
                                @foreach ($day['entries']->take(3) as $entry)
                                    <div title="{{ $entry->subjectName() }} · {{ $entry->type->label() }}"
                                         x-on:click="open = true"
                                         @class([
                                             'cursor-pointer truncate rounded-sm px-1 py-0.5 text-center text-[10px] leading-tight',
                                             'bg-check/20 text-link' => $entry->type === \App\Enums\CommunicationType::QuickUpdate,
                                             'bg-mark/20 text-mark' => $entry->type === \App\Enums\CommunicationType::ReviewPlanning,
                                         ])>
                                        {{ $entry->subjectName() }}
                                    </div>
                                @endforeach
                                @if ($day['entries']->count() > 3)
                                    <button type="button" x-on:click="open = true" class="block w-full cursor-pointer px-1 text-center text-[10px] text-muted hover:text-link">+{{ $day['entries']->count() - 3 }}</button>
                                @endif
                                @if ($day['entries']->isNotEmpty())
                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak x-on:keydown.escape.window="open = false"
                                             class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                                            <div class="absolute inset-0 bg-black/60" x-on:click="open = false" aria-hidden="true"></div>
                                            <div class="relative z-10 max-h-[80vh] w-full max-w-sm overflow-y-auto rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl">
                                                <div class="mb-3 flex items-center justify-between gap-3">
                                                    <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                                                        {{ $day['date']->format('D j M') }} · {{ $day['entries']->count() }} comms
                                                    </h2>
                                                    <button type="button" x-on:click="open = false" class="text-muted hover:text-body" aria-label="Close">×</button>
                                                </div>
                                                <ul class="space-y-1.5">
                                                    @foreach ($day['entries'] as $entry)
                                                        <li @class([
                                                            'flex items-center justify-between gap-3 rounded px-2 py-1.5 text-sm',
                                                            'bg-check/20 text-link' => $entry->type === \App\Enums\CommunicationType::QuickUpdate,
                                                            'bg-mark/20 text-mark' => $entry->type === \App\Enums\CommunicationType::ReviewPlanning,
                                                        ])>
                                                            <span class="min-w-0 truncate">{{ $entry->subjectName() }}</span>
                                                            <span class="shrink-0 text-xs opacity-80">{{ $entry->type->label() }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </template>
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
                                    <td class="border-b border-white/5 py-2 pr-4 text-left text-sm text-body">
                                        @if (($panel['kind'] ?? 'person') === 'project')
                                            <button type="button"
                                                    wire:click="openNotes({{ $row->id }})"
                                                    class="text-left text-link hover:underline"
                                                    aria-haspopup="dialog">
                                                {{ $row->name }}
                                            </button>
                                        @else
                                            {{ $row->name }}
                                        @endif
                                    </td>
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

    @if ($notesOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data="{
                leave(fn) {
                    if (! $wire.noteDirty || confirm('Discard unsaved changes?')) {
                        fn();
                    }
                }
             }"
             x-on:keydown.escape.window="leave(() => $wire.closeNotes())"
             role="dialog"
             aria-modal="true"
             aria-labelledby="comm-notes-title">
            <div class="absolute inset-0 bg-black/60" x-on:click="leave(() => $wire.closeNotes())" aria-hidden="true"></div>

            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 id="comm-notes-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        Notes · {{ $notesProjectName }}
                    </h2>
                    <button type="button"
                            x-on:click="leave(() => $wire.closeNotes())"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body"
                            aria-label="Close">×</button>
                </div>

                @if ($noteId === null && $noteDate === '')
                    <p class="mb-4 text-sm text-muted">No notes yet.</p>
                    <div class="flex justify-end">
                        <button type="button"
                                wire:click="startNewNote"
                                class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium hover:border-link/40">
                            New note
                        </button>
                    </div>
                @else
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm text-body">
                            {{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $noteDate)->format('j M Y') }}
                            @if ($noteId === null)
                                <span class="text-xs text-muted">new</span>
                            @endif
                        </p>
                        <p class="text-xs tabular-nums text-muted">
                            @if ($noteId === null)
                                New
                            @elseif ($noteCount > 0)
                                {{ $noteIndex }} of {{ $noteCount }}
                            @endif
                        </p>
                    </div>

                    <label class="block">
                        <span class="sr-only">Note</span>
                        <textarea wire:model.live="noteBody"
                                  rows="10"
                                  x-data
                                  x-init="$nextTick(() => $el.focus())"
                                  class="w-full resize-y rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none"
                                  placeholder="Write a note…"></textarea>
                    </label>

                    @if ($noteUpdatedAt)
                        <p class="mt-2 text-xs text-muted">Updated {{ $noteUpdatedAt }}</p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="button"
                                x-on:click="leave(() => $wire.previousNote())"
                                @disabled($noteId === null ? $noteCount === 0 : $noteIndex >= $noteCount)
                                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="Previous (older) note">
                            Prev
                        </button>
                        <button type="button"
                                x-on:click="leave(() => $wire.nextNote())"
                                @disabled($noteId === null || $noteIndex <= 1)
                                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="Next (newer) note">
                            Next
                        </button>
                        <button type="button"
                                x-on:click="leave(() => $wire.startNewNote())"
                                class="rounded border border-white/10 px-3 py-1.5 text-sm text-link hover:bg-panel">
                            New note
                        </button>
                        <button type="button"
                                wire:click="saveNote"
                                class="ml-auto rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium hover:border-link/40">
                            Save
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
