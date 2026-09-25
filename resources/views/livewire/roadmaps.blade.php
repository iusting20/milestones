<div class="mx-auto max-w-5xl px-3 py-6 sm:px-4">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <button type="button" wire:click="previousMonth"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Previous month">‹</button>

        <h1 class="inline-block bg-mark px-3 py-1 text-sm font-bold uppercase tracking-wide text-black sm:text-base">
            Roadmaps - {{ $monthLabel }}
        </h1>

        <button type="button" wire:click="nextMonth"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Next month">›</button>

        <button type="button" wire:click="goToThisMonth"
                class="ml-auto rounded border border-white/10 px-3 py-1.5 text-sm text-link no-underline hover:bg-panel">
            Today
        </button>
    </div>

    <div class="mb-3 flex items-center justify-between gap-3 text-[11px] uppercase tracking-[0.18em] text-muted">
        <span>{{ $visibleItems->count() }} ranges</span>
        <span>{{ $pendingCount }} pending</span>
    </div>

    <div x-data="{ hoverId: null }">
    <div class="overflow-hidden rounded-lg border border-white/10 bg-sidebar/80">
        <div class="grid grid-cols-7 border-b border-white/10 text-center text-[10px] font-semibold uppercase tracking-[0.2em] text-muted">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                <div class="px-1 py-2">{{ $weekday }}</div>
            @endforeach
        </div>

        <div class="divide-y divide-white/5">
            @foreach ($weeks as $weekIndex => $week)
                <div wire:key="week-{{ $week['days'][0]->toDateString() }}" class="px-1 py-1.5 sm:px-2">
                    <div class="grid grid-cols-7">
                        @foreach ($week['days'] as $day)
                            <button type="button"
                                    wire:key="day-{{ $day->toDateString() }}"
                                    wire:click="selectDay('{{ $day->toDateString() }}')"
                                    @class([
                                        'relative mx-auto flex h-8 w-8 items-center justify-center rounded-full text-xs tabular-nums transition',
                                        'text-faint' => $day->format('Y-m') !== $this->month,
                                        'text-body' => $day->format('Y-m') === $this->month && $day->toDateString() !== $today,
                                        'bg-check text-white font-semibold' => $day->toDateString() === $today,
                                        'ring-1 ring-mark' => $startedOn === $day->toDateString() && $day->toDateString() !== $today,
                                    ])>
                                {{ $day->day }}
                            </button>
                        @endforeach
                    </div>

                    @if (count($week['lanes']) > 0)
                        <div class="mt-1 space-y-0.5">
                            @foreach ($week['lanes'] as $laneIndex => $lane)
                                <div wire:key="lane-{{ $week['days'][0]->toDateString() }}-{{ $laneIndex }}"
                                     class="grid grid-cols-7 gap-x-0.5">
                                    @foreach ($lane as $segment)
                                        <div wire:key="seg-{{ $segment['id'] }}-{{ $week['days'][0]->toDateString() }}"
                                             title="{{ $segment['title'] }}"
                                             @mouseenter="hoverId = {{ $segment['id'] }}"
                                             @mouseleave="hoverId = null"
                                             :class="{ 'roadmap-bar-dimmed': hoverId !== null && hoverId !== {{ $segment['id'] }} }"
                                             @class([
                                                 'roadmap-bar',
                                                 'roadmap-color-'.$segment['colorIndex'] => $segment['pending'],
                                                 'roadmap-bar-pending' => $segment['pending'],
                                                 'roadmap-bar-done' => ! $segment['pending'],
                                                 'rounded-l-sm' => ! $segment['continuesLeft'],
                                                 'rounded-r-sm' => ! $segment['continuesRight'],
                                                 'rounded-l-none' => $segment['continuesLeft'],
                                                 'rounded-r-none' => $segment['continuesRight'],
                                                 'justify-end' => $segment['continuesLeft'] && ! $segment['continuesRight'],
                                             ])
                                             style="grid-column: {{ $segment['col'] }} / span {{ $segment['span'] }}">
                                            @if (! $segment['continuesLeft'])
                                                <svg class="roadmap-bar-start-flag shrink-0" viewBox="0 0 10 12" aria-hidden="true">
                                                    <path fill="currentColor" d="M1 0.75h1.25v10.5H1zM2.25 1.1h6.1L6.7 3.65 8.35 6.2H2.25z"/>
                                                </svg>
                                                <span class="shrink-0 whitespace-pre" aria-hidden="true"> --- </span>
                                            @endif
                                            @if (! $segment['continuesLeft'] || ! $segment['continuesRight'])
                                                <span class="truncate">{{ $segment['title'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="h-2" aria-hidden="true"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <ul class="mt-6 space-y-1" wire:key="range-list">
        @forelse ($visibleItems as $item)
            <li wire:key="range-{{ $item->id }}"
                @mouseenter="hoverId = {{ $item->id }}"
                @mouseleave="hoverId = null"
                :class="{ 'roadmap-bar-dimmed': hoverId !== null && hoverId !== {{ $item->id }} }"
                class="group flex items-center gap-2 rounded px-1 py-1.5 transition-opacity hover:bg-white/5">
                <span @class([
                    'mr-[3ch] h-2 w-2 shrink-0 rounded-full',
                    'roadmap-color-'.$this->colorIndexFor($item->id) => $item->isPending(),
                    'roadmap-swatch' => $item->isPending(),
                    'roadmap-swatch-done' => ! $item->isPending(),
                ])></span>

                <span @class([
                    'min-w-0 flex-1 truncate text-sm',
                    'text-muted' => ! $item->isPending(),
                    'text-body' => $item->isPending(),
                ])>
                    {{ $item->title }}
                </span>

                <span class="flex shrink-0 items-center gap-1 text-xs tabular-nums text-muted">
                    <label class="roadmap-date-hit" title="Edit start date">
                        <span class="pointer-events-none">{{ $item->started_on->format('j M') }}</span>
                        <input type="date"
                               value="{{ $item->started_on->toDateString() }}"
                               wire:change="updateStartedOn({{ $item->id }}, $event.target.value)"
                               class="roadmap-date-input"
                               aria-label="Start date for {{ $item->title }}"
                               onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
                    </label>
                    <span aria-hidden="true">→</span>
                    @if ($item->isPending())
                        <label class="roadmap-date-hit text-mark" title="Set completion date">
                            <span class="pointer-events-none">pending</span>
                            <input type="date"
                                   value=""
                                   wire:change="updateCompletedOn({{ $item->id }}, $event.target.value)"
                                   class="roadmap-date-input"
                                   aria-label="Set completion date for {{ $item->title }}"
                                   onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
                        </label>
                    @else
                        <label class="roadmap-date-hit" title="Edit completion date">
                            <span class="pointer-events-none">{{ $item->completed_on->format('j M') }}</span>
                            <input type="date"
                                   value="{{ $item->completed_on->toDateString() }}"
                                   wire:change="updateCompletedOn({{ $item->id }}, $event.target.value)"
                                   class="roadmap-date-input"
                                   aria-label="Completion date for {{ $item->title }}"
                                   onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
                        </label>
                    @endif
                </span>

                <button type="button"
                        wire:click="toggleComplete({{ $item->id }})"
                        class="mt-0 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition
                            {{ $item->isPending() ? 'border-muted/60 bg-transparent' : 'todo-check-done' }}"
                        aria-label="{{ $item->isPending() ? 'Mark complete' : 'Mark incomplete' }}">
                    @unless ($item->isPending())
                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    @endunless
                </button>

                <button type="button"
                        wire:click="deleteItem({{ $item->id }})"
                        wire:confirm="Delete this range?"
                        class="shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-red-400 focus:opacity-100"
                        aria-label="Delete">×</button>
            </li>
        @empty
            <li class="px-1 py-2 text-sm text-muted/70">No ranges in this month</li>
        @endforelse
    </ul>
    </div>

    <form wire:submit="addItem" class="mt-6 flex max-w-xl flex-col gap-2 sm:flex-row">
        <input type="text"
               wire:model="newTitle"
               placeholder="Start a range…"
               class="flex-1 rounded border border-white/10 bg-sidebar px-3 py-2 text-sm focus:border-link focus:outline-none" />
        <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-2 text-sm font-medium hover:border-link/40">
            Add from {{ \Carbon\Carbon::parse($startedOn)->format('j M') }}
        </button>
    </form>
</div>
