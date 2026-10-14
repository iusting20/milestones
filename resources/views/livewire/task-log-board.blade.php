<div class="mx-auto max-w-6xl px-3 py-6 sm:px-4"
     x-data="{
        hoverTitle: null,
        glowTitle: null,
        glowTimer: null,
        focusTitle(title) {
            if (this.glowTimer) {
                clearTimeout(this.glowTimer);
            }

            const target = Array.from(this.$root.querySelectorAll('[data-log-stat]'))
                .find((node) => node.getAttribute('data-log-stat') === title);

            target?.scrollIntoView({ behavior: 'smooth', block: 'center' });

            this.glowTitle = null;
            this.$nextTick(() => {
                this.glowTitle = title;
                this.glowTimer = setTimeout(() => {
                    this.glowTitle = null;
                    this.glowTimer = null;
                }, 1500);
            });
        },
     }">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <button type="button" wire:click="previousWeek"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Previous week">‹</button>

        <h1 class="inline-block bg-mark px-3 py-1 text-sm font-bold uppercase tracking-wide text-black sm:text-base">
            Log - Week {{ $weekNumber }}
        </h1>

        <button type="button" wire:click="nextWeek"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Next week">›</button>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="openAddModal"
                    class="flex h-8 w-8 items-center justify-center rounded border border-white/10 text-lg leading-none text-muted transition hover:bg-panel hover:text-link"
                    aria-label="Add log entry">
                +
            </button>

            <button type="button" wire:click="goToToday"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-link no-underline hover:bg-panel">
                Today
            </button>

            <a href="{{ route('milestones', ['week' => $week]) }}"
               class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
               wire:navigate>
                Milestones
            </a>
        </div>
    </div>

    @if ($showAddModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             wire:keydown.escape.window="closeAddModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="add-log-title">
            <div class="absolute inset-0 bg-black/60" wire:click="closeAddModal" aria-hidden="true"></div>

            <form wire:submit="addLog"
                  class="relative z-10 w-full max-w-md rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="add-log-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        Add log entry
                    </h2>
                    <button type="button"
                            wire:click="closeAddModal"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body"
                            aria-label="Close">×</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="mb-1 block text-xs uppercase tracking-[0.14em] text-muted">Day</span>
                        <div class="flex flex-wrap gap-1" role="group" aria-label="Day of week">
                            @forelse ($selectableDays as $day)
                                <button type="button"
                                        wire:click="selectLogDate('{{ $day['date'] }}')"
                                        @class([
                                            'rounded border px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide transition',
                                            'border-link bg-link/15 text-link' => $logDate === $day['date'],
                                            'border-white/10 text-muted hover:text-body' => $logDate !== $day['date'],
                                        ])>
                                    {{ $day['label'] }}
                                </button>
                            @empty
                                <p class="text-xs text-muted">No elapsed days in this week.</p>
                            @endforelse
                        </div>
                        @error('logDate')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="add-log-task" class="mb-1 block text-xs uppercase tracking-[0.14em] text-muted">Task</label>
                        <select id="add-log-task"
                                wire:model="todoItemId"
                                class="w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none">
                            <option value="">Select a task…</option>
                            @foreach ($tasks as $task)
                                <option value="{{ $task->id }}">
                                    @if ($task->parent)
                                        {{ $task->parent->title }} ›
                                    @endif
                                    {{ $task->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('todoItemId')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="add-log-start" class="mb-1 block text-xs uppercase tracking-[0.14em] text-muted">Start</label>
                            <input id="add-log-start"
                                   type="time"
                                   wire:model="startedAt"
                                   class="w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none" />
                            @error('startedAt')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="add-log-finish" class="mb-1 block text-xs uppercase tracking-[0.14em] text-muted">Finish</label>
                            <input id="add-log-finish"
                                   type="time"
                                   wire:model="finishedAt"
                                   class="w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none" />
                            @error('finishedAt')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button"
                            wire:click="closeAddModal"
                            class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">
                        Cancel
                    </button>
                    <button type="submit"
                            class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium hover:border-link/40">
                        Add
                    </button>
                </div>
            </form>
        </div>
    @endif

    @unless ($hasLogs)
        <p class="text-sm text-muted">No finished log entries this week.</p>
    @endunless

    @php
        $weekdays = array_slice($days, 0, 5);
        $weekend = array_slice($days, 5, 2);
    @endphp

    <div class="overflow-x-auto">
        <div class="min-w-[48rem] space-y-2">
            <div class="grid grid-cols-5 items-stretch gap-2">
                @foreach ($weekdays as $day)
                    @include('livewire.partials.task-log-day', ['day' => $day])
                @endforeach
            </div>

            <div class="grid grid-cols-5 items-stretch gap-2">
                <div aria-hidden="true"></div>
                <div aria-hidden="true"></div>
                <div aria-hidden="true"></div>
                @foreach ($weekend as $day)
                    @include('livewire.partials.task-log-day', ['day' => $day])
                @endforeach
            </div>
        </div>
    </div>

    @if (count($stats['days']) > 0)
        <section class="mt-8 rounded-lg border border-white/10 bg-sidebar/60">
            <header class="flex flex-wrap items-end justify-between gap-3 border-b border-white/10 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">Week totals</h2>
                <dl class="flex flex-wrap gap-x-5 gap-y-1 text-sm tabular-nums">
                    <div>
                        <dt class="text-[10px] uppercase tracking-[0.16em] text-muted">Logged</dt>
                        <dd class="text-lg font-semibold text-body">{{ $stats['totalDuration'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-[0.16em] text-muted">Items</dt>
                        <dd class="text-lg font-semibold text-body">{{ $stats['itemCount'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-[0.16em] text-muted">Sessions</dt>
                        <dd class="text-lg font-semibold text-body">{{ $stats['sessionCount'] }}</dd>
                    </div>
                </dl>
            </header>

            <div class="divide-y divide-white/10">
                @foreach ($stats['days'] as $dayStats)
                    <section wire:key="stat-day-{{ $dayStats['date'] }}" class="px-4 py-3">
                        <header class="mb-3 flex items-baseline justify-center gap-3">
                            <h3 class="text-base font-semibold uppercase tracking-[0.14em] text-muted sm:text-lg">
                                {{ $dayStats['heading'] }}
                            </h3>
                        </header>

                        <ul class="space-y-3">
                            @foreach ($dayStats['items'] as $item)
                                @php
                                    $previousWasTimed = $loop->index > 0 && $dayStats['items'][$loop->index - 1]['timed'];
                                    $separateUntimed = ! $item['timed'] && ($loop->first || $previousWasTimed);
                                @endphp
                                <li wire:key="stat-{{ $dayStats['date'] }}-{{ md5($item['title']) }}"
                                    data-log-stat="{{ $item['title'] }}"
                                    @class([
                                        'scroll-mt-24 rounded-md',
                                        'mt-8 border-t border-white/10 pt-6' => $separateUntimed,
                                    ])
                                    :class="{ 'log-item-glow': glowTitle === @js($item['title']) }">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                            <span class="truncate text-[15px] text-body">{{ $item['title'] }}</span>
                                        </div>
                                        <span class="shrink-0 text-xs tabular-nums text-muted">{{ $item['share'] }}%</span>
                                    </div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/5">
                                        <div class="h-full cursor-pointer rounded-full transition-opacity duration-150"
                                             style="width: {{ max(4, $item['width']) }}%; @if ($item['timed']) background-color: {{ $item['color'] }}; @else background-color: transparent; background-image: repeating-linear-gradient(-45deg, {{ $item['color'] }} 0 3px, transparent 3px 7px); @endif"
                                             @mouseenter="hoverTitle = @js($item['title'])"
                                             @mouseleave="hoverTitle = null"
                                             @click="focusTitle(@js($item['title']))"
                                             :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($item['title']) }"
                                             @if (! $item['timed']) data-untimed-bar @endif></div>
                                    </div>
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($item['sessions'] as $session)
                                            <li wire:key="stat-session-{{ $session['id'] ?? ('untimed-'.md5($dayStats['date'].$item['title'])) }}"
                                                class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-xs text-muted">
                                                @include('livewire.partials.task-log-times', [
                                                    'logId' => $session['id'],
                                                    'startLogId' => $session['startLogId'] ?? $session['id'],
                                                    'finishLogId' => $session['finishLogId'] ?? $session['id'],
                                                    'todoItemId' => $session['todoItemId'] ?? null,
                                                    'started' => $session['started'],
                                                    'finished' => $session['finished'],
                                                    'timed' => $session['timed'],
                                                    'textClass' => 'text-xs text-muted',
                                                    'context' => 'stats',
                                                ])
                                                <span class="ml-auto tabular-nums text-body/90">{{ $session['duration'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </section>
    @endif
</div>
