<div class="mx-auto max-w-5xl px-3 py-4 sm:px-4 sm:py-6">
    <div class="mb-5 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="previousWeek"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                    aria-label="Previous week">‹</button>

            <h1 class="inline-block max-w-[min(100%,12rem)] truncate bg-mark px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-black sm:max-w-none sm:px-3 sm:text-sm md:text-base">
                <span class="sm:hidden">DRIP · {{ $weekRange }}</span>
                <span class="hidden sm:inline">DRIP Matrix - Week: {{ $weekRange }}</span>
            </h1>

            <button type="button" wire:click="nextWeek"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                    aria-label="Next week">›</button>

            <a href="{{ route('milestones', ['week' => $week]) }}"
               class="ml-auto rounded border border-white/10 px-2.5 py-1.5 text-sm text-muted no-underline hover:text-body sm:px-3">
                ‹ <span class="hidden sm:inline">Milestones</span>
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="goToToday"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-link no-underline hover:bg-panel">
                Today
            </button>

            <select wire:model.live="status"
                    aria-label="Task status filter"
                    class="min-w-0 flex-1 rounded border border-white/10 bg-panel px-2 py-1.5 text-sm text-body focus:outline-none focus:ring-1 focus:ring-link sm:flex-none">
                <option value="all">all</option>
                <option value="pending">pending</option>
                <option value="completed">completed</option>
            </select>

            <label class="flex cursor-pointer items-center gap-1.5 text-sm text-muted hover:text-body">
                <input type="checkbox"
                       wire:model.live="todayOnly"
                       class="h-3.5 w-3.5 shrink-0 rounded border-white/20 bg-panel text-check focus:ring-link focus:ring-offset-0">
                <span>today tasks</span>
            </label>
        </div>
    </div>

    @include('livewire.partials.drip-matrix-grid', [
        'quadrants' => $quadrants,
        'untagged' => $untagged,
        'grid' => $grid,
        'showTaskDate' => ! $todayOnly,
    ])
</div>
