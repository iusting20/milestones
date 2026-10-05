<section wire:key="day-{{ $day['date'] }}"
         @class([
             'flex h-full flex-col rounded-lg border px-2 py-2',
             'border-link/40 bg-today' => $day['isToday'],
             'border-white/10 bg-sidebar/60' => ! $day['isToday'],
         ])>
    <header class="mb-3 border-b border-white/10 pb-2 text-center">
        <div @class([
            'text-sm font-semibold uppercase tracking-[0.18em]',
            'text-link' => $day['isToday'],
            'text-muted' => ! $day['isToday'],
        ])>
            {{ $day['label'] }}
        </div>
        <div @class([
            'mt-1 text-2xl font-semibold tabular-nums',
            'text-body' => $day['isToday'],
            'text-muted' => ! $day['isToday'],
        ])>
            {{ $day['dayNumber'] }}
        </div>
    </header>

    <div class="min-h-0 flex-1 space-y-2">
        @forelse ($day['logs'] as $log)
            <div wire:key="{{ $log['key'] }}"
                 class="space-y-1"
                 title="{{ $log['title'] }} · {{ $log['started'] }} → {{ $log['finished'] }}">
                <div class="flex items-center gap-1.5">
                    <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $log['color'] }}"></span>
                    <div class="truncate text-[11px] leading-snug text-body">
                        {{ $log['title'] }}
                    </div>
                </div>
                <div class="flex h-6 items-center gap-1.5">
                    <div class="min-w-0 flex-1">
                        <div @class([
                                 'h-5 rounded-sm transition-opacity duration-150',
                                 'cursor-pointer' => true,
                             ])
                             style="width: {{ max(8, $log['width']) }}%; @if ($log['timed']) background-color: {{ $log['color'] }}; @else background-color: transparent; background-image: repeating-linear-gradient(-45deg, {{ $log['color'] }} 0 3px, transparent 3px 7px); @endif"
                             role="img"
                             aria-label="{{ $log['duration'] }}"
                             @mouseenter="hoverTitle = @js($log['title'])"
                             @mouseleave="hoverTitle = null"
                             @click="focusTitle(@js($log['title']))"
                             :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($log['title']) }"
                             @if (! $log['timed']) data-untimed-bar @endif></div>
                    </div>
                    <span class="shrink-0 text-[10px] tabular-nums text-muted">
                        {{ $log['duration'] }}
                    </span>
                </div>
                @include('livewire.partials.task-log-times', [
                    'logId' => $log['id'],
                    'started' => $log['started'],
                    'finished' => $log['finished'],
                    'timed' => $log['timed'],
                    'context' => 'day',
                ])
            </div>
        @empty
            <p class="py-4 text-center text-[11px] text-faint">-</p>
        @endforelse
    </div>

    <div class="mt-3 border-t border-white/10 pt-3">
        <div class="mx-auto flex h-48 w-48 items-center justify-center sm:h-52 sm:w-52">
            @if ($day['pieSlices'] === [])
                <svg viewBox="0 0 100 100" class="h-full w-full text-faint" aria-hidden="true">
                    <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"/>
                </svg>
            @else
                <svg viewBox="0 0 100 100" class="h-full w-full" role="img" aria-label="Time split for {{ $day['label'] }}">
                    @foreach ($day['pieSlices'] as $slice)
                        @if ($slice['full'])
                            <circle cx="50" cy="50" r="42"
                                    fill="{{ $slice['color'] }}"
                                    class="cursor-pointer transition-opacity duration-150"
                                    @mouseenter="hoverTitle = @js($slice['title'])"
                                    @mouseleave="hoverTitle = null"
                                    @click="focusTitle(@js($slice['title']))"
                                    :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($slice['title']) }">
                                <title>{{ $slice['title'] }} · {{ $slice['duration'] }}</title>
                            </circle>
                        @else
                            <path d="{{ $slice['path'] }}"
                                  fill="{{ $slice['color'] }}"
                                  stroke="#1e1e1e"
                                  stroke-width="1"
                                  class="cursor-pointer transition-opacity duration-150"
                                  @mouseenter="hoverTitle = @js($slice['title'])"
                                  @mouseleave="hoverTitle = null"
                                  @click="focusTitle(@js($slice['title']))"
                                  :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($slice['title']) }">
                                <title>{{ $slice['title'] }} · {{ $slice['duration'] }}</title>
                            </path>
                        @endif
                    @endforeach
                </svg>
            @endif
        </div>
        <p class="mt-2 text-center text-[22px] font-semibold tabular-nums text-muted">
            Total: {{ $day['totalDuration'] }}
        </p>
    </div>
</section>
