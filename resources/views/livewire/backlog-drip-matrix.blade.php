<div class="mx-auto max-w-5xl px-3 py-4 sm:px-4 sm:py-6">
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('backlog') }}"
           class="rounded border border-white/10 px-2.5 py-1.5 text-sm text-muted no-underline hover:text-body sm:px-3">
            ‹ <span class="hidden sm:inline">Backlog</span>
        </a>

        <h1 class="inline-block bg-mark px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-black sm:px-3 sm:text-sm md:text-base">
            Backlog DRIP Matrix
        </h1>

        <select wire:model.live="status"
                aria-label="Task status filter"
                class="ml-auto min-w-0 rounded border border-white/10 bg-panel px-2 py-1.5 text-sm text-body focus:outline-none focus:ring-1 focus:ring-link">
            <option value="all">all</option>
            <option value="pending">pending</option>
            <option value="completed">completed</option>
        </select>
    </div>

    @include('livewire.partials.drip-matrix-grid', [
        'quadrants' => $quadrants,
        'untagged' => $untagged,
        'grid' => $grid,
        'showTaskDate' => false,
    ])
</div>
