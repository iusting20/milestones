<div class="mx-auto max-w-6xl px-3 py-6 sm:px-4">
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
</div>
