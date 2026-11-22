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
                                                    @elseif ($marker['type'] === 'star')
                                                        <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                            <path d="M8 1.15 9.79 5.72l4.86.4-3.72 3.16 1.14 4.78L8 11.55 3.93 14.06l1.14-4.78L1.35 6.12l4.86-.4z"/>
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

    <div class="mt-6 space-y-5" wire:key="range-lists">
        <section>
            <h2 class="mb-1 px-1 text-[11px] font-bold uppercase tracking-[0.18em] text-muted">Deliverables</h2>
            <ul class="space-y-1" wire:key="deliverable-list">
                @forelse ($deliverableItems as $item)
                    @include('livewire.partials.roadmap-list-item')
                @empty
                    <li class="px-1 py-2 text-sm text-muted/70">No deliverables in this month</li>
                @endforelse
            </ul>
        </section>

        <section>
            <h2 class="mb-1 px-1 text-[11px] font-bold uppercase tracking-[0.18em] text-muted">Timelines</h2>
            <ul class="space-y-1" wire:key="timeline-list">
                @forelse ($timelineItems as $item)
                    @include('livewire.partials.roadmap-list-item')
                @empty
                    <li class="px-1 py-2 text-sm text-muted/70">No timelines in this month</li>
                @endforelse
            </ul>
        </section>
    </div>
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

    <div class="mt-6 flex flex-wrap gap-2">
        <button type="button"
                wire:click="openCreate('timeline')"
                class="rounded border border-white/15 px-4 py-2 text-sm font-medium text-muted hover:bg-panel hover:text-body">
            Add timeline
        </button>
        <button type="button"
                wire:click="openCreate('deliverable')"
                class="rounded border border-white/15 px-4 py-2 text-sm font-medium text-muted hover:bg-panel hover:text-body">
            Add deliverable
        </button>
    </div>

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
                        @if ($editingId)
                            {{ $kind === 'deliverable' ? 'Edit deliverable' : 'Edit timeline' }}
                        @else
                            {{ $kind === 'deliverable' ? 'Add a deliverable' : 'Add a timeline' }}
                        @endif
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

                @if ($kind === 'deliverable')
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Date
                        <input type="date"
                               wire:model="startedOn"
                               class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none" />
                    </label>
                @else
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
                @endif

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
