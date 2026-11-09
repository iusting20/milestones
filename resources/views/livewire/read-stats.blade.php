<div class="mx-auto max-w-4xl px-3 py-6 sm:px-4">
    <div class="mb-6 flex items-center justify-between gap-2">
        <h1 class="text-xl font-bold text-body">Stats</h1>
        <a href="{{ route('read') }}" class="text-sm text-muted no-underline hover:text-body">&larr; Books</a>
    </div>

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">This week, hours per day</h2>

    <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted">
        @foreach ($types as $t)
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $colors[$t] }}"></span>
                {{ $t === '—' ? 'No type' : 'Type '.$t }}
            </span>
        @endforeach
    </div>

    <div class="mb-10 rounded border border-white/10 bg-sidebar p-4">
        <div class="grid grid-cols-7 gap-2 sm:gap-4">
            @foreach ($days as $day)
                <div class="flex flex-col items-center">
                    <div class="flex h-48 w-full items-end justify-center gap-0.5 sm:gap-1">
                        @foreach ($types as $t)
                            @php $m = $day['minutes'][$t]; @endphp
                            <div class="flex h-full flex-1 flex-col justify-end" title="{{ $day['label'] }} · {{ $t === '—' ? 'No type' : 'Type '.$t }}: {{ \App\Livewire\ReadStats::formatMinutes($m) }}">
                                <div class="w-full rounded-t-sm" style="height: {{ $m > 0 ? max(2, round($m / $maxDay * 100, 2)) : 0 }}%; background: {{ $colors[$t] }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 text-xs {{ $day['label'] === $today ? 'font-semibold text-body' : 'text-muted' }}">{{ $day['label'] }}</div>
                    @php $dayTotal = array_sum(array_intersect_key($day['minutes'], array_flip($types))); @endphp
                    <div class="text-[10px] tabular-nums text-faint">{{ $dayTotal > 0 ? \App\Livewire\ReadStats::formatMinutes($dayTotal) : '' }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">This week, share by type</h2>

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
                    <span class="w-16 text-body">{{ $t === '—' ? 'No type' : 'Type '.$t }}</span>
                    <span class="w-12 text-right font-semibold tabular-nums text-body">{{ $weekTotal > 0 ? round($totals[$t] / $weekTotal * 100) : 0 }}%</span>
                    <span class="tabular-nums text-muted">{{ \App\Livewire\ReadStats::formatMinutes($totals[$t]) }}</span>
                </li>
            @endforeach
            <li class="border-t border-white/10 pt-2 text-muted">Total {{ \App\Livewire\ReadStats::formatMinutes($weekTotal) }}</li>
        </ul>
    </div>
</div>
