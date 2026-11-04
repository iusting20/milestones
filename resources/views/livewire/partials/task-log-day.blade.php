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
                        <div class="h-5 cursor-pointer rounded-sm transition-opacity duration-150"
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
                    'startLogId' => $log['startLogId'] ?? $log['id'],
                    'finishLogId' => $log['finishLogId'] ?? $log['id'],
                    'todoItemId' => $log['todoItemId'] ?? null,
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

    @php($idle = $day['idle'])
    <div class="mt-3 border-t border-white/10 pt-3" data-idle-bar="{{ $day['date'] }}">
        <div class="mb-1 flex items-baseline justify-between text-[10px] uppercase tracking-[0.16em] text-muted">
            <span>Idle</span>
            <span class="text-faint">{{ $idle['idleMinutes'] > 0 ? round($idle['idleMinutes'] / 540 * 100).'%' : '—' }}</span>
        </div>
        <div class="relative h-3 w-full overflow-hidden rounded-sm bg-white/5 ring-1 ring-white/10">
            @if ($idle['future'])
                <div class="absolute inset-y-0 bg-[repeating-linear-gradient(45deg,transparent,transparent_3px,rgba(255,255,255,0.04)_3px,rgba(255,255,255,0.04)_6px)]"
                     style="left: {{ $idle['future']['left'] }}%; width: {{ $idle['future']['width'] }}%"></div>
            @endif
            @foreach ($idle['segments'] as $segment)
                <div class="absolute inset-y-0 bg-zinc-500/80 hover:bg-zinc-400"
                     style="left: {{ $segment['left'] }}%; width: {{ $segment['width'] }}%"
                     title="{{ $segment['label'] }}"></div>
            @endforeach
            @foreach ($idle['running'] ?? [] as $running)
                <div class="absolute inset-y-0" data-running-segment
                     style="left: {{ $running['left'] }}%; width: {{ $running['width'] }}%; background-image: repeating-linear-gradient(45deg, rgba(88,166,255,0.9) 0 3px, rgba(88,166,255,0.25) 3px 6px);"
                     title="{{ $running['label'] }}"></div>
            @endforeach
        </div>
        <div class="relative mt-1 h-3 text-[9px] text-faint">
            @foreach ($idle['ticks'] as $tick)
                <span class="absolute {{ $loop->first ? '' : ($loop->last ? '-translate-x-full' : '-translate-x-1/2') }}" style="left: {{ $tick['left'] }}%">{{ $tick['hour'] }}</span>
            @endforeach
        </div>
    </div>

    <div class="mt-3 border-t border-white/10 pt-3" x-data="{ pieMode: 'total', clockHover: null }">
        <div class="mb-2 flex justify-center">
            <div class="inline-flex rounded-full border border-white/10 bg-canvas/60 p-0.5 text-[10px] font-semibold uppercase tracking-wider" role="group" aria-label="Chart mode">
                <button type="button" class="rounded-full px-2.5 py-0.5 transition" @click="pieMode = 'total'"
                        :class="pieMode === 'total' ? 'bg-link/20 text-link' : 'text-muted hover:text-body'" :aria-pressed="pieMode === 'total'">Total</button>
                <button type="button" class="rounded-full px-2.5 py-0.5 transition" @click="pieMode = 'clock'"
                        :class="pieMode === 'clock' ? 'bg-link/20 text-link' : 'text-muted hover:text-body'" :aria-pressed="pieMode === 'clock'">Clock</button>
            </div>
        </div>
        <div class="mx-auto flex h-48 w-48 items-center justify-center sm:h-52 sm:w-52" x-show="pieMode === 'clock'" x-cloak data-clock="{{ $day['date'] }}">
            <svg viewBox="0 0 100 100" class="h-full w-full overflow-visible" role="img" aria-label="Sessions on the clock for {{ $day['label'] }} (8AM–8PM)">
                <circle cx="50" cy="50" r="36" fill="none" stroke="currentColor" stroke-width="9" class="text-white/5"/>
                @foreach (\App\Livewire\TaskLogBoard::clockTicks() as $tick)
                    <line x1="{{ $tick['ix'] }}" y1="{{ $tick['iy'] }}" x2="{{ 50 + ($tick['ix'] - 50) * 0.93 }}" y2="{{ 50 + ($tick['iy'] - 50) * 0.93 }}" stroke="currentColor" stroke-width="0.6" class="text-white/25"/>
                    <text x="{{ $tick['x'] }}" y="{{ $tick['y'] }}" text-anchor="middle" dominant-baseline="central" font-size="5.5" fill="currentColor" class="text-muted tabular-nums">{{ $tick['label'] }}</text>
                @endforeach
                @foreach ($day['clockArcs'] as $arc)
                    @php($tip = $arc['title'].' ('.$arc['started'].'–'.$arc['finished'].')')
                    @if ($arc['full'])
                        <circle cx="50" cy="50" r="36" fill="none" stroke="{{ $arc['color'] }}" stroke-width="9"
                    @else
                        <path d="{{ $arc['path'] }}" fill="none" stroke="{{ $arc['color'] }}" stroke-width="9" stroke-linecap="butt"
                    @endif
                          data-clock-arc
                          class="cursor-pointer transition-opacity duration-150"
                          @mouseenter="hoverTitle = @js($arc['title']); clockHover = @js($tip)"
                          @mouseleave="hoverTitle = null; clockHover = null"
                          @click="clockHover = @js($tip); focusTitle(@js($arc['title']))"
                          :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($arc['title']) }"><title>{{ $tip }}</title></{{ $arc['full'] ? 'circle' : 'path' }}>
                @endforeach
                <text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-size="5" fill="currentColor" class="text-faint uppercase">{{ $day['clockArcs'] === [] ? 'no sessions' : '8am – 8pm' }}</text>
            </svg>
        </div>
        <p class="h-4 truncate text-center text-[11px] text-muted" x-show="pieMode === 'clock'" x-cloak x-text="clockHover ?? ''"></p>
        <div class="mx-auto flex h-48 w-48 items-center justify-center sm:h-52 sm:w-52" x-show="pieMode === 'total'">
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
