<div class="mx-auto max-w-3xl px-3 py-6 sm:px-4">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold uppercase tracking-widest text-body sm:text-3xl">Productivity Log</h1>

        @include('livewire.partials.week-cycler', ['weekKey' => $week, 'todayAction' => 'goToToday'])

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('milestones.drip-matrix', ['week' => $week]) }}"
               class="inline-flex items-center gap-1.5 rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
               wire:navigate>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/>
                </svg>
                DRIP Matrix
            </a>

            <a href="{{ route('milestones.stats') }}"
               class="inline-flex items-center gap-1.5 rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
               wire:navigate>
                Stats
            </a>

            <a href="{{ route('milestones.log', ['week' => $week]) }}"
               class="inline-flex items-center gap-1.5 rounded border border-white/10 px-3 py-1.5 text-sm text-muted no-underline hover:bg-panel hover:text-body"
               wire:navigate>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Log
            </a>
        </div>
    </div>

    <div class="space-y-8">
        <livewire:week-list
            :week="$week"
            :key="'week-list-'.$week"
        />

        @foreach ($days as $day)
            <livewire:day-list
                :date="$day->toDateString()"
                :day-label="strtoupper($day->format('l'))"
                :is-today="$day->toDateString() === $today"
                :key="'day-'.$day->toDateString()"
            />
        @endforeach

        <section>
            <button type="button"
                    wire:click="$toggle('showOpenItems')"
                    class="mb-0 flex w-full items-center gap-2 text-left text-sm font-bold uppercase tracking-wider text-muted hover:text-body"
                    aria-expanded="{{ $showOpenItems ? 'true' : 'false' }}">
                <span>Started and not finished</span>
                <svg @class([
                        'h-3 w-3 shrink-0',
                        'text-link' => $showOpenItems,
                    ]) viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M2.5 4.5L6 8l3.5-3.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                @unless ($showOpenItems)
                    <span class="ml-auto text-xs font-normal normal-case tracking-normal text-muted/60">
                        {{ $openItems->count() }} {{ $openItems->count() === 1 ? 'item' : 'items' }}
                    </span>
                @endunless
            </button>

            @if ($showOpenItems)
                <div class="mt-3">
                    @if ($openItems->isEmpty())
                        <p class="px-1 text-sm text-muted/70">None</p>
                    @else
                        <ul class="space-y-1">
                            @foreach ($openItems as $item)
                                @if (! $loop->first && $item->date->toDateString() !== $openItems[$loop->index - 1]->date->toDateString())
                                    <li class="py-2" aria-hidden="true">
                                        <div class="border-t border-dashed border-white/10"></div>
                                    </li>
                                @endif
                                <li wire:key="open-{{ $item->id }}">
                                    <button type="button"
                                            wire:click="focusOpenItem({{ $item->id }}, '{{ $item->date->toDateString() }}')"
                                            class="flex w-full items-baseline gap-3 rounded px-1 py-1.5 text-left text-[15px] text-body hover:bg-white/5">
                                        <span class="w-24 shrink-0 text-xs font-semibold uppercase tracking-wide text-muted">{{ $item->date->format('l') }}</span>
                                        <span>{{ $item->title }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </section>

        <div class="flex justify-center">
            <a href="{{ route('backlog') }}"
               class="rounded border border-white/10 px-4 py-1.5 text-sm text-link no-underline hover:bg-panel">
                Backlog
            </a>
        </div>
    </div>
</div>
