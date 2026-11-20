<div>
@if ($viewingBook)
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
         wire:keydown.escape.window="closeBook"
         role="dialog" aria-modal="true" aria-labelledby="stats-book-title" data-book-modal>
        <div class="absolute inset-0 bg-black/60" wire:click="closeBook" aria-hidden="true"></div>

        <div class="relative z-10 flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-lg border border-white/10 bg-sidebar shadow-xl sm:rounded-lg">
            <div class="flex items-start justify-between gap-3 px-4 pb-3 pt-4 sm:px-5 sm:pt-5">
                <div class="min-w-0">
                    <h2 id="stats-book-title" class="text-base font-semibold text-body">
                        @include('livewire.partials.read-item-name', [
                            'title' => $viewingBook['title'],
                            'startedToday' => $viewingBook['startedToday'] ?? false,
                            'startedLabel' => $viewingBook['startedLabel'] ?? 'Started today',
                            'finished' => $viewingBook['finished'] ?? false,
                        ])
                    </h2>
                </div>
                <button type="button" wire:click="closeBook"
                        class="rounded px-1 text-lg leading-none text-muted hover:text-body" aria-label="Close">×</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-4 sm:px-5 sm:pb-5">
                <dl class="grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-2 text-sm">
                    <dt class="text-[10px] uppercase tracking-[0.14em] text-muted">Started</dt>
                    <dd class="text-body" data-book-started>
                        @if ($viewingBook['startedOn'])
                            {{ $viewingBook['startedOn'] }}
                            @if ($viewingBook['startedAgo'])
                                <span class="text-muted"> · {{ $viewingBook['startedAgo'] }}</span>
                            @endif
                        @else
                            <span class="text-muted">Not started yet</span>
                        @endif
                    </dd>
                </dl>

                <div class="mt-5" data-book-heatmap>
                    <div class="mb-2 flex items-center justify-center gap-0.5 text-xs text-muted">
                        <button type="button" wire:click="previousHeatmapMonth"
                                class="rounded px-1.5 py-0.5 leading-none hover:bg-white/5 hover:text-body"
                                aria-label="Previous month">‹</button>
                        <span class="min-w-[7rem] px-1 text-center tabular-nums" data-heatmap-month>{{ $viewingBook['heatmap']['label'] }}</span>
                        @unless ($viewingBook['heatmap']['isCurrentMonth'])
                            <button type="button" wire:click="nextHeatmapMonth"
                                    class="rounded px-1.5 py-0.5 leading-none hover:bg-white/5 hover:text-body"
                                    aria-label="Next month">›</button>
                        @endunless
                    </div>
                    <div class="grid grid-cols-7 gap-px text-center text-[10px] uppercase tracking-wide text-faint">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
                            <span class="py-0.5">{{ $label[0] }}</span>
                        @endforeach
                    </div>
                    <div class="mt-0.5 grid grid-cols-7 gap-1">
                        @foreach ($viewingBook['heatmap']['weeks'] as $week)
                            @foreach ($week as $day)
                                @php
                                    $max = $viewingBook['heatmap']['maxDay'];
                                    $alpha = $day['minutes'] > 0 ? 0.22 + 0.78 * ($day['minutes'] / $max) : 0;
                                @endphp
                                <div @class([
                                        'relative flex h-5 items-center justify-center rounded-sm py-0.5 text-[10px] tabular-nums',
                                        'text-faint' => ! $day['inMonth'],
                                        'text-muted' => $day['inMonth'] && $day['minutes'] === 0 && ! $day['isToday'],
                                        'font-semibold text-body' => $day['isToday'] || ($day['inMonth'] && $day['minutes'] > 0),
                                        'ring-1 ring-white/25' => $day['inMonth'] && $day['inWeek'],
                                    ])
                                    @if ($day['inMonth'] && $day['minutes'] > 0) style="background: {{ \App\Livewire\ReadBookStatsModal::hexAlpha($viewingBook['heatmap']['color'], $alpha) }};" @endif
                                    data-heatmap-day="{{ $day['ymd'] }}"
                                    data-heatmap-minutes="{{ $day['minutes'] }}"
                                    title="{{ $day['inMonth'] ? ($day['minutes'] > 0 ? $day['day'].' · '.\App\Livewire\ReadStats::formatMinutes($day['minutes']) : $day['day']) : '' }}">
                                    @if ($day['inMonth'])
                                        {{ $day['day'] }}
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-2 text-sm">
                    <dt class="text-[10px] uppercase tracking-[0.14em] text-muted">Last read</dt>
                    <dd class="text-body" data-book-last-read>
                        @if ($viewingBook['lastReadOn'])
                            {{ $viewingBook['lastReadOn'] }}
                            @if ($viewingBook['lastReadRunning'])
                                <span class="ml-1 rounded bg-mark/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mark">running</span>
                            @endif
                            @if ($viewingBook['lastReadAgo'])
                                <span class="text-muted"> · {{ $viewingBook['lastReadAgo'] }}</span>
                            @endif
                        @else
                            <span class="text-muted">No sessions yet</span>
                        @endif
                    </dd>

                    <dt class="text-[10px] uppercase tracking-[0.14em] text-muted">Total time</dt>
                    <dd class="tabular-nums text-body" data-book-total>{{ \App\Livewire\ReadStats::formatMinutes($viewingBook['totalMinutes']) }}</dd>

                    <dt class="text-[10px] uppercase tracking-[0.14em] text-muted">{{ $viewingBook['weekLabel'] }}</dt>
                    <dd class="tabular-nums text-body" data-book-week>{{ \App\Livewire\ReadStats::formatMinutes($viewingBook['weekMinutes']) }}</dd>

                    @if ($viewingBook['finished'])
                        <dt class="text-[10px] uppercase tracking-[0.14em] text-muted">Status</dt>
                        <dd class="text-body" data-book-finished>
                            @include('livewire.partials.read-item-name', ['title' => 'Finished', 'startedToday' => false, 'finished' => true])
                        </dd>
                    @endif
                </dl>

                <section class="mt-6 border-t border-white/10 pt-4" data-book-sessions>
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Sessions</h3>
                    <ul class="max-h-52 space-y-1 overflow-y-auto sm:max-h-64">
                        @forelse ($viewingBook['sessions'] as $session)
                            <li class="rounded border border-white/10 bg-panel px-3 py-2 text-sm" wire:key="modal-session-{{ $session['id'] }}" data-session-row="{{ $session['id'] }}">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                    <span class="text-body">
                                        {{ $session['started'] }}
                                        @if ($session['running'])
                                            <span class="ml-1 rounded bg-mark/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mark">running</span>
                                        @elseif ($session['finished'])
                                            <span class="text-muted">→ {{ $session['finished'] }}@if ($session['nextDay'])<sup class="ml-0.5 text-[10px] text-faint" title="Next day">+1</sup>@endif</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 font-semibold tabular-nums text-link">{{ \App\Livewire\ReadStats::formatMinutes($session['minutes']) }}</span>
                                </div>
                            </li>
                        @empty
                            <li class="px-1 py-3 text-sm text-muted">No sessions yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </div>
@endif
</div>
