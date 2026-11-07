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
            Log - Week: {{ $weekRange }}
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

                        @if ($creatingTask)
                            <div class="mt-2 flex items-center gap-2" data-new-task-form>
                                <input id="add-log-new-task"
                                       type="text"
                                       wire:model="newTaskTitle"
                                       wire:keydown.enter.prevent="createTask"
                                       wire:keydown.escape.stop="cancelCreatingTask"
                                       placeholder="New task name"
                                       aria-label="New task name"
                                       x-init="$el.focus()"
                                       class="min-w-0 flex-1 rounded border border-white/10 bg-panel px-3 py-2 text-sm text-body focus:border-link focus:outline-none" />
                                <button type="button" wire:click="createTask"
                                        class="rounded border border-white/15 bg-panel px-3 py-2 text-sm font-medium hover:border-link/40">Create</button>
                                <button type="button" wire:click="cancelCreatingTask"
                                        class="rounded px-1 text-lg leading-none text-muted hover:text-body" aria-label="Cancel new task">×</button>
                            </div>
                            @error('newTaskTitle')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        @else
                            <button type="button" wire:click="startCreatingTask" data-new-task-button
                                    class="mt-1.5 text-xs text-link hover:underline">+ New task</button>
                        @endif
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

    <section class="mt-8 grid grid-cols-1 gap-3 lg:grid-cols-2" data-week-activity>
        @foreach (['partners' => 'Activity by client', 'assets' => 'Activity by asset'] as $hubKey => $chartLabel)
            <section class="rounded-lg border border-white/10 bg-sidebar/60 px-4 py-3" data-activity-chart="{{ $hubKey }}">
                <header class="mb-3 flex items-baseline justify-between gap-3 border-b border-white/10 pb-2">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">{{ $chartLabel }}</h2>
                    <span class="text-xs tabular-nums text-muted">{{ $activity[$hubKey]['totalDuration'] }}</span>
                </header>
                @if ($activity[$hubKey]['rows'] === [])
                    <p class="text-xs text-muted" data-activity-empty>No logged time this week.</p>
                @else
                    <ul class="space-y-2.5">
                        @foreach ($activity[$hubKey]['rows'] as $row)
                            <li wire:key="activity-{{ $hubKey }}-{{ md5($row['name']) }}" data-activity-row="{{ $row['name'] }}"
                                role="button" tabindex="0"
                                title="{{ $row['others'] ? 'Show and assign items not linked to a '.($hubKey === 'partners' ? 'client' : 'asset') : 'Show items for '.$row['name'] }}"
                                wire:click="openActivity('{{ $hubKey }}', @js($row['name']))"
                                wire:keydown.enter="openActivity('{{ $hubKey }}', @js($row['name']))"
                                class="-mx-2 cursor-pointer rounded-md px-2 py-1 transition hover:bg-white/5 focus:bg-white/5 focus:outline-none">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                                        <span @class(['truncate', 'text-muted italic' => $row['others'], 'text-body' => ! $row['others']])>{{ $row['name'] }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs tabular-nums text-muted">{{ $row['duration'] }} · {{ $row['share'] }}%</span>
                                </div>
                                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-white/5">
                                    <div class="h-full rounded-full" style="width: {{ max(2, $row['width']) }}%; background: {{ $row['color'] }}"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </section>

    @if ($activityModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             wire:keydown.escape.window="closeActivity"
             role="dialog" aria-modal="true" aria-labelledby="activity-modal-title" data-activity-modal="{{ $activityModal['hub'] }}">
            <div class="absolute inset-0 bg-black/60" wire:click="closeActivity" aria-hidden="true"></div>

            <div class="relative z-10 max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="activity-modal-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        {{ $activityModal['others'] ? 'Others — not linked to a '.$activityModal['label'] : $activityModal['row'] }}
                        <span class="ml-2 normal-case tracking-normal tabular-nums">{{ $activityModal['duration'] }}</span>
                    </h2>
                    <button type="button" wire:click="closeActivity"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body" aria-label="Close">×</button>
                </div>

                @if ($activityModal['others'])
                    <div class="mb-3 flex justify-center">
                        <div class="inline-flex rounded border border-white/15 p-0.5 text-xs font-semibold uppercase tracking-wide" role="group" aria-label="Assign to" data-activity-side-toggle>
                            @foreach (['partners' => 'Client', 'assets' => 'Asset'] as $sideKey => $sideLabel)
                                <button type="button" wire:click="switchActivityHub('{{ $sideKey }}')"
                                        aria-pressed="{{ $activityModal['hub'] === $sideKey ? 'true' : 'false' }}"
                                        data-activity-side="{{ $sideKey }}"
                                        @class([
                                            'rounded px-3 py-1 transition',
                                            'bg-link/15 text-link' => $activityModal['hub'] === $sideKey,
                                            'text-muted hover:text-body' => $activityModal['hub'] !== $sideKey,
                                        ])>{{ $sideLabel }}</button>
                            @endforeach
                        </div>
                    </div>
                    @if ($activityModal['items'] !== [])
                        <p class="mb-3 text-xs text-muted">Pick a {{ $activityModal['label'] }} for an item and press Apply. The item keeps that {{ $activityModal['label'] }} on every day and in later weeks too.</p>
                    @endif
                @endif

                @if ($activityModal['days'] === [])
                    <p class="text-sm text-muted" data-activity-modal-empty>
                        {{ $activityModal['others'] ? 'Everything this week is linked to a '.$activityModal['label'].'.' : 'No items this week.' }}
                    </p>
                @else
                    <div class="space-y-4">
                        @foreach ($activityModal['days'] as $activityDay)
                            <section wire:key="activity-day-{{ $activityModal['hub'] }}-{{ $activityDay['date'] }}" data-activity-day="{{ $activityDay['date'] }}">
                                <h3 class="border-b border-white/10 pb-1 text-center text-xs font-semibold uppercase tracking-[0.14em] text-muted" data-activity-day-heading>
                                    {{ $activityDay['heading'] }} <span class="normal-case tracking-normal tabular-nums">({{ $activityDay['duration'] }})</span>
                                </h3>
                                <ul class="divide-y divide-white/10">
                                    @foreach ($activityDay['items'] as $item)
                                        <li wire:key="activity-item-{{ $activityModal['hub'] }}-{{ $activityDay['date'] }}-{{ md5($item['title']) }}" data-activity-item="{{ $item['title'] }}"
                                            class="py-2">
                                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                                <span class="min-w-0 truncate text-body">{{ $item['title'] }}</span>
                                                <span class="shrink-0 text-xs tabular-nums text-muted">{{ $item['duration'] }} · {{ $item['share'] }}%</span>
                                            </div>
                                            @if ($activityModal['others'])
                                                <div class="mt-1.5 flex items-center gap-2" x-data="{ project: '' }">
                                                    <select x-model="project" aria-label="{{ ucfirst($activityModal['label']) }} for {{ $item['title'] }}"
                                                            class="min-w-0 flex-1 rounded border border-white/15 bg-panel px-2 py-1 text-sm text-body">
                                                        <option value="">Choose {{ $activityModal['label'] }}…</option>
                                                        @foreach ($activityModal['projects'] as $projectId => $projectName)
                                                            <option value="{{ $projectId }}">{{ $projectName }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="button" :disabled="project === ''"
                                                            @click="$wire.assignActivityItem(@js($item['title']), project)"
                                                            class="rounded border border-white/15 bg-panel px-3 py-1 text-sm font-medium hover:border-link/40 disabled:cursor-not-allowed disabled:opacity-40">
                                                        Apply
                                                    </button>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if (count($stats['days']) > 0)
        <section class="mt-8 space-y-3" data-week-totals>
            <header class="flex flex-wrap items-end justify-between gap-3 rounded-lg border border-white/10 bg-sidebar/60 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">Week totals</h2>
                <dl class="flex flex-wrap gap-x-5 gap-y-1 text-sm tabular-nums">
                    <div>
                        <dt class="text-[10px] uppercase tracking-[0.16em] text-muted">Logged</dt>
                        <dd class="text-lg font-semibold text-body">{{ $stats['totalDuration'] }}</dd>
                    </div>
                    <div data-week-idle>
                        <dt class="text-[10px] uppercase tracking-[0.16em] text-muted">Idle hours</dt>
                        <dd class="text-lg font-semibold text-body">{{ $weekIdleDuration }}</dd>
                    </div>
                    @foreach (['partners' => 'Partners covered', 'assets' => 'Assets covered'] as $hubKey => $hubLabel)
                        <div data-coverage="{{ $hubKey }}"
                             title="{{ $coverage[$hubKey]['uncovered'] === [] ? 'All covered' : 'Not covered: '.implode(', ', $coverage[$hubKey]['uncovered']) }}">
                            <dt class="text-[10px] uppercase tracking-[0.16em] text-muted">{{ $hubLabel }}</dt>
                            <dd class="text-lg font-semibold text-body">{{ $coverage[$hubKey]['covered'] }} out of {{ $coverage[$hubKey]['total'] }}</dd>
                        </div>
                    @endforeach
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

            <div class="grid grid-cols-1 items-start gap-3 lg:grid-cols-2">
                @foreach ($stats['days'] as $dayStats)
                    <section wire:key="stat-day-{{ $dayStats['date'] }}" data-week-total-day="{{ $dayStats['date'] }}"
                             @class([
                                 'rounded-lg border px-4 py-3',
                                 'border-link/40 bg-today' => $dayStats['date'] === \App\Support\Clock::todayString(),
                                 'border-white/10 bg-sidebar/60' => $dayStats['date'] !== \App\Support\Clock::todayString(),
                             ])>
                        <header class="mb-3 flex items-baseline justify-center gap-3 border-b border-white/10 pb-2">
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
