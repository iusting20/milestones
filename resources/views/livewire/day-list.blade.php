<section @class([
    'rounded-lg px-2 py-3 sm:px-3',
    'bg-today border-l-4 border-check' => $isToday,
])>
    @if ($isToday)
        <h2 class="mb-0 flex w-full items-center gap-2 text-left text-sm font-bold uppercase tracking-wider text-muted">
            <span>{{ $dayLabel }}</span>
            <span class="text-xs font-semibold normal-case tracking-normal text-link">today</span>
        </h2>
    @else
        <button type="button"
                wire:click="toggleExpanded"
                @class([
                    'mb-0 flex w-full items-center gap-2 text-left text-sm font-bold uppercase tracking-wider',
                    'text-muted hover:text-body' => $date <= \App\Support\Clock::todayString(),
                    'text-faint hover:text-muted' => $date > \App\Support\Clock::todayString(),
                ])
                aria-expanded="{{ $expanded ? 'true' : 'false' }}">
            <span>{{ $dayLabel }}</span>
            <svg @class([
                    'h-3 w-3 shrink-0',
                    'text-link' => $expanded,
                ]) viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="M2.5 4.5L6 8l3.5-3.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            @unless ($expanded)
                <span class="ml-auto text-xs font-normal normal-case tracking-normal text-muted/60">
                    {{ $itemCount }} {{ $itemCount === 1 ? 'item' : 'items' }}
                </span>
            @endunless
        </button>
    @endif

    @if ($expanded)
        <div class="mt-3">
            <ul
                class="space-y-1"
                data-sortable
                data-sortable-nest
                data-component-id="{{ $this->getId() }}"
                wire:key="list-{{ $date }}"
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
                                    wire:click="delete({{ $item->id }})"
                                    wire:confirm="Delete this divider?"
                                    class="shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-red-400 focus:opacity-100"
                                    aria-label="Delete">×</button>
                        </li>
                    @else
                        @include('livewire.partials.todo-item', ['item' => $item, 'nested' => false])
                    @endif
                @endforeach
            </ul>

            @if ($items->isEmpty() && ! $adding)
                <p class="px-1 py-1 text-sm text-muted/70">No items yet</p>
            @endif

            @if ($adding)
                <form wire:submit.prevent="addItem" class="mt-1 flex items-center gap-2 px-1 py-1.5" wire:key="adding-{{ $date }}">
                    <span class="ml-6 flex h-5 w-5 shrink-0 rounded-full border-2 border-muted/40" aria-hidden="true"></span>
                    <input
                        type="text"
                        wire:model="newTitle"
                        wire:blur="finishAdding"
                        placeholder="New task…"
                        x-init="$nextTick(() => $el.focus())"
                        class="w-full flex-1 border-0 bg-transparent px-0 py-0 text-[15px] text-body placeholder:text-muted/50 focus:outline-none focus:ring-0"
                    />
                </form>
            @endif

            <div class="group/add mt-2 flex flex-wrap items-center gap-2">
                <button type="button"
                        wire:click="startAdding"
                        class="flex h-8 w-8 items-center justify-center rounded text-lg leading-none text-muted/40 transition hover:text-link"
                        aria-label="Add task">
                    +
                </button>
                <button type="button"
                        wire:click="copyUnfinishedFromPreviousDay"
                        class="text-xs text-muted opacity-0 transition group-hover/add:opacity-100 hover:text-link"
                        aria-label="Copy unfinished milestones from previous day">
                    copy unfinished milestones from previous day
                </button>
                <button type="button"
                        wire:click="addDivider"
                        class="text-xs text-muted opacity-0 transition group-hover/add:opacity-100 hover:text-link"
                        aria-label="Add divider">
                    ---
                </button>
            </div>
        </div>
    @endif
</section>
