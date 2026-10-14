<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold uppercase tracking-widest text-body sm:text-3xl">Backlog</h1>

        <div class="flex items-center gap-1 text-sm text-muted" role="group" aria-label="Display">
            <span class="mr-1">display:</span>
            @foreach (['pending', 'finished', 'all'] as $option)
                <button type="button"
                        wire:click="$set('display', '{{ $option }}')"
                        @class([
                            'rounded px-2 py-1 transition',
                            'bg-white/10 text-body' => $display === $option,
                            'text-muted hover:text-body' => $display !== $option,
                        ])
                        aria-pressed="{{ $display === $option ? 'true' : 'false' }}">
                    {{ $option }}
                </button>
            @endforeach
        </div>

        <div class="ml-auto flex items-center gap-2">
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

            <a href="{{ route('backlog.drip-matrix') }}"
               class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
               wire:navigate>
                DRIP Matrix
            </a>
        </div>
    </div>

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

                    @if ($showTags)
                        @include('livewire.partials.drip-chips-row', ['item' => $item])
                    @endif

                    <button type="button"
                            wire:click="moveToThisWeek({{ $item->id }})"
                            wire:confirm="Move this to For this week? It will be removed from the backlog."
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center text-muted/60 opacity-0 transition group-hover:opacity-100 hover:text-[#ffe600] focus:opacity-100 focus-visible:text-[#ffe600] [@media(hover:none)]:opacity-70"
                            title="Move to For this week"
                            aria-label="Move to For this week">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path d="M4 10L10 4M6 4h4v4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <span @class([
                        'min-w-0 flex-1 text-[15px] leading-snug',
                        'text-muted line-through' => $item->is_completed,
                        'text-body' => ! $item->is_completed,
                    ])>
                        {{ $item->title }}
                    </span>

                    @if ($item->is_completed && $item->completed_at)
                        <span class="inline-flex shrink-0 items-center gap-1.5 self-center tabular-nums text-link"
                              title="Completed {{ $item->completed_at->timezone('Europe/Bucharest')->format('D j M') }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4l2.5 1.5M12 5.5V3m-2 0h4"/>
                                <circle cx="12" cy="14" r="7"/>
                            </svg>
                            <span class="text-[15px] font-semibold leading-none">{{ $item->completed_at->timezone('Europe/Bucharest')->format('D j M') }}</span>
                        </span>
                    @endif

                    <button type="button"
                            wire:click="deleteItem({{ $item->id }})"
                            wire:confirm="Delete this backlog item?"
                            class="shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-red-400 focus:opacity-100"
                            aria-label="Delete">×</button>
                </li>
            @endif
        @endforeach
    </ul>

    @if ($adding)
        <form wire:submit.prevent="addItem" class="mt-1 flex items-center gap-2 px-1 py-1.5" wire:key="adding-backlog">
            <span class="ml-6 flex h-5 w-5 shrink-0 rounded-full border-2 border-muted/40" aria-hidden="true"></span>
            <input
                type="text"
                wire:model="newTitle"
                wire:blur="finishAdding"
                placeholder="New backlog item…"
                x-init="$nextTick(() => $el.focus())"
                class="w-full flex-1 border-0 bg-transparent px-0 py-0 text-[15px] text-body placeholder:text-muted/50 focus:outline-none focus:ring-0"
            />
        </form>
    @endif

    <div class="group/add mt-2 flex flex-wrap items-center gap-2">
        <button type="button"
                wire:click="startAdding"
                class="flex h-8 w-8 items-center justify-center rounded text-lg leading-none text-muted/40 transition hover:text-link"
                aria-label="Add backlog item">
            +
        </button>
        <button type="button"
                wire:click="addDivider"
                class="text-xs text-muted opacity-0 transition group-hover/add:opacity-100 hover:text-link"
                aria-label="Add divider">
            ---
        </button>
    </div>
</div>
