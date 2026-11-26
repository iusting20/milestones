<section wire:key="day-{{ $day['date'] }}"
         @class([
             'flex h-full min-w-56 flex-col rounded-lg border px-2 py-2',
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

    <div class="mt-auto shrink-0">
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

        <div class="mt-3 border-t border-white/10 pt-3"
             x-data="{ pieMode: 'total', clockHover: null, night: false }"
             @pie-mode-all.window="pieMode = $event.detail">
            <div class="mb-2 flex h-6 items-center justify-center gap-1.5">
                <div class="inline-flex rounded-full border border-white/10 bg-canvas/60 p-0.5 text-[10px] font-semibold uppercase tracking-wider" role="group" aria-label="Chart mode">
                    <button type="button" class="rounded-full px-2.5 py-0.5 transition" @click="pieMode = 'total'" @dblclick.stop="$dispatch('pie-mode-all', 'total')"
                            :class="pieMode === 'total' ? 'bg-link/20 text-link' : 'text-muted hover:text-body'" :aria-pressed="pieMode === 'total'" title="Double-click to set all days">Total</button>
                    <button type="button" class="rounded-full px-2.5 py-0.5 transition" @click="pieMode = 'clock'" @dblclick.stop="$dispatch('pie-mode-all', 'clock')"
                            :class="pieMode === 'clock' ? 'bg-link/20 text-link' : 'text-muted hover:text-body'" :aria-pressed="pieMode === 'clock'" title="Double-click to set all days">Clock</button>
                </div>
                @if ($day['nightClockArcs'] !== [])
                    <div class="size-6 shrink-0">
                        <button type="button"
                                x-show="pieMode === 'clock'"
                                x-cloak
                                class="inline-flex aspect-square size-6 items-center justify-center rounded-full border border-white/10 bg-canvas/60 p-0 leading-none text-muted transition hover:text-body"
                                @click="night = !night"
                                :class="night ? 'bg-link/20 text-link' : ''"
                                :aria-pressed="night"
                                :aria-label="night ? 'Night dial, 5pm to midnight' : 'Day dial, 8am to 5pm'">
                            <svg x-show="!night" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="12" r="3.2"/>
                                <path stroke-linecap="round" d="M12 3.5v1.8M12 18.7v1.8M3.5 12h1.8M18.7 12h1.8M6 6l1.3 1.3M16.7 16.7 18 18M18 6l-1.3 1.3M7.3 16.7 6 18"/>
                            </svg>
                            <svg x-show="night" x-cloak viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
                                <path d="M15.5 3.2a8.2 8.2 0 1 0 5.3 12.4A7 7 0 0 1 15.5 3.2z"/>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>
            <div class="relative mx-auto h-48 w-48 sm:h-52 sm:w-52">
                <div class="absolute inset-0 flex items-center justify-center" x-show="pieMode === 'clock'" x-cloak data-clock="{{ $day['date'] }}">
                    <svg viewBox="0 0 100 100" class="h-full w-full overflow-visible" role="img" :aria-label="night ? 'Sessions on the clock for {{ $day['label'] }} (5PM–12AM)' : 'Sessions on the clock for {{ $day['label'] }} (8AM–5PM)'">
                        <g x-show="!night">
                            <path d="{{ \App\Livewire\TaskLogBoard::clockTrackPath(8, 17) }}" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="butt" class="text-white/5" data-day-track/>
                            @foreach (\App\Livewire\TaskLogBoard::clockTicks() as $tick)
                                @if (in_array($tick['label'], ['8', '9', '10', '11', '12', '1', '2', '3', '4', '5'], true))
                                    <line x1="{{ $tick['ix'] }}" y1="{{ $tick['iy'] }}" x2="{{ 50 + ($tick['ix'] - 50) * 0.93 }}" y2="{{ 50 + ($tick['iy'] - 50) * 0.93 }}" stroke="currentColor" stroke-width="0.6" class="text-white/25"/>
                                    <text x="{{ $tick['x'] }}" y="{{ $tick['y'] }}" text-anchor="middle" dominant-baseline="central" font-size="5.5" fill="currentColor" class="text-muted tabular-nums">{{ $tick['label'] }}</text>
                                @endif
                            @endforeach
                            @foreach ($day['clockArcs'] as $arc)
                                @php($tip = $arc['title'].' ('.$arc['started'].'–'.$arc['finished'].')')
                                <path d="{{ $arc['path'] }}" fill="none" stroke="{{ $arc['color'] }}" stroke-width="{{ $arc['stroke'] }}" stroke-linecap="butt"
                                      @if ($arc['wip']) data-wip-arc @endif
                                      data-clock-arc
                                      class="cursor-pointer transition-opacity duration-150"
                                      @mouseenter="hoverTitle = @js($arc['title']); clockHover = @js($tip)"
                                      @mouseleave="hoverTitle = null; clockHover = null"
                                      @click="clockHover = @js($tip); focusTitle(@js($arc['title']))"
                                      :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($arc['title']) }"><title>{{ $tip }}</title></path>
                            @endforeach
                            <text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-size="5" fill="currentColor" class="text-faint uppercase">{{ $day['clockArcs'] === [] ? 'no sessions' : '8am - 5pm' }}</text>
                        </g>
                        <g x-show="night" x-cloak>
                            <path d="{{ \App\Livewire\TaskLogBoard::clockTrackPath(17, 24) }}" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="butt" class="text-white/5" data-night-track/>
                            @foreach (\App\Livewire\TaskLogBoard::clockTicks() as $tick)
                                @if (in_array($tick['label'], ['5', '6', '7', '8', '9', '10', '11', '12'], true))
                                    <line x1="{{ $tick['ix'] }}" y1="{{ $tick['iy'] }}" x2="{{ 50 + ($tick['ix'] - 50) * 0.93 }}" y2="{{ 50 + ($tick['iy'] - 50) * 0.93 }}" stroke="currentColor" stroke-width="0.6" class="text-white/25"/>
                                    <text x="{{ $tick['x'] }}" y="{{ $tick['y'] }}" text-anchor="middle" dominant-baseline="central" font-size="5.5" fill="currentColor" class="text-muted tabular-nums">{{ $tick['label'] }}</text>
                                @endif
                            @endforeach
                            @foreach ($day['nightClockArcs'] as $arc)
                                @php($tip = $arc['title'].' ('.$arc['started'].'–'.$arc['finished'].')')
                                <path d="{{ $arc['path'] }}" fill="none" stroke="{{ $arc['color'] }}" stroke-width="{{ $arc['stroke'] }}" stroke-linecap="butt"
                                      @if ($arc['wip']) data-wip-arc @endif
                                      data-night-arc
                                      class="cursor-pointer transition-opacity duration-150"
                                      @mouseenter="hoverTitle = @js($arc['title']); clockHover = @js($tip)"
                                      @mouseleave="hoverTitle = null; clockHover = null"
                                      @click="clockHover = @js($tip); focusTitle(@js($arc['title']))"
                                      :class="{ 'opacity-20': hoverTitle !== null && hoverTitle !== @js($arc['title']) }"><title>{{ $tip }}</title></path>
                            @endforeach
                            <text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-size="5" fill="currentColor" class="text-faint uppercase">{{ $day['nightClockArcs'] === [] ? 'no sessions' : '5pm - 12am' }}</text>
                        </g>
                    </svg>
                </div>
                <div class="absolute inset-0 flex items-center justify-center" x-show="pieMode === 'total'">
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
            </div>
            <p class="h-4 truncate text-center text-[11px] text-muted" x-text="pieMode === 'clock' ? (clockHover ?? '') : ''"></p>
            <p class="mt-2 h-8 whitespace-nowrap text-center text-[22px] font-semibold leading-8 tabular-nums text-muted">
                Total: {{ $day['totalDuration'] }}
            </p>
        </div>
    </div>
</section>
