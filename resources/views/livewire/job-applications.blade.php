@php
    use App\Livewire\JobApplications as JA;
    $kindColors = [
        'added' => '#39c5cf', 'applied' => '#f0883e', 'follow-up' => '#f778ba', 'rejected' => '#da3633',
        'closed' => '#da3633', 'removed' => '#6e7681', 'status' => '#a371f7', 'interview' => '#f0883e', 'offer' => '#f0883e', 'note' => '#6e7681',
    ];
@endphp
<div class="mx-auto max-w-4xl px-3 py-6 sm:px-4">
    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="text-xl font-bold text-body">Applications</h1>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs text-muted">
                @if ($lastChange)
                    Last change {{ $lastChange->copy()->timezone($tz)->format('j M Y, H:i') }} ({{ $tz }})
                @endif
            </span>
            <button type="button" wire:click="openCoverLetters" data-my-cover-letters
                    class="rounded border border-white/15 bg-panel px-3 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">
                My cover letters{!! $savedCoverLetters->isEmpty() ? '' : ' <span class="tabular-nums text-muted">(' . e($savedCoverLetters->count()) . ')</span>' !!}
            </button>
            <button type="button" wire:click="openCreate"
                    class="rounded border border-white/15 bg-panel px-3 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">
                + Add application
            </button>
        </div>
    </div>
    @if ($flash)
        <p class="mb-2 text-xs text-[#39c5cf]" wire:key="flash">{{ $flash }}</p>
    @endif
    <p class="mb-4 text-sm text-muted">{{ $apps->count() }} roles tracked · {{ $openCount }} open · {{ $closedCount }} closed · {{ $statusCounts['Applied'] ?? 0 }} applied · {{ $followUps->count() }} follow-ups ahead</p>

    <div class="mb-6 flex flex-wrap gap-2" role="group" aria-label="Filter by category">
        @foreach (['all' => 'All'] + $categories as $value => $label)
            <button type="button" wire:click="$set('category', '{{ $value }}')"
                    class="rounded border px-3 py-1.5 text-sm {{ $category === $value ? 'border-[#39c5cf]/70 bg-[#39c5cf]/10 font-medium text-body' : 'border-white/15 text-muted hover:border-white/30 hover:text-body' }}"
                    @if ($category === $value) aria-current="true" @endif>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mb-6 flex flex-wrap gap-2" role="group" aria-label="Filter closed gigs">
        @foreach (['all' => 'All', 'open' => 'Open ('.$openCount.')', 'closed' => 'Closed ('.$closedCount.')'] as $value => $label)
            <button type="button" wire:click="$set('closedFilter', '{{ $value }}')"
                    class="rounded border px-3 py-1.5 text-sm {{ $closedFilter === $value ? 'border-[#39c5cf]/70 bg-[#39c5cf]/10 font-medium text-body' : 'border-white/15 text-muted hover:border-white/30 hover:text-body' }}"
                    data-closed-filter="{{ $value }}"
                    @if ($closedFilter === $value) aria-current="true" @endif>
                {{ $label }}
            </button>
        @endforeach
    </div>

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
            <p class="text-sm text-muted">No applications yet.</p>
        @endforelse
    </div>

    {{-- Weekly --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Per week</h2>
    <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted">
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:#39c5cf"></span>Added</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:#f0883e"></span>Applied</span>
    </div>
    <div class="mb-10 overflow-x-auto rounded border border-white/10 bg-sidebar p-4">
        <div class="flex min-w-max gap-2 sm:gap-4" style="min-width: 100%">
            @foreach ($weekly as $w)
                <div class="flex min-w-[2.5rem] flex-1 flex-col items-center">
                    <div class="flex h-40 w-full items-end justify-center gap-0.5">
                        @foreach (['added' => '#39c5cf', 'applied' => '#f0883e'] as $series => $color)
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
                    <span class="inline-block h-3 w-3 rounded-sm" style="background: {{ JA::TYPE_COLORS[$t] ?? '#6e7681' }}"></span>
                    <span class="w-24 text-body">{{ $t }}</span>
                    <span class="w-10 text-right font-semibold tabular-nums text-body">{{ $n }}</span>
                    <span class="tabular-nums text-muted">{{ $typeTotal ? round($n / $typeTotal * 100) : 0 }}%</span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Next steps --}}
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Next Steps:</h2>
    <div class="mb-10 rounded border border-white/10 bg-sidebar p-4" data-next-steps>
        @if ($nextUp)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs text-muted">Apply next{{ $nextQueueCount > 1 ? ' · '.($nextQueueCount - 1).' more waiting' : '' }}</p>
                    <button type="button" wire:click="openView({{ $nextUp->id }})" class="mt-0.5 text-left text-base font-semibold text-body hover:text-link" data-next-step-id="{{ $nextUp->id }}">{{ $nextUp->role ?: 'Role' }}</button>
                    <div class="text-sm text-body">{{ $nextUp->company }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                        <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background: {{ JA::statusColor($nextUp->status) }}"></span>{{ $nextUp->status }}</span>
                        @foreach (array_filter([$categories[$nextUp->category] ?? null, $nextUp->type, $nextUp->rate, $nextUp->ir35 && $nextUp->ir35 !== 'N/A' ? 'IR35 '.$nextUp->ir35 : null, $nextUp->location]) as $bit)
                            <span>· {{ $bit }}</span>
                        @endforeach
                    </div>
                    @if ($nextUp->next_step)<div class="mt-1 text-xs text-muted">{{ $nextUp->next_step }}</div>@endif
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($nextUp->link)
                        <a href="{{ $nextUp->link }}" target="_blank" rel="noopener noreferrer"
                           class="rounded border border-white/15 px-3 py-1.5 text-sm text-link no-underline hover:border-[#39c5cf]/60">Open job ↗</a>
                    @endif
                    <button type="button" wire:click="openCoverLetter({{ $nextUp->id }})" data-cover-letter-btn="{{ $nextUp->id }}"
                            class="rounded border px-3 py-1.5 text-sm font-medium {{ $nextUp->hasCoverLetter() ? 'border-[#39c5cf]/60 bg-[#39c5cf]/10 text-body hover:bg-[#39c5cf]/20' : ($nextUp->cover_letter_not_needed ? 'border-white/10 text-muted hover:border-white/30 hover:text-body' : 'border-white/15 text-body hover:border-[#39c5cf]/60') }}">
                        @if ($nextUp->hasCoverLetter())
                            Cover letter ✓
                        @elseif ($nextUp->cover_letter_not_needed)
                            No cover letter
                        @else
                            Cover letter
                        @endif
                    </button>
                    <button type="button" wire:click="markApplied({{ $nextUp->id }})" wire:loading.attr="disabled"
                            class="rounded border border-[#f0883e]/60 bg-[#f0883e]/10 px-3 py-1.5 text-sm font-medium text-body hover:bg-[#f0883e]/20">Done, I applied</button>
                </div>
            </div>
        @else
            <p class="text-sm text-muted">Nothing left to apply to{{ $category !== 'all' ? ' in this category' : '' }}.</p>
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

    {{-- Activity log (collapsed by default; wire:ignore.self keeps it open across Livewire updates) --}}
    <details class="group mb-10 rounded border border-white/10 bg-sidebar" wire:ignore.self data-activity-log>
        <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">
            <span>Activity log ({{ count($log) }})</span>
            <span class="text-xs font-normal normal-case tracking-normal text-link"><span class="group-open:hidden">Show</span><span class="hidden group-open:inline">Hide</span></span>
        </summary>
    <ol class="border-t border-white/10">
        @php $lastDate = null; @endphp
        @forelse ($log as $entry)
            @if ($entry['date'] !== $lastDate)
                <li class="border-t border-white/10 px-4 pb-1 pt-3 text-xs font-semibold uppercase tracking-[0.12em] text-muted first:border-t-0">{{ \Carbon\Carbon::parse($entry['date'])->format('D j M Y') }}</li>
                @php $lastDate = $entry['date']; @endphp
            @endif
            <li class="flex gap-3 px-4 py-1.5 text-sm">
                <span class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full" style="background: {{ $kindColors[$entry['kind']] ?? '#6e7681' }}"></span>
                <div class="min-w-0">
                    <span class="font-semibold text-body">{{ $entry['title'] }}</span>
                    @if ($entry['app'])<span class="text-body"> · {{ $entry['app']->label() }}</span>@endif
                    @if ($entry['source'] === 'api' || $entry['source'] === 'manual')<span class="ml-1 text-[10px] uppercase tracking-wide text-faint">{{ $entry['source'] === 'api' ? 'via API' : 'by hand' }}</span>@endif
                    @if ($entry['detail'])<div class="text-xs text-muted">{{ $entry['detail'] }}</div>@endif
                </div>
            </li>
        @empty
            <li class="px-4 py-4 text-sm text-muted">Nothing logged yet.</li>
        @endforelse
    </ol>
    </details>

    {{-- All rows --}}
    <details class="mb-10 rounded border border-white/10 bg-sidebar" wire:ignore.self>
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">
            @if ($closedFilter === 'open')
                Open applications ({{ $listed->count() }})
            @elseif ($closedFilter === 'closed')
                Closed applications ({{ $listed->count() }})
            @else
                All applications ({{ $listed->count() }})
            @endif
        </summary>
        <ul class="divide-y divide-white/10 border-t border-white/10">
            @forelse ($listed as $a)
                <li class="px-4 py-3 text-sm {{ $a->isClosed() ? 'bg-white/[0.02] opacity-70' : '' }}" wire:key="app-{{ $a->id }}" @if ($a->isClosed()) data-closed-gig="{{ $a->id }}" @endif>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <button type="button" wire:click="openView({{ $a->id }})" class="min-w-0 text-left font-semibold {{ $a->isClosed() ? 'text-muted' : 'text-link hover:underline' }}">{{ $a->label() }}</button>
                        <span class="flex flex-wrap items-center gap-2 text-xs text-muted">
                            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background: {{ JA::statusColor($a->status) }}"></span>{{ $a->status }}</span>
                            @if ($a->isClosed())
                                <span class="rounded border border-[#da3633]/50 bg-[#da3633]/10 px-2 py-0.5 text-xs font-medium text-body" data-closed-badge>Closed</span>
                            @endif
                            @if ($a->hasCoverLetter())
                                <button type="button" wire:click="openCoverLetter({{ $a->id }})"
                                        class="rounded border border-[#39c5cf]/40 px-2 py-0.5 text-xs text-body hover:border-[#39c5cf]/60"
                                        aria-label="Cover letter for {{ $a->label() }}">Cover letter ✓</button>
                            @endif
                            <a href="{{ $a->threadsUrl() }}" data-comm-threads="{{ $a->id }}"
                               class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted no-underline hover:border-[#39c5cf]/60 hover:text-body"
                               aria-label="Comm. Threads for {{ $a->label() }}">Comm. Threads{!! ($a->messages_count ?? 0) > 0 ? ' <span class="tabular-nums">(' . e($a->messages_count) . ')</span>' : '' !!}</a>
                            @if ($a->isClosed())
                                <button type="button" wire:click="reopen({{ $a->id }})" data-reopen="{{ $a->id }}"
                                        class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted hover:border-[#39c5cf]/60 hover:text-body"
                                        aria-label="Reopen {{ $a->label() }}">Reopen</button>
                            @else
                                <button type="button" wire:click="markClosed({{ $a->id }})" data-mark-closed="{{ $a->id }}"
                                        class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted hover:border-[#da3633]/60 hover:text-body"
                                        aria-label="Mark {{ $a->label() }} as closed">Mark as closed</button>
                            @endif
                            <button type="button" wire:click="openEdit({{ $a->id }})"
                                    class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted hover:border-[#39c5cf]/60 hover:text-body"
                                    aria-label="Edit {{ $a->label() }}">Edit</button>
                        </span>
                    </div>
                    @if ($a->next_step || $a->follow_up_date)
                        <div class="mt-0.5 text-xs text-muted">{{ collect([$a->next_step, $a->follow_up_date ? 'Follow up '.$a->follow_up_date->format('j M') : null])->filter()->implode(' · ') }}</div>
                    @endif
                    <div class="mt-0.5 text-xs text-muted">{{ collect([$categories[$a->category] ?? null, $a->type, $a->rate, $a->ir35 && $a->ir35 !== 'N/A' ? 'IR35 '.$a->ir35 : null, $a->location])->filter()->implode(' · ') }}</div>
                    <div class="text-xs text-faint">{{ collect([$a->date_added ? 'Added '.$a->date_added->format('j M') : null, $a->date_applied ? 'Applied '.$a->date_applied->format('j M') : null, $a->source, $a->contact])->filter()->implode(' · ') }}</div>
                </li>
            @empty
                <li class="px-4 py-4 text-sm text-muted">No applications in this view.</li>
            @endforelse
        </ul>
    </details>
    @if ($viewing)
        @php
            $rows = [
                'Company' => $viewing->company,
                'Role' => $viewing->role,
                'Category' => $categories[$viewing->category] ?? $viewing->category,
                'Status' => $viewing->status,
                'Type' => $viewing->type,
                'Rate / salary' => $viewing->rate,
                'IR35' => $viewing->ir35,
                'Location' => $viewing->location,
                'Source / recruiter' => $viewing->source,
                'Contact' => $viewing->contact,
                'Date added' => $viewing->date_added?->format('D j M Y'),
                'Date applied' => $viewing->date_applied?->format('D j M Y'),
                'Follow-up date' => $viewing->follow_up_date?->format('D j M Y'),
                'Next step / result' => $viewing->next_step,
                'Notes' => $viewing->notes,
            ];
        @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
             wire:keydown.escape.window="closeView"
             role="dialog" aria-modal="true" aria-labelledby="job-view-title" data-job-modal>
            <div class="absolute inset-0 bg-black/60" wire:click="closeView" aria-hidden="true"></div>

            <div class="relative z-10 max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-t-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:rounded-lg sm:p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="job-view-title" class="text-base font-semibold text-body">{{ $viewing->role ?: 'Role' }}</h2>
                        <p class="text-sm text-muted">{{ $viewing->company }}</p>
                    </div>
                    <button type="button" wire:click="closeView"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body" aria-label="Close">×</button>
                </div>

                <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
                    @foreach ($rows as $label => $value)
                        @continue($value === null || $value === '')
                        <dt class="text-xs uppercase tracking-[0.14em] text-muted sm:pt-0.5">{{ $label }}</dt>
                        <dd class="mb-1 whitespace-pre-line break-words text-body sm:mb-0">{{ $value }}</dd>
                    @endforeach
                    @if ($viewing->link)
                        <dt class="text-xs uppercase tracking-[0.14em] text-muted sm:pt-0.5">Link</dt>
                        <dd class="mb-1 break-all text-xs text-muted sm:mb-0">{{ $viewing->link }}</dd>
                    @endif
                </dl>

                <section class="mt-6 border-t border-white/10 pt-4" data-conversation data-conversation-job="{{ $viewing->id }}">
                    <a href="{{ $viewing->threadsUrl() }}" data-comm-threads="{{ $viewing->id }}"
                       class="inline-flex rounded border border-white/15 bg-panel px-3 py-1.5 text-sm font-medium text-body no-underline hover:border-[#39c5cf]/60">
                        Comm. Threads{!! ($viewing->messages_count ?? 0) > 0 ? ' <span class="tabular-nums text-muted">(' . e($viewing->messages_count) . ')</span>' : '' !!}
                    </a>
                    <p class="mt-2 text-xs text-muted">Cover letter and conversation messages for this gig live on the Comm. Threads page.</p>
                </section>

                <div class="sticky bottom-0 -mx-4 mt-4 flex flex-wrap justify-end gap-2 border-t border-white/10 bg-sidebar px-4 pt-3 sm:-mx-5 sm:px-5">
                    @if ($viewing->isClosed())
                        <button type="button" wire:click="reopen({{ $viewing->id }})" data-reopen="{{ $viewing->id }}"
                                class="rounded border border-white/15 px-3 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">Reopen</button>
                    @else
                        <button type="button" wire:click="markClosed({{ $viewing->id }})" data-mark-closed="{{ $viewing->id }}"
                                class="rounded border border-white/15 px-3 py-1.5 text-sm font-medium text-body hover:border-[#da3633]/60">Mark as closed</button>
                    @endif
                    <button type="button" wire:click="openCoverLetter({{ $viewing->id }})" data-cover-letter-btn="{{ $viewing->id }}"
                            class="rounded border px-3 py-1.5 text-sm font-medium {{ $viewing->hasCoverLetter() ? 'border-[#39c5cf]/60 bg-[#39c5cf]/10 text-body hover:bg-[#39c5cf]/20' : ($viewing->cover_letter_not_needed ? 'border-white/10 text-muted hover:text-body' : 'border-white/15 text-muted hover:text-body') }}">
                        @if ($viewing->hasCoverLetter())
                            Cover letter ✓
                        @elseif ($viewing->cover_letter_not_needed)
                            No cover letter
                        @else
                            Cover letter
                        @endif
                    </button>
                    <button type="button" wire:click="openEdit({{ $viewing->id }})"
                            class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">Edit</button>
                    @if ($viewing->link)
                        <a href="{{ $viewing->link }}" target="_blank" rel="noopener noreferrer"
                           class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium text-link no-underline hover:border-[#39c5cf]/60">Open application ↗</a>
                    @else
                        <span class="px-2 py-1.5 text-xs text-faint">No application link</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
    @if ($coverLetterJob && $coverLetterJobId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-4"
             wire:keydown.escape.window="closeCoverLetter"
             role="dialog" aria-modal="true" aria-labelledby="cover-letter-title" data-cover-letter-modal>
            <div class="absolute inset-0 bg-black/60" wire:click="closeCoverLetter" aria-hidden="true"></div>
            <div class="relative z-10 max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-t-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:rounded-lg sm:p-5"
                 x-data="{ copied: false }">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="cover-letter-title" class="text-base font-semibold text-body">Cover letter</h2>
                        <p class="text-sm text-muted">{{ $coverLetterJob->label() }}</p>
                    </div>
                    <button type="button" wire:click="closeCoverLetter"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body" aria-label="Close">×</button>
                </div>

                <form wire:submit="saveCoverLetter">
                    <label class="mb-3 flex items-center gap-2 text-sm text-body">
                        <input type="checkbox" wire:model="coverLetterNotNeeded" class="rounded border-white/20 bg-panel">
                        This job does not need a cover letter
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Cover letter
                        <textarea wire:model="coverLetterBody" x-ref="letter" rows="14"
                                  class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none"
                                  placeholder="Paste your cover letter here"></textarea>
                        @error('coverLetterBody')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    @if ($coverLetterJob->cover_letter_updated_at)
                        <p class="mt-2 text-[11px] text-faint">Last saved {{ $coverLetterJob->cover_letter_updated_at->copy()->timezone($tz)->format('j M Y, H:i') }} ({{ $tz }})</p>
                    @endif

                    <div class="sticky bottom-0 -mx-4 mt-4 flex flex-wrap justify-end gap-2 border-t border-white/10 bg-sidebar px-4 pt-3 sm:-mx-5 sm:px-5">
                        <button type="button"
                                x-on:click="navigator.clipboard.writeText($refs.letter.value); copied = true; setTimeout(() => copied = false, 1500)"
                                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                                :disabled="!($refs.letter && $refs.letter.value)">
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                        <button type="button" wire:click="closeCoverLetter"
                                class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">Cancel</button>
                        <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if ($showCoverLetters)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-4"
             wire:keydown.escape.window="closeCoverLetters"
             role="dialog" aria-modal="true" aria-labelledby="cover-letters-title" data-cover-letters-modal>
            <div class="absolute inset-0 bg-black/60" wire:click="closeCoverLetters" aria-hidden="true"></div>
            <div class="relative z-10 max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-t-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:rounded-lg sm:p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="cover-letters-title" class="text-base font-semibold text-body">My cover letters</h2>
                        <p class="text-sm text-muted">{{ $savedCoverLetters->count() }} saved</p>
                    </div>
                    <button type="button" wire:click="closeCoverLetters"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body" aria-label="Close">×</button>
                </div>
                <ul class="divide-y divide-white/10 rounded border border-white/10">
                    @forelse ($savedCoverLetters as $letter)
                        <li class="px-3 py-3" wire:key="cl-{{ $letter->id }}" x-data="{ copied: false }">
                            <details>
                                <summary class="cursor-pointer">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <span class="font-semibold text-body">{{ $letter->label() }}</span>
                                        @if ($letter->cover_letter_updated_at)
                                            <span class="text-xs tabular-nums text-muted">{{ $letter->cover_letter_updated_at->copy()->timezone($tz)->format('j M Y, H:i') }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-muted">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $letter->cover_letter), 140) }}</p>
                                </summary>
                                <pre class="mt-3 max-h-72 overflow-auto whitespace-pre-wrap break-words rounded border border-white/10 bg-panel p-3 text-sm text-body" x-ref="letter">{{ $letter->cover_letter }}</pre>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button"
                                            x-on:click="navigator.clipboard.writeText($refs.letter.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">
                                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                    </button>
                                    <button type="button" wire:click="openView({{ $letter->id }})"
                                            class="rounded border border-white/15 px-3 py-1.5 text-sm text-body hover:border-[#39c5cf]/60">Open job</button>
                                </div>
                            </details>
                        </li>
                    @empty
                        <li class="px-3 py-4 text-sm text-muted">No cover letters saved yet. Open a gig's Comm. Threads page, or use the Cover letter button, and paste one.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
             wire:keydown.escape.window="closeForm"
             role="dialog" aria-modal="true" aria-labelledby="job-form-title">
            <div class="absolute inset-0 bg-black/60" wire:click="closeForm" aria-hidden="true"></div>

            <form wire:submit="save" class="relative z-10 max-h-[92dvh] w-full max-w-2xl overflow-y-auto rounded-t-lg border border-white/10 bg-sidebar p-4 shadow-xl sm:rounded-lg sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="job-form-title" class="text-sm font-semibold uppercase tracking-[0.16em] text-muted">
                        {{ $editingId ? 'Edit application' : 'Add application' }}
                    </h2>
                    <button type="button" wire:click="closeForm"
                            class="rounded px-1 text-lg leading-none text-muted hover:text-body" aria-label="Close">×</button>
                </div>

                @error('form.fields')<p class="mb-3 text-xs text-[#da3633]">{{ $message }}</p>@enderror

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Company *
                        <input type="text" wire:model="form.company" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.company')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Role *
                        <input type="text" wire:model="form.role" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.role')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Category
                        <select wire:model="form.category" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                            @foreach ($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('form.category')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Status
                        <input type="text" list="job-statuses" wire:model="form.status" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        <datalist id="job-statuses">@foreach ($knownStatuses as $opt)<option value="{{ $opt }}"></option>@endforeach</datalist>
                        @error('form.status')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Type
                        <select wire:model="form.type" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                            <option value="">—</option>
                            @foreach ($types as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                        @error('form.type')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Date added
                        <input type="date" wire:model="form.date_added" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.date_added')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Date applied
                        <input type="date" wire:model="form.date_applied" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.date_applied')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Rate / salary
                        <input type="text" wire:model="form.rate" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.rate')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        IR35
                        <select wire:model="form.ir35" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                            <option value="">—</option>
                            @foreach ($ir35Options as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                        @error('form.ir35')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Location
                        <input type="text" wire:model="form.location" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.location')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Source / recruiter
                        <input type="text" wire:model="form.source" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.source')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Contact
                        <input type="text" wire:model="form.contact" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.contact')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Link
                        <input type="text" inputmode="url" wire:model="form.link" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.link')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted sm:col-span-2">
                        Next step / result
                        <textarea wire:model="form.next_step" rows="3" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none"></textarea>
                        @error('form.next_step')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                        Follow-up date
                        <input type="date" wire:model="form.follow_up_date" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none" />
                        @error('form.follow_up_date')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                    <span class="hidden sm:block"></span>
                    <label class="block text-xs uppercase tracking-[0.14em] text-muted sm:col-span-2">
                        Notes
                        <textarea wire:model="form.notes" rows="3" class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none"></textarea>
                        @error('form.notes')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                    </label>
                </div>

                <p class="mt-3 text-[11px] text-faint">Changes to status, applied date, next step, follow-up and notes go into the activity log.</p>

                <div class="sticky bottom-0 -mx-4 mt-4 flex justify-end gap-2 border-t border-white/10 bg-sidebar px-4 pt-3 sm:-mx-5 sm:px-5">
                    <button type="button" wire:click="closeForm"
                            class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">Cancel</button>
                    <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">
                        {{ $editingId ? 'Save' : 'Add' }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
