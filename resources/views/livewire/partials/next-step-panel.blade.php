@php
    use App\Livewire\JobApplications as JA;
@endphp
<div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        @if (! empty($caption))
            <p class="text-xs text-muted">{{ $caption }}</p>
        @endif
        <button type="button" wire:click="openView({{ $job->id }})" class="mt-0.5 text-left text-base font-semibold text-body hover:text-link" @if (! empty($markCurrent)) data-next-step-id="{{ $job->id }}" @endif>{{ $job->role ?: 'Role' }}</button>
        <div class="text-sm text-body">{{ $job->company }}</div>
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background: {{ JA::statusColor($job->status) }}"></span>{{ $job->status }}</span>
            @foreach (array_filter([$categories[$job->category] ?? null, $job->type, $job->rate, $job->ir35 && $job->ir35 !== 'N/A' ? 'IR35 '.$job->ir35 : null, $job->location]) as $bit)
                <span>· {{ $bit }}</span>
            @endforeach
        </div>
        @if ($job->next_step)<div class="mt-1 text-xs text-muted">{{ $job->next_step }}</div>@endif
    </div>
    <div class="flex w-full shrink-0 flex-col gap-2 sm:w-44">
        @if ($job->link)
            <a href="{{ $job->link }}" target="_blank" rel="noopener noreferrer"
               class="rounded border border-white/15 px-3 py-1.5 text-center text-sm text-link no-underline hover:border-[#39c5cf]/60">Open job ↗</a>
        @endif
        <a href="{{ $job->threadsUrl() }}" data-comm-threads="{{ $job->id }}" @if (! empty($markCurrent)) data-next-step-comm-thread @endif
           class="rounded border border-white/15 px-3 py-1.5 text-center text-sm text-body no-underline hover:border-[#39c5cf]/60"
           aria-label="Comm. thread for {{ $job->label() }}">Comm. thread{!! ($job->messages_count ?? 0) > 0 ? ' <span class="tabular-nums text-muted">(' . e($job->messages_count) . ')</span>' : '' !!}</a>
        <button type="button" wire:click="openCoverLetter({{ $job->id }})" data-cover-letter-btn="{{ $job->id }}"
                class="rounded border px-3 py-1.5 text-sm font-medium {{ $job->hasCoverLetter() ? 'border-[#39c5cf]/60 bg-[#39c5cf]/10 text-body hover:bg-[#39c5cf]/20' : ($job->cover_letter_not_needed ? 'border-white/10 text-muted hover:border-white/30 hover:text-body' : 'border-white/15 text-body hover:border-[#39c5cf]/60') }}">
            @if ($job->hasCoverLetter())
                Cover letter ✓
            @elseif ($job->cover_letter_not_needed)
                No cover letter
            @else
                Cover letter
            @endif
        </button>
        <button type="button" wire:click="markClosed({{ $job->id }})" data-mark-closed="{{ $job->id }}" @if (! empty($markCurrent)) data-next-step-mark-closed @endif
                class="rounded border border-white/15 px-3 py-1.5 text-sm font-medium text-body hover:border-[#da3633]/60"
                aria-label="Mark {{ $job->label() }} as closed">Mark as closed</button>
        <button type="button" wire:click="markApplied({{ $job->id }})" wire:loading.attr="disabled"
                class="rounded border border-[#f0883e]/60 bg-[#f0883e]/10 px-3 py-1.5 text-sm font-medium text-body hover:bg-[#f0883e]/20">Done, I applied</button>
    </div>
</div>
