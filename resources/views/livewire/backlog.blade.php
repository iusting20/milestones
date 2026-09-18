<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
    <h1 class="mb-6 text-2xl font-bold uppercase tracking-widest text-body sm:text-3xl">Backlog</h1>

    <ul
        class="space-y-1"
        data-sortable
        data-component-id="{{ $this->getId() }}"
        wire:key="backlog-list"
    >
        @foreach ($items as $item)
            @if ($item->is_separator)
                <li
                    data-item-id="{{ $item->id }}"
                    wire:key="item-{{ $item->id }}"
                    class="group flex items-center gap-2 py-3"
                >
                    <button type="button"
                            data-drag-handle
                            class="shrink-0 cursor-grab touch-none px-0.5 text-muted/50 opacity-0 transition group-hover:opacity-100 hover:text-muted focus:opacity-100 active:cursor-grabbing [@media(hover:none)]:opacity-70"
                            aria-label="Drag to reorder">
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="4" r="1.2"/><circle cx="11" cy="4" r="1.2"/>
                            <circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/>
                            <circle cx="5" cy="12" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                        </svg>
                    </button>
                    <div class="flex-1 border-t border-dashed border-white/10" aria-hidden="true"></div>
                    <button type="button"
                            wire:click="deleteItem({{ $item->id }})"
                            wire:confirm="Delete this divider?"
                            class="shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-red-400 focus:opacity-100"
                            aria-label="Delete">×</button>
                </li>
            @else
                <li
                    data-item-id="{{ $item->id }}"
                    wire:key="item-{{ $item->id }}"
                    class="group flex items-start gap-2 rounded px-1 py-2 hover:bg-white/5"
                >
                    <button type="button"
                            data-drag-handle
                            class="mt-0.5 shrink-0 cursor-grab touch-none px-0.5 text-muted/50 opacity-0 transition group-hover:opacity-100 hover:text-muted focus:opacity-100 active:cursor-grabbing [@media(hover:none)]:opacity-70"
                            aria-label="Drag to reorder">
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="4" r="1.2"/><circle cx="11" cy="4" r="1.2"/>
                            <circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/>
                            <circle cx="5" cy="12" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                        </svg>
                    </button>

                    <button type="button"
                            wire:click="toggle({{ $item->id }})"
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition
                                {{ $item->is_completed ? 'todo-check-done' : 'border-muted/60 bg-transparent' }}"
                            aria-label="{{ $item->is_completed ? 'Mark incomplete' : 'Mark complete' }}">
                        @if ($item->is_completed)
                            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @endif
                    </button>

                    <span @class([
                        'min-w-0 flex-1 text-[15px] leading-snug',
                        'text-muted line-through' => $item->is_completed,
                        'text-body' => ! $item->is_completed,
                    ])>
                        {{ $item->title }}
                    </span>

                    <button type="button"
                            wire:click="moveToToday({{ $item->id }})"
                            wire:confirm="Move this to today's milestones? It will be removed from the backlog."
                            class="mt-0.5 shrink-0 text-muted/50 opacity-0 transition group-hover:opacity-100 hover:text-link focus:opacity-100 [@media(hover:none)]:opacity-70"
                            title="Move to today"
                            aria-label="Move to today">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path d="M4 10L10 4M6 4h4v4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <button type="button"
                            wire:click="deleteItem({{ $item->id }})"
                            wire:confirm="Delete this backlog item?"
                            class="shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-red-400 focus:opacity-100"
                            aria-label="Delete">×</button>
                </li>
            @endif
        @endforeach
    </ul>

    <form wire:submit="addItem" class="mt-6 flex max-w-xl flex-col gap-2 sm:flex-row">
        <input type="text"
               wire:model="newTitle"
               placeholder="Add backlog item..."
               class="flex-1 rounded border border-white/10 bg-sidebar px-3 py-2 text-sm focus:border-link focus:outline-none" />
        <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-2 text-sm font-medium hover:border-link/40">
            Add
        </button>
        <button type="button"
                wire:click="addDivider"
                class="rounded border border-dashed border-white/15 px-4 py-2 text-sm text-muted hover:border-link/40 hover:text-link">
            Add divider
        </button>
    </form>
</div>
