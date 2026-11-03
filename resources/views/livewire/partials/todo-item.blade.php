@php
    $weekScoped = $weekScoped ?? false;
    $glyphHover = $weekScoped ? 'hover:text-[#ffe600] focus-visible:text-[#ffe600]' : 'hover:text-link';
@endphp
<li
    data-item-id="{{ $item->id }}"
    @unless ($nested) data-nestable="1" @endunless
    wire:key="item-{{ $item->id }}"
    @class([
        'rounded',
        'ml-7' => $nested,
    ])
>
    <div class="group flex items-start gap-2 rounded px-1 py-1.5 hover:bg-white/5">
                        <button type="button"
                                data-drag-handle
                                class="mt-0.5 shrink-0 cursor-grab touch-none px-0.5 text-muted/50 opacity-0 transition group-hover:opacity-100 {{ $weekScoped ? 'hover:text-[#ffe600] focus-visible:text-[#ffe600]' : 'hover:text-muted' }} focus:opacity-100 active:cursor-grabbing [@media(hover:none)]:opacity-70"
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

                        @if ($weekScoped ?? false)
                            <button type="button"
                                    wire:click="openMoveModal({{ $item->id }}, false)"
                                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center text-muted/60 opacity-0 transition group-hover:opacity-100 hover:text-[#ffe600] focus:opacity-100 focus-visible:text-[#ffe600] [@media(hover:none)]:opacity-70"
                                    title="Move to day"
                                    aria-label="Move to day">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path d="M4 10L10 4M6 4h4v4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        @else
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
                        @endif

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
                                    'flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[15px] leading-snug',
                                    'opacity-60' => $item->is_completed && $item->links->isNotEmpty(),
                                ])>
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
                                            class="{{ $glyphHover }} block px-0.5 text-sm leading-none text-muted/40 transition"
                                            aria-label="Add another link">
                                        +
                                    </button>
                                </div>
                            @endif
                        </div>

                        @if ($item->isLogging())
                            @if ($editingLoggingId === $item->id)
                                <span class="inline-flex shrink-0 items-center gap-1.5 self-center tabular-nums text-link">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4l2.5 1.5M12 5.5V3m-2 0h4"/>
                                        <circle cx="12" cy="14" r="7"/>
                                    </svg>
                                    <input type="time"
                                           wire:model="editLoggingStartedAt"
                                           x-data="{ armed: false }"
                                           x-init="$nextTick(() => { $el.focus(); setTimeout(() => armed = true, 150) })"
                                           x-on:blur="if (armed) $wire.finishEditingLoggingStart()"
                                           x-on:keydown.enter.prevent="$wire.finishEditingLoggingStart()"
                                           x-on:keydown.escape.prevent="armed = false; $wire.cancelEditingLoggingStart()"
                                           class="w-[6.5rem] rounded border border-link/40 bg-transparent px-1 py-0 text-[15px] font-semibold leading-none text-link focus:border-link focus:outline-none"
                                           aria-label="Edit logging start time" />
                                </span>
                                @error('editLoggingStartedAt')
                                    <p class="basis-full text-[10px] text-red-400">{{ $message }}</p>
                                @enderror
                            @else
                                <button type="button"
                                        wire:click.stop="startEditingLoggingStart({{ $item->id }})"
                                        class="inline-flex shrink-0 cursor-text items-center gap-1.5 self-center rounded tabular-nums text-link hover:bg-white/5"
                                        aria-label="Edit logging start {{ $item->logging_started_at->timezone('Europe/Bucharest')->format('H:i') }}"
                                        title="Started {{ $item->logging_started_at->timezone('Europe/Bucharest')->format('H:i') }}. Click to change.">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4l2.5 1.5M12 5.5V3m-2 0h4"/>
                                        <circle cx="12" cy="14" r="7"/>
                                    </svg>
                                    <span class="flex flex-col items-start">
                                        <span class="text-[15px] font-semibold leading-none">{{ $item->logging_started_at->timezone('Europe/Bucharest')->format('H:i') }}</span>
                                        <span class="mt-0.5 text-[10px] font-normal leading-none text-white"
                                              wire:key="elapsed-{{ $item->id }}-{{ $item->logging_started_at->getTimestampMs() }}"
                                              data-elapsed-since="{{ $item->logging_started_at->getTimestampMs() }}"
                                              x-data="{
                                                  start: {{ $item->logging_started_at->getTimestampMs() }},
                                                  label: '',
                                                  timer: null,
                                                  tick() {
                                                      const m = Math.max(0, Math.floor((Date.now() - this.start) / 60000));
                                                      this.label = m >= 60 ? Math.floor(m / 60) + 'h ' + (m % 60) + 'm' : m + 'min';
                                                  },
                                                  init() { this.tick(); this.timer = setInterval(() => this.tick(), 15000); },
                                                  destroy() { clearInterval(this.timer); },
                                              }"
                                              x-text="label"></span>
                                    </span>
                                </button>
                            @endif
                        @endif

                        @if ($showLogControls && ! ($weekScoped ?? false))
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

                        @unless ($nested)
                            <button type="button"
                                    wire:click.stop="startAddingChild({{ $item->id }})"
                                    class="{{ $glyphHover }} mt-0.5 shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 focus:opacity-100 [@media(hover:none)]:opacity-70"
                                    aria-label="Add sub-item">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M8 6h12M8 12h12M8 18h12"/>
                                    <path d="M4 6h.01M4 12h.01M4 18h.01"/>
                                    <path d="M4 9v6"/>
                                </svg>
                            </button>
                        @endunless

                        @if (($isToday ?? false) && ! $weekScoped)
                            <button type="button"
                                    wire:click.stop="moveToThisWeek({{ $item->id }})"
                                    class="mt-0.5 shrink-0 px-1 text-muted opacity-0 transition group-hover:opacity-100 hover:text-[#ffe600] focus:opacity-100 focus-visible:text-[#ffe600] [@media(hover:none)]:opacity-70"
                                    title="Move to For this week"
                                    aria-label="Move to For this week">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 16V4"/>
                                    <path d="M7 9l5-5 5 5"/>
                                    <path d="M4 20h16"/>
                                </svg>
                            </button>
                        @endif

                        @if ($weekScoped)
                            <button type="button"
                                    wire:click.stop="moveToBacklog({{ $item->id }})"
                                    class="mt-0.5 shrink-0 px-1 text-muted opacity-0 transition group-hover:opacity-100 hover:text-[#c10e34] focus:opacity-100 focus-visible:text-[#c10e34] [@media(hover:none)]:opacity-70"
                                    title="Move to backlog"
                                    aria-label="Move to backlog">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 16V4"/>
                                    <path d="M7 9l5-5 5 5"/>
                                    <path d="M4 20h16"/>
                                </svg>
                            </button>
                        @endif

                        <button type="button"
                                wire:click.stop="startLinking({{ $item->id }})"
                                class="{{ $glyphHover }} mt-0.5 shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 focus:opacity-100 [@media(hover:none)]:opacity-70"
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
                        </div>

    @unless ($nested)
        <ul
            @class([
                'mt-1 space-y-1',
                'hidden' => $item->children->isEmpty() && $addingChildFor !== $item->id,
            ])
            @if ($item->children->isNotEmpty() || $addingChildFor === $item->id)
                data-sortable
                data-sortable-nest
                data-parent-id="{{ $item->id }}"
                data-component-id="{{ $this->getId() }}"
            @endif
            wire:key="children-{{ $item->id }}"
        >
            @foreach ($item->children as $child)
                @include('livewire.partials.todo-item', ['item' => $child, 'nested' => true, 'weekScoped' => $weekScoped])
            @endforeach
            @if ($addingChildFor === $item->id)
                <li data-drop-slot class="ml-7" wire:key="adding-child-{{ $item->id }}">
                    <form wire:submit.prevent="addChild" @class([
                        'flex min-h-12 items-center gap-2 rounded border border-dashed px-2 py-2',
                        'border-[#ffe600]/70 bg-[#ffe600]/5' => $weekScoped,
                        'border-link/70 bg-link/5' => ! $weekScoped,
                    ])>
                        <span @class([
                            'flex h-5 w-5 shrink-0 rounded-full border-2 border-dashed',
                            'border-[#ffe600]/70' => $weekScoped,
                            'border-link/70' => ! $weekScoped,
                        ]) aria-hidden="true"></span>
                        <input
                            type="text"
                            wire:model="newChildTitle"
                            wire:keydown.escape="cancelAddingChild"
                            wire:keydown.enter.prevent="addChild"
                            placeholder="Type a sub-item, or drop a task here"
                            class="w-full flex-1 border-0 bg-transparent px-0 py-0 text-[15px] text-body placeholder:text-muted/50 focus:outline-none focus:ring-0"
                        />
                    </form>
                </li>
            @endif
        </ul>
    @endunless
</li>
