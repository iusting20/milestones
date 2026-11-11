@php
    use App\Livewire\JobApplications as JA;
    $kindColors = [
        'added' => '#58a6ff', 'applied' => '#3fb950', 'follow-up' => '#ffe600', 'rejected' => '#da3633',
        'closed' => '#da3633', 'removed' => '#8b8b8b', 'status' => '#d2a8ff', 'interview' => '#3fb950', 'offer' => '#3fb950', 'note' => '#8b8b8b',
    ];
@endphp
<div class="mx-auto max-w-4xl px-3 py-6 sm:px-4">
    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="text-xl font-bold text-body">Applications</h1>
        <span class="text-xs text-muted">
            @if ($lastSync)
                Last synced {{ $lastSync->synced_at->copy()->timezone($tz)->format('j M Y, H:i') }} ({{ $tz }})
            @else
                Never synced
            @endif
        </span>
    </div>
    <p class="mb-6 text-sm text-muted">{{ $apps->count() }} roles tracked · {{ $statusCounts['Applied'] ?? 0 }} applied · {{ $followUps->count() }} follow-ups ahead</p>

    {{-- Status --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">By status</h2>
    <div class="mb-10 space-y-2 rounded border border-white/10 bg-sidebar p-4">
        @forelse ($statusCounts as $status => $n)
            <div class="flex items-center gap-3 text-sm">
                <span class="w-36 shrink-0 truncate text-body">{{ $status }}</span>
                <div class="h-4 flex-1 rounded-sm bg-white/5">
                    <div class="h-full rounded-sm" style="width: {{ round($n / $maxStatus * 100, 2) }}%; background: {{ JA::statusColor($status) }}"></div>
                </div>
                <span class="w-8 text-right font-semibold tabular-nums text-body">{{ $n }}</span>
            </div>
        @empty
            <p class="text-sm text-muted">No applications synced yet.</p>
        @endforelse
    </div>

    {{-- Weekly --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Per week</h2>
    <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted">
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:#58a6ff"></span>Added</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:#3fb950"></span>Applied</span>
    </div>
    <div class="mb-10 overflow-x-auto rounded border border-white/10 bg-sidebar p-4">
        <div class="flex min-w-max gap-2 sm:gap-4" style="min-width: 100%">
            @foreach ($weekly as $w)
                <div class="flex min-w-[2.5rem] flex-1 flex-col items-center">
                    <div class="flex h-40 w-full items-end justify-center gap-0.5">
                        @foreach (['added' => '#58a6ff', 'applied' => '#3fb950'] as $series => $color)
                            <div class="flex h-full flex-1 flex-col justify-end">
                                @if ($w[$series] > 0)
                                    <div class="text-center text-[10px] tabular-nums text-muted">{{ $w[$series] }}</div>
                                @endif
                                <div class="w-full rounded-t-sm" title="{{ ucfirst($series) }}: {{ $w[$series] }}" style="height: {{ $w[$series] > 0 ? max(2, round($w[$series] / $maxWeek * 85, 2)) : 0 }}%; background: {{ $color }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 whitespace-nowrap text-[10px] text-muted">{{ $w['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Contract vs permanent --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Contract vs permanent</h2>
    <div class="mb-10 flex flex-col items-center gap-6 rounded border border-white/10 bg-sidebar p-6 sm:flex-row sm:justify-center sm:gap-10">
        @if ($typeGradient)
            <div class="h-40 w-40 shrink-0 rounded-full" style="background: {{ $typeGradient }}"></div>
        @endif
        <ul class="space-y-2 text-sm">
            @foreach ($typeCounts as $t => $n)
                <li class="flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ JA::TYPE_COLORS[$t] ?? '#8b8b8b' }}"></span>
                    <span class="w-24 text-body">{{ $t }}</span>
                    <span class="w-10 text-right font-semibold tabular-nums text-body">{{ $n }}</span>
                    <span class="tabular-nums text-muted">{{ $typeTotal ? round($n / $typeTotal * 100) : 0 }}%</span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Day rates --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Contract day rates</h2>
    <div class="mb-10 rounded border border-white/10 bg-sidebar p-4">
        @if ($rateStats)
            <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <span class="text-muted">Lowest <span class="font-semibold text-body">£{{ $rateStats['low'] }}</span></span>
                <span class="text-muted">Median <span class="font-semibold text-body">£{{ $rateStats['median'] }}</span></span>
                <span class="text-muted">Highest <span class="font-semibold text-body">£{{ $rateStats['high'] }}</span></span>
                <span class="text-muted">{{ count($rates) }} with a rate{{ $unparsedRates ? ', '.$unparsedRates.' not stated' : '' }}</span>
            </div>
            <div class="space-y-2">
                @foreach ($rates as $r)
                    @php
                        $span = $axisMax - $axisMin;
                        $left = ($r['min'] - $axisMin) / $span * 100;
                        $width = max(1.2, ($r['max'] - $r['min']) / $span * 100);
                    @endphp
                    <div class="grid grid-cols-[minmax(0,9rem)_1fr] items-center gap-3 text-xs sm:grid-cols-[minmax(0,14rem)_1fr]">
                        <span class="truncate text-muted" title="{{ $r['app']->label() }}">{{ $r['app']->company }}</span>
                        <div class="relative h-4 rounded-sm bg-white/5">
                            <div class="absolute top-0 h-full rounded-sm" style="left: {{ round($left, 2) }}%; width: {{ round($width, 2) }}%; background: {{ JA::statusColor($r['app']->status) }}"></div>
                            <span class="absolute top-0 whitespace-nowrap pl-1 text-[10px] leading-4 text-body" style="left: {{ round(min($left + $width, 80), 2) }}%">£{{ $r['min'] }}{{ $r['max'] !== $r['min'] ? '–'.$r['max'] : '' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 grid grid-cols-[minmax(0,9rem)_1fr] gap-3 text-[10px] text-faint sm:grid-cols-[minmax(0,14rem)_1fr]">
                <span></span>
                <div class="flex justify-between tabular-nums"><span>£{{ $axisMin }}</span><span>£{{ $axisMax }}</span></div>
            </div>
            <p class="mt-3 text-[11px] text-faint">Bar colour = status.</p>
        @else
            <p class="text-sm text-muted">No contract rates to show.</p>
        @endif
    </div>

    {{-- Follow-ups --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Upcoming follow-ups</h2>
    <ul class="mb-10 divide-y divide-white/10 rounded border border-white/10 bg-sidebar">
        @forelse ($followUps as $a)
            <li class="px-4 py-3 text-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-semibold text-body">{{ $a->label() }}</span>
                    <span class="tabular-nums {{ $a->follow_up_date->isSameDay($today) ? 'font-semibold text-mark' : 'text-muted' }}">{{ $a->follow_up_date->format('D j M') }}</span>
                </div>
                @if ($a->next_step)<div class="mt-0.5 text-muted">{{ $a->next_step }}</div>@endif
                <div class="mt-0.5 text-xs text-faint">{{ collect([$a->source, $a->contact])->filter()->implode(' · ') }}</div>
            </li>
        @empty
            <li class="px-4 py-4 text-sm text-muted">No follow-ups scheduled.</li>
        @endforelse
    </ul>

    {{-- Progress log --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Progress log</h2>
    <ol class="mb-10 rounded border border-white/10 bg-sidebar">
        @php $lastDate = null; @endphp
        @forelse ($log as $entry)
            @if ($entry['date'] !== $lastDate)
                <li class="border-t border-white/10 px-4 pb-1 pt-3 text-xs font-semibold uppercase tracking-[0.12em] text-muted first:border-t-0">{{ \Carbon\Carbon::parse($entry['date'])->format('D j M Y') }}</li>
                @php $lastDate = $entry['date']; @endphp
            @endif
            <li class="flex gap-3 px-4 py-1.5 text-sm">
                <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full" style="background: {{ $kindColors[$entry['kind']] ?? '#8b8b8b' }}"></span>
                <div class="min-w-0">
                    <span class="font-semibold text-body">{{ $entry['title'] }}</span>
                    @if ($entry['app'])<span class="text-body"> · {{ $entry['app']->label() }}</span>@endif
                    @if ($entry['detail'])<div class="text-xs text-muted">{{ $entry['detail'] }}</div>@endif
                </div>
            </li>
        @empty
            <li class="px-4 py-4 text-sm text-muted">Nothing logged yet.</li>
        @endforelse
    </ol>

    {{-- All rows --}}
    <details class="mb-10 rounded border border-white/10 bg-sidebar">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">All applications ({{ $apps->count() }})</summary>
        <ul class="divide-y divide-white/10 border-t border-white/10">
            @foreach ($apps as $a)
                <li class="px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-semibold text-body">
                            @if ($a->link)<a href="{{ $a->link }}" target="_blank" rel="noopener noreferrer" class="text-link no-underline hover:underline">{{ $a->label() }}</a>@else{{ $a->label() }}@endif
                        </span>
                        <span class="flex items-center gap-1.5 text-xs text-muted"><span class="inline-block h-2 w-2 rounded-full" style="background: {{ JA::statusColor($a->status) }}"></span>{{ $a->status }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-muted">{{ collect([$a->type, $a->rate, $a->ir35 && $a->ir35 !== 'N/A' ? 'IR35 '.$a->ir35 : null, $a->location])->filter()->implode(' · ') }}</div>
                    <div class="text-xs text-faint">{{ collect([$a->date_added ? 'Added '.$a->date_added->format('j M') : null, $a->date_applied ? 'Applied '.$a->date_applied->format('j M') : null, $a->source, $a->contact])->filter()->implode(' · ') }}</div>
                </li>
            @endforeach
        </ul>
    </details>
</div>
