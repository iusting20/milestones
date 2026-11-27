@php
    use App\Livewire\JobApplications as JA;
@endphp
<div>
    <details class="mb-10 rounded border border-white/10 bg-sidebar" wire:ignore.self>
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">
            All opportunities
        </summary>
        <div class="space-y-3 border-t border-white/10 px-4 py-3">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Filter by category">
                @foreach (['all' => 'All'] + $categories as $value => $label)
                    <button type="button" wire:click="$parent.set('category', '{{ $value }}')"
                            class="rounded border px-3 py-1.5 text-sm {{ $category === $value ? 'border-[#39c5cf]/70 bg-[#39c5cf]/10 font-medium text-body' : 'border-white/15 text-muted hover:border-white/30 hover:text-body' }}"
                            @if ($category === $value) aria-current="true" @endif>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2" role="group" aria-label="Filter by status">
                @foreach (['all' => 'All', 'open' => 'Open ('.$openCount.')', 'applied' => 'Applied ('.$appliedCount.')', 'discussion' => 'Discussion in progress...', 'closed' => 'Closed'] as $value => $label)
                    <button type="button" wire:click="$set('closedFilter', '{{ $value }}')"
                            class="rounded border px-3 py-1.5 text-sm {{ $closedFilter === $value ? 'border-[#39c5cf]/70 bg-[#39c5cf]/10 font-medium text-body' : 'border-white/15 text-muted hover:border-white/30 hover:text-body' }}"
                            data-closed-filter="{{ $value }}"
                            @if ($closedFilter === $value) aria-current="true" @endif>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2" role="group" aria-label="Filter by source">
                @foreach (['all' => 'All', 'direct' => 'Direct', 'agency' => 'Rec. Agency', 'upwork' => 'Upwork'] as $value => $label)
                    <button type="button" wire:click="$set('channel', '{{ $value }}')"
                            class="rounded border px-3 py-1.5 text-sm {{ $channel === $value ? 'border-[#39c5cf]/70 bg-[#39c5cf]/10 font-medium text-body' : 'border-white/15 text-muted hover:border-white/30 hover:text-body' }}"
                            data-channel-filter="{{ $value }}"
                            @if ($channel === $value) aria-current="true" @endif>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="flex justify-center">
                <button type="button" wire:click="$parent.openCreate"
                        class="rounded border border-white/15 bg-panel px-3 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">
                    + add
                </button>
            </div>
        </div>
        <ul class="divide-y divide-white/10 border-t border-white/10">
            @forelse ($listed as $a)
                <li class="px-4 py-3 text-sm {{ $a->isClosed() ? 'bg-white/[0.02] opacity-70' : '' }}" wire:key="app-{{ $a->id }}" @if ($a->isClosed()) data-closed-gig="{{ $a->id }}" @endif>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button type="button" wire:click="$parent.openView({{ $a->id }})" class="flex min-w-0 items-center gap-2 text-left font-semibold {{ $a->isClosed() ? 'text-muted' : 'text-link hover:underline' }}">
                            @include('livewire.partials.job-avatar', ['job' => $a])
                            <span>{{ $a->label() }}</span>
                        </button>
                        <span class="flex flex-wrap items-center gap-2 text-xs text-muted">
                            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background: {{ JA::statusColor($a->status) }}"></span>{{ $a->status }}</span>
                            @if ($a->isClosed())
                                <span class="rounded border border-[#da3633]/50 bg-[#da3633]/10 px-2 py-0.5 text-xs font-medium text-body" data-closed-badge>Closed</span>
                            @endif
                            @if ($a->hasCoverLetter())
                                <button type="button" wire:click="$parent.openCoverLetter({{ $a->id }})"
                                        class="rounded border border-[#39c5cf]/40 px-2 py-0.5 text-xs text-body hover:border-[#39c5cf]/60"
                                        aria-label="Cover letter for {{ $a->label() }}">Cover letter ✓</button>
                            @endif
                            <a href="{{ $a->threadsUrl() }}" data-comm-threads="{{ $a->id }}"
                               class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted no-underline hover:border-[#39c5cf]/60 hover:text-body"
                               aria-label="Comm. Threads for {{ $a->label() }}">Comm. Threads{!! ($a->messages_count ?? 0) > 0 ? ' <span class="tabular-nums">(' . e($a->messages_count) . ')</span>' : '' !!}</a>
                            @if ($a->isClosed())
                                <button type="button" wire:click="$parent.reopen({{ $a->id }})" data-reopen="{{ $a->id }}"
                                        class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted hover:border-[#39c5cf]/60 hover:text-body"
                                        aria-label="Reopen {{ $a->label() }}">Reopen</button>
                            @else
                                <button type="button" wire:click="$parent.markClosed({{ $a->id }})" data-mark-closed="{{ $a->id }}"
                                        class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted hover:border-[#da3633]/60 hover:text-body"
                                        aria-label="Mark {{ $a->label() }} as closed">Mark as closed</button>
                            @endif
                            <button type="button" wire:click="$parent.openEdit({{ $a->id }})"
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
</div>
