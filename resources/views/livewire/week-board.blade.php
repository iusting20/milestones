<div class="mx-auto max-w-3xl px-3 py-6 sm:px-4">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <button type="button" wire:click="previousWeek"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Previous week">‹</button>

        <h1 class="inline-block bg-mark px-3 py-1 text-sm font-bold uppercase tracking-wide text-black sm:text-base">
            Productivity Log - Week {{ $weekNumber }}
        </h1>

        <button type="button" wire:click="nextWeek"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Next week">›</button>

        <button type="button" wire:click="goToToday"
                class="ml-auto rounded border border-white/10 px-3 py-1.5 text-sm text-link no-underline hover:bg-panel">
            Today
        </button>
    </div>

    <div class="space-y-8">
        @foreach ($days as $day)
            <livewire:day-list
                :date="$day->toDateString()"
                :day-label="strtoupper($day->format('l'))"
                :is-today="$day->toDateString() === $today"
                :key="'day-'.$day->toDateString()"
            />
        @endforeach
    </div>
</div>
