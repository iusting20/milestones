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
                    'text-muted hover:text-body' => $date <= now()->toDateString(),
                    'text-faint hover:text-muted' => $date > now()->toDateString(),
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
                    {{ $items->count() }} {{ $items->count() === 1 ? 'item' : 'items' }}
                </span>
            @endunless
        </button>
    @endif

    @if ($expanded)
        <div class="mt-3">
            <ul
                class="space-y-1"
                data-sortable
                data-component-id="{{ $this->getId() }}"
                wire:key="list-{{ $date }}"
            >
                @foreach ($items as $item)
                    <li
                        data-item-id="{{ $item->id }}"
                        wire:key="item-{{ $item->id }}"
                        @class([
                            'group flex items-start gap-2 rounded px-1 py-1.5 hover:bg-white/5',
                            'border border-link' => $item->isLogging(),
                            'border border-transparent' => ! $item->isLogging(),
                        ])
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

                        <div class="min-w-0 flex-1">
                            @if ($editingId === $item->id)
                                <input
                                    type="text"
                                    wire:model.live.debounce.700ms="editTitle"
                                    wire:blur="finishEditing"
                                    wire:keydown.enter.prevent="finishEditing"
                                    x-init="$nextTick(() => $el.focus())"
                                    @class([
                                        'w-full border-0 bg-transparent px-0 py-0 text-[15px] leading-snug focus:outline-none focus:ring-0',
                                        'text-muted' => $item->is_completed,
                                        'text-body' => ! $item->is_completed,
                                    ])
                                />
                            @else
                                <div @class([
                                    'flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-[15px] leading-snug',
                                    'opacity-60' => $item->is_completed && $item->links->isNotEmpty(),
                                ])>
                                    @if ($item->isLogging())
                                        <span class="inline-flex shrink-0 items-center gap-1 tabular-nums text-link"
                                              aria-label="Logging since {{ $item->logging_started_at->timezone('Europe/Bucharest')->format('H:i') }}"
                                              title="Started {{ $item->logging_started_at->timezone('Europe/Bucharest')->format('H:i') }}">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4l2.5 1.5M12 5.5V3m-2 0h4"/>
                                                <circle cx="12" cy="14" r="7"/>
                                            </svg>
                                            <span class="text-xs">{{ $item->logging_started_at->timezone('Europe/Bucharest')->format('H:i') }}</span>
                                        </span>
                                    @endif
                                    <button type="button"
                                            wire:click.stop="startEditing({{ $item->id }})"
                                            @class([
                                                'cursor-text text-left',
                                                'text-muted' => $item->is_completed,
                                                'text-body' => ! $item->is_completed,
                                            ])>
                                        {{ $item->title }}
                                    </button>
                                    @foreach ($item->links as $link)
                                        <a href="{{ $link->url }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="shrink-0 text-link hover:underline">
                                            {{ $link->title }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if ($linkingId === $item->id)
                                <div class="mt-1 space-y-1"
                                     x-data
                                     @focusout="if (!$el.contains($event.relatedTarget)) $wire.finishLinking()">
                                    @foreach ($editLinks as $index => $editLink)
                                        <div class="flex flex-wrap items-center gap-2" wire:key="edit-link-{{ $linkingId }}-{{ $index }}">
                                            <input
                                                type="text"
                                                wire:model="editLinks.{{ $index }}.title"
                                                wire:keydown.enter.prevent="finishLinking"
                                                wire:keydown.escape="cancelLinking"
                                                placeholder="Title"
                                                @if ($index === 0) x-init="$nextTick(() => $el.focus())" @endif
                                                class="w-28 shrink-0 border-0 border-b border-white/10 bg-transparent px-0 py-0.5 text-xs text-body placeholder:text-muted/50 focus:outline-none focus:ring-0 sm:w-36"
                                            />
                                            <input
                                                type="url"
                                                wire:model="editLinks.{{ $index }}.url"
                                                wire:keydown.enter.prevent="finishLinking"
                                                wire:keydown.escape="cancelLinking"
                                                placeholder="https://…"
                                                class="min-w-0 flex-1 border-0 border-b border-white/10 bg-transparent px-0 py-0.5 text-xs text-link placeholder:text-muted/50 focus:outline-none focus:ring-0"
                                            />
                                        </div>
                                    @endforeach
                                    <button type="button"
                                            wire:click="addLinkRow"
                                            class="block px-0.5 text-sm leading-none text-muted/40 transition hover:text-link"
                                            aria-label="Add another link">
                                        +
                                    </button>
                                </div>
                            @endif
                        </div>

                        @if ($showLogControls)
                            <div class="mt-0.5 flex shrink-0 items-center gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100 [@media(hover:none)]:opacity-70">
                                <button type="button"
                                        wire:click.stop="startLogging({{ $item->id }})"
                                        @class([
                                            'rounded px-1.5 py-0.5 text-xs transition',
                                            'text-muted hover:text-link' => ! $item->isLogging(),
                                            'text-faint cursor-default' => $item->isLogging(),
                                        ])
                                        @disabled($item->isLogging())
                                        aria-label="Start logging">
                                    Start
                                </button>
                                <button type="button"
                                        wire:click.stop="finishLogging({{ $item->id }})"
                                        @class([
                                            'rounded px-1.5 py-0.5 text-xs transition',
                                            'text-muted hover:text-link' => $item->isLogging(),
                                            'text-faint cursor-default' => ! $item->isLogging(),
                                        ])
                                        @disabled(! $item->isLogging())
                                        aria-label="Finish logging">
                                    Finish
                                </button>
                            </div>
                        @endif

                        <button type="button"
                                wire:click.stop="startLinking({{ $item->id }})"
                                class="mt-0.5 shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-link focus:opacity-100 [@media(hover:none)]:opacity-70"
                                aria-label="{{ $item->links->isNotEmpty() ? 'Edit links' : 'Add link' }}">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                            </svg>
                        </button>

                        <button type="button"
                                wire:click="delete({{ $item->id }})"
                                wire:confirm="Delete this item?"
                                class="shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-red-400 focus:opacity-100"
                                aria-label="Delete">×</button>
                    </li>
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

            <div class="mt-2 flex flex-wrap items-center gap-2"
                 onmouseenter="this.querySelector('[data-copy-prev]').style.opacity='1'"
                 onmouseleave="this.querySelector('[data-copy-prev]').style.opacity='0'">
                <button type="button"
                        wire:click="startAdding"
                        class="flex h-8 w-8 items-center justify-center rounded text-lg leading-none text-muted/40 transition hover:text-link"
                        aria-label="Add task">
                    +
                </button>
                <button type="button"
                        data-copy-prev
                        wire:click="copyUnfinishedFromPreviousDay"
                        class="text-xs text-muted transition hover:text-link"
                        style="opacity:0">
                    copy unfinished milestones from previous day
                </button>
            </div>
        </div>
    @endif
</section>
