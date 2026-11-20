<div>
@if ($viewingBook)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
         wire:keydown.escape.window="closeBook"
         wire:click.self="closeBook"
         role="dialog" aria-modal="true" aria-labelledby="stats-book-title" data-book-modal>
        <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded border border-white/10 bg-panel p-4 shadow-xl">
            <h2 id="stats-book-title" class="text-2xl font-bold text-body">
                @include('livewire.partials.read-item-name', [
                    'title' => $viewingBook['title'],
                    'startedToday' => $viewingBook['startedToday'] ?? false,
                    'startedLabel' => $viewingBook['startedLabel'] ?? 'Started today',
                    'finished' => $viewingBook['finished'] ?? false,
                ])
            </h2>

            <div class="mt-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-stretch">
                    @unless ($viewingBook['isArticle'] ?? false)
                        <div class="sm:w-28 sm:shrink-0">
                            <div class="h-full rounded border border-white/10 bg-sidebar px-3 py-2">
                                <div class="text-[10px] uppercase tracking-wide text-muted">Type</div>
                                <div class="text-lg font-semibold">{{ $viewingBook['type'] ?: '—' }}</div>
                            </div>
                        </div>
                    @endunless
                    <div class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wide text-muted">Total time read</div>
                            <div class="text-lg font-semibold tabular-nums text-body" data-book-total>{{ \App\Livewire\ReadStats::formatMinutes($viewingBook['totalMinutes']) }}</div>
                        </div>
                        <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wide text-muted">{{ $viewingBook['weekLabel'] }}</div>
                            <div class="text-sm font-semibold text-body">{{ $viewingBook['weekDays'] !== '' ? $viewingBook['weekDays'] : '—' }}</div>
                            <div class="text-lg font-semibold tabular-nums text-body" data-book-week>{{ \App\Livewire\ReadStats::formatMinutes($viewingBook['weekMinutes']) }}</div>
                        </div>
                        <div class="rounded border border-white/10 bg-sidebar px-3 py-2">
                            <div class="text-[10px] uppercase tracking-wide text-muted">Last read</div>
                            <div class="text-sm font-semibold text-body" data-book-last-read>
                                @if ($viewingBook['lastReadOn'])
                                    <span title="{{ $viewingBook['lastReadOn'] }}">{{ $viewingBook['lastReadAgo'] ?: $viewingBook['lastReadOn'] }}</span>
                                    @if ($viewingBook['lastReadRunning'])
                                        <span class="ml-1 rounded bg-mark/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mark">running</span>
                                    @endif
                                    <div class="mt-0.5 text-xs font-normal text-muted">{{ $viewingBook['lastReadOn'] }}</div>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <div class="rounded border border-white/10 bg-sidebar px-3 py-2" data-book-started>
                            <div class="text-[10px] uppercase tracking-wide text-muted">Started</div>
                            @if ($viewingBook['startedOn'])
                                <div class="text-sm font-semibold text-body">{{ $viewingBook['startedOn'] }}</div>
                                @if ($viewingBook['startedAgo'])
                                    <div class="mt-0.5 text-xs text-muted">{{ $viewingBook['startedAgo'] }}</div>
                                @endif
                            @else
                                <div class="text-sm font-semibold text-muted">Not started yet</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($viewingBook['finished'])
                    <div class="mt-2 text-sm text-body" data-book-finished>
                        @include('livewire.partials.read-item-name', ['title' => 'Finished', 'startedToday' => false, 'finished' => true])
                    </div>
                @endif

                <div class="mt-5" data-book-heatmap>
                    <div class="mb-2 flex items-center justify-center gap-0.5 text-xs text-muted">
                        <button type="button" wire:click="previousHeatmapMonth"
                                class="rounded px-1.5 py-0.5 leading-none hover:bg-white/5 hover:text-body"
                                aria-label="Previous month">‹</button>
                        <span class="min-w-[7.5rem] px-1 text-center tabular-nums" data-heatmap-month>{{ $viewingBook['heatmap']['label'] }}</span>
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
                                        'relative flex aspect-square items-center justify-center rounded-sm text-[10px] tabular-nums',
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

                <section class="mt-5" data-book-sessions>
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Sessions</h3>
                    <ul class="space-y-2">
                        @forelse ($viewingBook['sessions'] as $session)
                            <li class="rounded border border-white/10 bg-sidebar px-3 py-2" wire:key="modal-session-{{ $session['id'] }}" data-session-row="{{ $session['id'] }}">
                                <div class="text-sm text-body">
                                    {{ $session['started'] }}
                                    @if ($session['running'])
                                        <span class="ml-1 rounded bg-mark/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mark">running</span>
                                    @elseif ($session['finished'])
                                        <span class="text-muted">→ {{ $session['finished'] }}@if ($session['nextDay'])<sup class="ml-0.5 text-[10px] text-faint" title="Next day">+1</sup>@endif</span>
                                    @endif
                                    <span class="ml-2 font-semibold tabular-nums text-link">{{ \App\Livewire\ReadStats::formatMinutes($session['minutes']) }}</span>
                                </div>
                            </li>
                        @empty
                            <li class="rounded border border-dashed border-white/15 px-3 py-8 text-center text-sm text-muted">No sessions yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            <div class="mt-3 flex justify-end text-xs">
                <button type="button" wire:click="closeBook" class="text-muted hover:text-body">Cancel</button>
            </div>
        </div>
    </div>
@endif
</div>
