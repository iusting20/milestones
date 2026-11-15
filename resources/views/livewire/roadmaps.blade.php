<div class="mx-auto max-w-5xl px-3 py-6 sm:px-4">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold uppercase tracking-widest text-body sm:text-3xl">Timelines</h1>

        <div class="ml-2 flex items-center gap-0.5 text-xs text-muted" data-month-toggle>
            <button type="button" wire:click="previousMonth"
                    class="rounded px-1.5 py-0.5 leading-none hover:bg-white/5 hover:text-body"
                    aria-label="Previous month">‹</button>
            <span class="px-1 tabular-nums" data-month-label title="{{ $this->month }}">{{ $monthLabel }}</span>
            <button type="button" wire:click="nextMonth"
                    class="rounded px-1.5 py-0.5 leading-none hover:bg-white/5 hover:text-body"
                    aria-label="Next month">›</button>
            @unless ($isCurrentMonth)
                <button type="button" wire:click="goToThisMonth"
                        class="ml-1 rounded px-1.5 py-0.5 hover:bg-white/5 hover:text-body" data-month-today>This month</button>
            @endunless
        </div>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-center text-[11px] uppercase tracking-[0.18em] text-muted">
        <span class="font-bold">{{ $visibleItems->count() }} projects</span>
        <span>{{ $activeCount }} active</span>
        <span>{{ $completedCount }} completed</span>
        <span>{{ $notStartedCount }} not yet started</span>
    </div>

    <div x-data="{ hoverId: null }" class="flex items-start gap-3">
    <div class="min-w-0 flex-1">
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
                                                 'roadmap-track',
                                                 'roadmap-color-'.$segment['colorIndex'] => $segment['pending'],
                                                 'roadmap-track-done' => ! $segment['pending'],
                                             ])
                                             style="grid-column: {{ $segment['col'] }} / span {{ $segment['span'] }}; --span: {{ $segment['span'] }}">
                                            @foreach ($segment['markers'] as $marker)
                                                <span class="roadmap-endpoint roadmap-endpoint-{{ $marker['type'] }}"
                                                      style="--at: {{ $marker['at'] }}"
                                                      data-marker="{{ $marker['type'] }}"
                                                      data-date="{{ $marker['date'] }}"
                                                      aria-hidden="true">
                                                    @if ($marker['type'] === 'done')
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M3.2 8.2 6.4 11.4 12.8 4.6"/>
                                                        </svg>
                                                    @else
                                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                                            <path d="M8 2.2v4.2"/>
                                                            <path d="M4.15 4.7a5 5 0 1 0 7.7 0"/>
                                                        </svg>
                                                    @endif
                                                </span>
                                            @endforeach
                                            <div @class([
                                                'roadmap-bar',
                                                'roadmap-color-'.$segment['colorIndex'] => $segment['pending'],
                                                'roadmap-bar-pending' => $segment['pending'],
                                                'roadmap-bar-done' => ! $segment['pending'],
                                                'roadmap-bar-from-dot' => $segment['fromDot'],
                                                'roadmap-bar-to-dot' => $segment['toDot'],
                                                'rounded-l-sm' => ! $segment['continuesLeft'],
                                                'rounded-r-sm' => ! $segment['continuesRight'],
                                                'rounded-l-none' => $segment['continuesLeft'],
                                                'rounded-r-none' => $segment['continuesRight'],
                                                'justify-end' => $segment['continuesLeft'] && ! $segment['continuesRight'],
                                            ])>
                                            @if (! $segment['continuesLeft'] || ! $segment['continuesRight'])
                                                <span class="truncate">{{ $segment['title'] }}</span>
                                            @endif
                                            </div>
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
                    <button type="button"
                            wire:click="openEdit({{ $item->id }})"
                            class="truncate text-left hover:underline"
                            title="Edit {{ $item->title }}">
                        {{ $item->title }}
                    </button>
                    @if ($item->hasLink())
                        <a href="{{ $item->link_url }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="ml-2 text-link hover:underline"
                           title="{{ $item->link_url }}">{{ $item->linkLabel() }}</a>
                    @endif
                    @if ($item->project)
                        <span class="ml-2 text-xs text-muted"
                              title="{{ $item->project->hub === 'assets' ? 'Asset' : 'Client' }}">{{ $item->project->name }}</span>
                    @endif
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
                    <label @class(['roadmap-date-hit', 'text-muted/60' => $item->finish_on === null]) title="{{ $item->finish_on ? 'Edit scheduled finish' : 'Set scheduled finish' }}" data-finish-for="{{ $item->id }}">
                        <span class="pointer-events-none">{{ $item->finish_on ? $item->finish_on->format('j M') : 'finish?' }}</span>
                        <input type="date"
                               value="{{ $item->finish_on?->toDateString() }}"
                               min="{{ $item->started_on->toDateString() }}"
                               wire:change="updateFinishOn({{ $item->id }}, $event.target.value)"
                               class="roadmap-date-input"
                               aria-label="Scheduled finish for {{ $item->title }}"
                               onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
                    </label>
                    <span aria-hidden="true" class="px-0.5">·</span>
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

    <nav class="sticky top-16 hidden w-16 shrink-0 flex-col gap-1 self-start md:flex"
         aria-label="Months in {{ $yearMinimap[0]['key'] ?? '' }}">
        @foreach ($yearMinimap as $mini)
            <button type="button"
                    wire:click="showMonth('{{ $mini['key'] }}')"
                    title="{{ $mini['label'] }}"
                    aria-label="{{ $mini['label'] }}"
                    aria-current="{{ $mini['active'] ? 'true' : 'false' }}"
                    @class([
                        'rounded px-1 py-1 text-left transition',
                        'bg-white/10 ring-1 ring-white/40' => $mini['active'],
                        'hover:bg-white/5' => ! $mini['active'],
                    ])>
                <span class="block text-[9px] font-semibold uppercase tracking-[0.14em] text-muted">{{ $mini['label'] }}</span>
                <span class="mt-0.5 block space-y-px" aria-hidden="true">
                    @foreach ($mini['weeks'] as $miniWeek)
                        <span class="grid grid-cols-7 gap-px">
                            @foreach ($miniWeek as $miniDay)
                                <span @class([
                                    'h-1 w-full rounded-[1px]',
                                    'bg-white/25' => $miniDay['inMonth'] && $miniDay['colors'] === [] && ! $mini['active'],
                                    'bg-white/45' => $miniDay['inMonth'] && $miniDay['colors'] === [] && $mini['active'],
                                    'bg-transparent' => ! $miniDay['inMonth'],
                                ]) @if ($miniDay['colors'] !== []) style="{{ count($miniDay['colors']) === 1 ? 'background-color: '.$miniDay['colors'][0] : 'background-image: linear-gradient(to bottom, '.implode(', ', $miniDay['colors']).')' }}" @endif></span>
                            @endforeach
                        </span>
                    @endforeach
                </span>
            </button>
        @endforeach
    </nav>
    </div>

    <button type="button"
            wire:click="openCreate"
            class="mt-6 rounded border border-white/15 px-4 py-2 text-sm font-medium text-muted hover:bg-panel hover:text-body">
        Add timeline
    </button>

    @if ($creating)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             wire:keydown.escape.window="closeCreate"
             role="dialog"
             aria-modal="true"
             aria-labelledby="start-project-title">
            <div class="absolute inset-0 bg-black/60" wire:click="closeCreate" aria-hidden="true"></div>

            <form wire:submit="addItem" class="relative z-10 w-full max-w-md rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="start-project-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        {{ $editingId ? 'Edit timeline' : 'Add a timeline' }}
                    </h2>
                    <button type="button"
                            wire:click="closeCreate"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body"
                            aria-label="Close">×</button>
                </div>

                <label class="mb-3 block text-xs uppercase tracking-[0.14em] text-muted">
                    Name
                    <input type="text"
                           wire:model="newTitle"
                           autofocus
                           class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Start date
                        <input type="date"
                               wire:model="startedOn"
                               class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none" />
                    </label>

                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Finish date <span class="normal-case tracking-normal text-muted/60">(optional)</span>
                        <input type="date"
                               wire:model="finishOn"
                               class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none" />
                    </label>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3">
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Link title <span class="normal-case tracking-normal text-muted/60">(optional)</span>
                        <input type="text"
                               wire:model="newLinkTitle"
                               placeholder="Title"
                               class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                    </label>

                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Link URL <span class="normal-case tracking-normal text-muted/60">(optional)</span>
                        <input type="url"
                               wire:model="newLinkUrl"
                               placeholder="https://"
                               class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('newLinkUrl')
                            <span class="mt-1 block text-xs normal-case tracking-normal text-red-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <label class="mt-3 block text-xs uppercase tracking-[0.14em] text-muted">
                    Associated with <span class="normal-case tracking-normal text-muted/60">(optional)</span>
                    <select wire:model="newProjectId"
                            class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                        <option value="">None</option>
                        @if ($clients->isNotEmpty())
                            <optgroup label="Clients">
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($assets->isNotEmpty())
                            <optgroup label="Assets">
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </label>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                            wire:click="closeCreate"
                            class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">
                        Cancel
                    </button>
                    <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium hover:border-link/40">
                        {{ $editingId ? 'Save' : 'Add' }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
