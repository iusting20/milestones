<div class="mx-auto max-w-5xl px-3 py-4 sm:px-4 sm:py-6">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold uppercase tracking-widest text-body sm:text-3xl">DRIP Matrix</h1>

        @include('livewire.partials.week-cycler', ['weekKey' => $week, 'todayAction' => 'goToToday'])

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <select wire:model.live="period"
                    aria-label="Time period filter"
                    class="min-w-0 rounded border border-white/10 bg-panel px-2 py-1.5 text-sm text-body focus:outline-none focus:ring-1 focus:ring-link">
                <option value="today">today</option>
                <option value="week">this week</option>
                <option value="month">this month</option>
            </select>

            <a href="{{ route('milestones', ['week' => $week]) }}"
               class="rounded border border-white/10 px-2.5 py-1.5 text-sm text-muted no-underline hover:text-body sm:px-3">
                ‹ <span class="hidden sm:inline">Milestones</span>
            </a>
        </div>
    </div>

    @include('livewire.partials.drip-matrix-grid', [
        'quadrants' => $quadrants,
        'untagged' => $untagged,
        'grid' => $grid,
        'showTaskDate' => $period !== 'today',
    ])
</div>
