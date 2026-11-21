<div class="mx-auto max-w-4xl px-3 py-6 sm:px-4">
    <div class="mb-6 flex flex-nowrap items-center gap-1 sm:gap-2">
        <h1 class="shrink-0 text-xl font-bold text-body">Stats</h1>

        <div class="flex min-w-0 flex-1 justify-center">
            @include('livewire.partials.week-cycler', ['todayAction' => 'goToCurrentWeek', 'hideFuture' => true])
        </div>

        <a href="{{ route('read') }}" class="shrink-0 whitespace-nowrap text-sm text-muted no-underline hover:text-body">&larr; Books</a>
    </div>

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">{{ $isCurrentWeek ? 'This week, hours per day' : 'Hours per day' }}</h2>

    <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted">
        @foreach ($types as $t)
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $colors[$t] }}"></span>
                {{ \App\Livewire\ReadStats::typeLabel($t) }}
            </span>
        @endforeach
    </div>

    <div class="mb-10 rounded border border-white/10 bg-sidebar p-4">
        <div class="grid grid-cols-7 gap-2 sm:gap-4">
            @foreach ($days as $dayIndex => $day)
                <div class="flex flex-col items-center">
                    <div class="flex h-48 w-full items-end justify-center gap-0.5 sm:gap-1">
                        @foreach ($types as $t)
                            @php $m = $day['minutes'][$t]; @endphp
                            <div class="flex h-full flex-1 flex-col justify-end">
                                <div class="group relative w-full rounded-t-sm" style="height: {{ $m > 0 ? max(2, round($m / $maxDay * 100, 2)) : 0 }}%; background: {{ $colors[$t] }}" @if ($m > 0) tabindex="0" @endif>
                                    @if ($m > 0)
                                        <div @class([
                                                'pointer-events-none absolute bottom-full z-30 mb-2 hidden w-max max-w-[16rem] rounded border border-white/15 bg-panel px-3 py-2 text-left text-xs shadow-xl group-hover:block group-focus:block',
                                                'left-0' => $dayIndex < 2,
                                                'right-0' => $dayIndex > 4,
                                                'left-1/2 -translate-x-1/2' => $dayIndex >= 2 && $dayIndex <= 4,
                                            ])>
                                            <ul class="list-disc space-y-0.5 pl-4 text-muted">
                                                @foreach ($day['books'][$t] as $b)
                                                    <li><span class="text-body">@include('livewire.partials.read-item-name', ['title' => $b['title'], 'startedToday' => $b['startedToday'] ?? false, 'startedLabel' => $b['startedLabel'] ?? 'Started today', 'finished' => $b['finished'] ?? false])</span> · {{ \App\Livewire\ReadStats::formatMinutes($b['minutes']) }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 text-xs {{ ($day['ymd'] ?? '') === $todayDate ? 'font-semibold text-body' : 'text-muted' }}">{{ $day['label'] }}</div>
                    @php $dayTotal = array_sum(array_intersect_key($day['minutes'], array_flip($types))); @endphp
                    <div class="text-[10px] tabular-nums text-faint">{{ $dayTotal > 0 ? \App\Livewire\ReadStats::formatMinutes($dayTotal) : '' }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">{{ $isCurrentWeek ? 'This week, share by type' : 'Share by type' }}</h2>

    <div class="flex flex-col items-center gap-6 rounded border border-white/10 bg-sidebar p-6 sm:flex-row sm:justify-center sm:gap-10">
        @if ($pieGradient)
            <div class="h-48 w-48 shrink-0 rounded-full" style="background: {{ $pieGradient }}"></div>
        @else
            <div class="flex h-48 w-48 shrink-0 items-center justify-center rounded-full border border-dashed border-white/15 text-sm text-muted">No reading yet</div>
        @endif
        <ul class="space-y-2 text-sm">
            @foreach ($types as $t)
                <li class="flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ $colors[$t] }}"></span>
                    <span class="w-20 text-body">{{ \App\Livewire\ReadStats::typeLabel($t) }}</span>
                    <span class="w-12 text-right font-semibold tabular-nums text-body">{{ $weekTotal > 0 ? round($totals[$t] / $weekTotal * 100) : 0 }}%</span>
                    <span class="tabular-nums text-muted">{{ \App\Livewire\ReadStats::formatMinutes($totals[$t]) }}</span>
                </li>
            @endforeach
            <li class="border-t border-white/10 pt-2 text-muted">Total {{ \App\Livewire\ReadStats::formatMinutes($weekTotal) }}</li>
        </ul>
    </div>

    <h2 class="mb-3 mt-10 text-sm font-semibold uppercase tracking-[0.16em] text-muted">{{ $isCurrentWeek ? 'Books read this week' : 'Books read' }}</h2>

    <ul class="divide-y divide-white/10 rounded border border-white/10 bg-sidebar">
        @forelse ($books as $book)
            <li>
                <button type="button" wire:click="openBook({{ $book['id'] }})"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-white/5 focus-visible:bg-white/5 focus-visible:outline-none"
                        data-open-book="{{ $book['id'] }}">
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $colors[$book['type'] && isset($colors[$book['type']]) ? $book['type'] : '—'] }}"></span>
                    <span class="min-w-0 flex-1 text-body">@include('livewire.partials.read-item-name', ['title' => $book['title'], 'startedToday' => $book['startedToday'] ?? false, 'startedLabel' => $book['startedLabel'] ?? 'Started today', 'finished' => $book['finished'] ?? false, 'truncate' => true])</span>
                    <span class="shrink-0 font-semibold tabular-nums text-body">{{ \App\Livewire\ReadStats::formatMinutes($book['minutes']) }}</span>
                </button>
            </li>
        @empty
            <li class="px-4 py-4 text-sm text-muted">{{ $isCurrentWeek ? 'No books read yet this week.' : 'No books read this week.' }}</li>
        @endforelse
    </ul>

    <h2 class="mb-3 mt-10 text-sm font-semibold uppercase tracking-[0.16em] text-muted">{{ $isCurrentWeek ? 'Articles read this week' : 'Articles read' }}</h2>

    <ul class="divide-y divide-white/10 rounded border border-white/10 bg-sidebar">
        @forelse ($articles as $article)
            <li>
                <button type="button" wire:click="openBook({{ $article['id'] }})"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-white/5 focus-visible:bg-white/5 focus-visible:outline-none"
                        data-open-book="{{ $article['id'] }}">
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $colors['articles'] }}"></span>
                    <span class="min-w-0 flex-1 text-body">@include('livewire.partials.read-item-name', ['title' => $article['title'], 'startedToday' => $article['startedToday'] ?? false, 'startedLabel' => $article['startedLabel'] ?? 'Started today', 'finished' => $article['finished'] ?? false, 'truncate' => true])</span>
                    <span class="shrink-0 font-semibold tabular-nums text-body">{{ \App\Livewire\ReadStats::formatMinutes($article['minutes']) }}</span>
                </button>
            </li>
        @empty
            <li class="px-4 py-4 text-sm text-muted">{{ $isCurrentWeek ? 'No articles read yet this week.' : 'No articles read this week.' }}</li>
        @endforelse
    </ul>

    <livewire:read-book-stats-modal :week="$weekKey" />
</div>
