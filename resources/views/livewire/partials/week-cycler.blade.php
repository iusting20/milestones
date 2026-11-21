<div class="flex min-w-0 items-center gap-0 whitespace-nowrap text-[11px] text-muted sm:gap-0.5 sm:text-xs {{ $class ?? '' }}" data-week-toggle>
    <button type="button" wire:click="previousWeek"
            class="rounded px-1 py-0.5 leading-none hover:bg-white/5 hover:text-body sm:px-1.5"
            aria-label="Previous week">‹</button>
    <span class="max-w-[7.5rem] truncate px-0.5 tabular-nums sm:max-w-none sm:px-1" data-week-label title="{{ $weekKey }}">{{ $weekRange }}</span>
    @if (empty($hideFuture) || ! $isCurrentWeek)
        <button type="button" wire:click="nextWeek"
                class="rounded px-1 py-0.5 leading-none hover:bg-white/5 hover:text-body sm:px-1.5"
                aria-label="Next week">›</button>
    @endif
    @unless ($isCurrentWeek)
        <button type="button" wire:click="{{ $todayAction }}"
                class="ml-0.5 rounded px-1 py-0.5 hover:bg-white/5 hover:text-body sm:ml-1 sm:px-1.5" data-week-today>This week</button>
    @endunless
</div>
