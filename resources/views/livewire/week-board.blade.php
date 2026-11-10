<div class="mx-auto max-w-3xl px-3 py-6 sm:px-4">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <button type="button" wire:click="previousWeek"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Previous week">‹</button>

        <h1 class="inline-block bg-mark px-3 py-1 text-sm font-bold uppercase tracking-wide text-black sm:text-base">
            Productivity Log - Week: {{ $weekRange }}
        </h1>

        <button type="button" wire:click="nextWeek"
                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                aria-label="Next week">›</button>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" wire:click="goToToday"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-link no-underline hover:bg-panel">
                Today
            </button>

            <button type="button"
                    wire:click="$toggle('showTags')"
                    @class([
                        'flex h-8 w-8 items-center justify-center rounded text-muted transition hover:bg-white/5 hover:text-body',
                        'bg-white/10 text-link' => $showTags,
                    ])
                    aria-label="{{ $showTags ? 'Hide task tags' : 'Show task tags' }}"
                    aria-pressed="{{ $showTags ? 'true' : 'false' }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                </svg>
            </button>

            <a href="{{ route('milestones.drip-matrix', ['week' => $week]) }}"
               class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
               wire:navigate>
                DRIP Matrix
            </a>

            <button type="button"
                    wire:click="$toggle('showLogControls')"
                    @class([
                        'flex h-8 w-8 items-center justify-center rounded text-muted transition hover:bg-white/5 hover:text-body',
                        'bg-white/10 text-link' => $showLogControls,
                    ])
                    aria-label="{{ $showLogControls ? 'Hide log controls' : 'Show log controls' }}"
                    aria-pressed="{{ $showLogControls ? 'true' : 'false' }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </button>

            <a href="{{ route('milestones.log', ['week' => $week]) }}"
               class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
               wire:navigate>
                Log
            </a>
        </div>
    </div>

    <div class="space-y-8">
        @foreach ($days as $day)
            <livewire:day-list
                :date="$day->toDateString()"
                :day-label="strtoupper($day->format('l'))"
                :is-today="$day->toDateString() === $today"
                :show-tags="$showTags"
                :show-log-controls="$showLogControls"
                :key="'day-'.$day->toDateString()"
            />
        @endforeach

        <livewire:week-list
            :week="$week"
            :show-tags="$showTags"
            :show-log-controls="$showLogControls"
            :key="'week-list-'.$week"
        />

        <div class="flex justify-center">
            <a href="{{ route('backlog') }}"
               class="rounded border border-white/10 px-4 py-1.5 text-sm text-link no-underline hover:bg-panel">
                Backlog
            </a>
        </div>
    </div>
</div>
