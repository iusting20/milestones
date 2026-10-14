<section class="rounded-lg border-l-4 border-[#ffe600] bg-today px-2 py-3 sm:px-3">
    <h2 class="mb-0 flex w-full items-center gap-2 text-left text-sm font-bold uppercase tracking-wider text-muted">
        <span>For this week</span>
    </h2>

    <div class="mt-3">
        <ul
            class="space-y-1"
            data-sortable
            data-sortable-nest
            data-component-id="{{ $this->getId() }}"
            wire:key="list-week-{{ $week }}"
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
                                class="hover:text-[#ffe600] focus-visible:text-[#ffe600] shrink-0 cursor-grab touch-none px-0.5 text-muted/50 opacity-0 transition group-hover:opacity-100 focus:opacity-100 active:cursor-grabbing [@media(hover:none)]:opacity-70"
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
                    @include('livewire.partials.todo-item', ['item' => $item, 'nested' => false, 'weekScoped' => true])
                @endif
            @endforeach
        </ul>

        @if ($items->isEmpty() && ! $adding)
            <p class="px-1 py-1 text-sm text-muted/70">No items yet</p>
        @endif

        @if ($adding)
            <form wire:submit.prevent="addItem" class="mt-1 flex items-center gap-2 px-1 py-1.5" wire:key="adding-week-{{ $week }}">
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
                    class="hover:text-[#ffe600] focus-visible:text-[#ffe600] flex h-8 w-8 items-center justify-center rounded text-lg leading-none text-muted/40 transition"
                    aria-label="Add task">
                +
            </button>
            <button type="button"
                    wire:click="copyUnfinishedFromPreviousWeek"
                    class="hover:text-[#ffe600] focus-visible:text-[#ffe600] text-xs text-muted opacity-0 transition group-hover/add:opacity-100"
                    aria-label="Copy unfinished milestones from last week">
                copy unfinished milestones from last week
            </button>
            <button type="button"
                    wire:click="addDivider"
                    class="hover:text-[#ffe600] focus-visible:text-[#ffe600] text-xs text-muted opacity-0 transition group-hover/add:opacity-100"
                    aria-label="Add divider">
                ---
            </button>
        </div>
    </div>

    @if ($showMoveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             wire:keydown.escape.window="closeMoveModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="move-to-day-title">
            <div class="absolute inset-0 bg-black/60" wire:click="closeMoveModal" aria-hidden="true"></div>

            <div class="relative z-10 w-full max-w-md rounded-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="move-to-day-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        Move to day
                    </h2>
                    <button type="button"
                            wire:click="closeMoveModal"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body"
                            aria-label="Close">×</button>
                </div>

                <div>
                    <span class="mb-2 block text-xs uppercase tracking-[0.14em] text-muted">Day</span>
                    <div class="flex flex-wrap gap-1" role="group" aria-label="Day of week">
                        @foreach ($selectableDays as $day)
                            <button type="button"
                                    wire:click="moveToDay('{{ $day['date'] }}')"
                                    class="hover:border-[#ffe600] hover:text-[#ffe600] focus-visible:border-[#ffe600] focus-visible:text-[#ffe600] rounded border border-white/10 px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted transition">
                                {{ $day['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="button"
                            wire:click="closeMoveModal"
                            class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
