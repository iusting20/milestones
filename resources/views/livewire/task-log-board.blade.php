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

    @if ($stats['itemCount'] > 0)
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

            <ul class="divide-y divide-white/5">
                @foreach ($stats['items'] as $item)
                    <li wire:key="stat-{{ md5($item['title']) }}"
                        data-log-stat="{{ $item['title'] }}"
                        class="scroll-mt-24 rounded-md px-4 py-3"
                        :class="{ 'log-item-glow': glowTitle === @js($item['title']) }">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                <span class="truncate text-[15px] text-body">{{ $item['title'] }}</span>
                            </div>
                            <div class="flex shrink-0 items-baseline gap-3 tabular-nums">
                                <span class="text-sm font-semibold text-body">{{ $item['duration'] }}</span>
                                <span class="text-xs text-muted">{{ $item['share'] }}%</span>
                            </div>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/5">
                            <div class="h-full cursor-pointer rounded-full transition-opacity duration-150"
                                 style="width: {{ max(4, $item['width']) }}%; background-color: {{ $item['color'] }}"
                                 @mouseenter="hoverTitle = @js($item['title'])"
                                 @mouseleave="hoverTitle = null"
                                 @click="focusTitle(@js($item['title']))"
                                 :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($item['title']) }"></div>
                        </div>
                        <ul class="mt-2 space-y-1">
                            @foreach ($item['sessions'] as $session)
                                <li wire:key="stat-session-{{ $session['id'] }}"
                                    class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-xs text-muted">
                                    <span class="w-20 shrink-0 tabular-nums text-body/80">{{ $session['day'] }} {{ $session['date'] }}</span>
                                    <span class="tabular-nums">{{ $session['started'] }} → {{ $session['finished'] }}</span>
                                    <span class="ml-auto tabular-nums text-body/90">{{ $session['duration'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
