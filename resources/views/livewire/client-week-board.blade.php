<div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold uppercase tracking-widest text-body sm:text-3xl">Week Board</h1>

        <div class="ml-auto flex items-center gap-1 rounded border border-white/10 p-0.5 text-sm" role="group" aria-label="Rows">
            @foreach (['partners' => 'Partners', 'assets' => 'Assets'] as $option => $label)
                <button type="button"
                        wire:click="$set('view', '{{ $option }}')"
                        @class([
                            'rounded px-3 py-1 transition',
                            'bg-white/10 text-body font-semibold' => $hub === $option,
                            'text-muted hover:text-body' => $hub !== $option,
                        ])
                        aria-pressed="{{ $hub === $option ? 'true' : 'false' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($clients->isEmpty())
        @if ($hub === 'assets')
            <p class="text-muted">No assets yet. Add them under Links → Assets.</p>
        @else
            <p class="text-muted">No partners yet. Add clients under Links → Partners.</p>
        @endif
    @else
        <div class="overflow-x-auto">
            <div class="min-w-[720px]">
                <div class="grid grid-cols-[10rem_repeat(3,minmax(0,1fr))] gap-3 pb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    <div>{{ $hub === 'assets' ? 'Asset' : 'Partner' }}</div>
                    @foreach ($statuses as $status)
                        <div>{{ $status->label() }}</div>
                    @endforeach
                </div>

                <div
                    data-sortable
                    data-sortable-group="week-board-rows-{{ $this->getId() }}"
                    data-sortable-handle="[data-row-handle]"
                    data-sortable-method="reorderClients"
                    data-component-id="{{ $this->getId() }}"
                    wire:key="client-rows-{{ $hub }}"
                >
                @foreach ($clients as $client)
                    <div class="grid grid-cols-[10rem_repeat(3,minmax(0,1fr))] gap-3 border-t border-white/10 py-3" data-item-id="{{ $client->id }}" wire:key="client-{{ $client->id }}">
                        <div class="flex items-start gap-1 pt-1 font-semibold text-body">
                            <button type="button"
                                    data-row-handle
                                    class="mt-0.5 shrink-0 cursor-grab touch-none text-muted/50 hover:text-muted active:cursor-grabbing"
                                    aria-label="Drag to reorder partner">
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                    <circle cx="5" cy="4" r="1.2"/><circle cx="11" cy="4" r="1.2"/>
                                    <circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/>
                                    <circle cx="5" cy="12" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                                </svg>
                            </button>
                            <span class="min-w-0">{{ $client->name }}</span>
                        </div>

                        @foreach ($statuses as $status)
                            @php($lane = $client->id.'-'.$status->value)
                            <div class="flex flex-col rounded bg-panel p-2" wire:key="lane-{{ $lane }}">
                                <ul
                                    class="min-h-[2.5rem] flex-1 space-y-1"
                                    data-sortable
                                    data-sortable-nest
                                    data-parent-id="{{ $client->id * 10 + $status->code() }}"
                                    data-component-id="{{ $this->getId() }}"
                                    wire:key="list-{{ $lane }}"
                                >
                                    @foreach ($items->get($lane, collect()) as $item)
                                        <li
                                            data-item-id="{{ $item->id }}"
                                            wire:key="card-{{ $item->id }}"
                                            class="group flex items-start gap-1 rounded border border-white/10 bg-canvas px-2 py-1.5 text-sm"
                                        >
                                            <button type="button"
                                                    data-drag-handle
                                                    class="mt-0.5 shrink-0 cursor-grab touch-none text-muted/50 hover:text-muted active:cursor-grabbing"
                                                    aria-label="Drag to move">
                                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                    <circle cx="5" cy="4" r="1.2"/><circle cx="11" cy="4" r="1.2"/>
                                                    <circle cx="5" cy="8" r="1.2"/><circle cx="11" cy="8" r="1.2"/>
                                                    <circle cx="5" cy="12" r="1.2"/><circle cx="11" cy="12" r="1.2"/>
                                                </svg>
                                            </button>
                                            <span @class([
                                                'min-w-0 flex-1 leading-snug',
                                                'text-muted line-through' => $status === \App\Enums\WeekBoardStatus::Done,
                                                'text-body' => $status !== \App\Enums\WeekBoardStatus::Done,
                                            ])>{{ $item->title }}</span>
                                            <span class="flex shrink-0 items-center opacity-0 transition group-hover:opacity-100 focus-within:opacity-100 [@media(hover:none)]:opacity-70">
                                                @if ($item->todoItem === null)
                                                    <button type="button"
                                                            wire:click="startCard({{ $item->id }})"
                                                            class="px-1 text-[10px] uppercase text-muted hover:text-link"
                                                            title="Create a milestone for today and start its timer">Start</button>
                                                @else
                                                    <a href="{{ route('milestones') }}"
                                                       class="px-1 text-[10px] uppercase text-link no-underline"
                                                       title="Started as a milestone on {{ $item->todoItem->date?->format('D j M') }}">Started</a>
                                                @endif
                                                <button type="button"
                                                        wire:click="deleteCard({{ $item->id }})"
                                                        wire:confirm="Delete this card?"
                                                        class="px-1 text-xs text-muted hover:text-red-400"
                                                        aria-label="Delete">×</button>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if ($status === \App\Enums\WeekBoardStatus::Planned)
                                @if ($addingLane === $lane)
                                    <form wire:submit.prevent="addCard({{ $client->id }}, '{{ $status->value }}')" class="mt-1" wire:key="adding-{{ $lane }}">
                                        <input type="text"
                                               wire:model="newTitles.{{ $lane }}"
                                               wire:keydown.escape="cancelAdding"
                                               x-on:blur="if (! $el.value.trim()) $wire.cancelAdding()"
                                               class="w-full rounded border border-white/10 bg-canvas px-2 py-1 text-sm text-body"
                                               placeholder="Card title…"
                                               autofocus>
                                    </form>
                                @else
                                    <button type="button"
                                            wire:click="startAdding({{ $client->id }}, '{{ $status->value }}')"
                                            class="mt-1 self-start rounded px-1 text-xs text-muted hover:text-body">+ Add</button>
                                @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
