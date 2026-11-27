@php
    use App\Livewire\JobApplications as JA;
@endphp
<div>
    <div class="min-w-0 text-center">
        @if (! empty($caption))
            <p class="text-xs uppercase tracking-[0.14em] text-[#39c5cf]">{{ $caption }}</p>
        @endif
        <button type="button" wire:click="openView({{ $job->id }})" class="mx-auto mt-3 flex w-full flex-col items-center gap-3 text-center text-2xl font-semibold text-body hover:text-link sm:text-3xl" @if (! empty($markCurrent)) data-next-step-id="{{ $job->id }}" @endif>
            @include('livewire.partials.job-avatar', ['job' => $job, 'avatarClass' => 'h-32 w-32', 'avatarSize' => 128])
            <span>{{ $job->role ?: 'Role' }}</span>
        </button>
        <div class="mt-1 text-lg text-body">{{ $job->company }}</div>
        @if ($job->next_step)<div class="mt-2 text-sm text-muted">{{ $job->next_step }}</div>@endif
    </div>
    <div class="mt-4 grid w-full grid-cols-2 gap-2">
        <div class="flex flex-col gap-2">
            <button type="button" wire:click="openCoverLetter({{ $job->id }})" data-cover-letter-btn="{{ $job->id }}"
                    class="rounded border px-3 py-1.5 text-sm font-medium {{ $job->hasCoverLetter() ? 'border-[#39c5cf]/60 bg-[#39c5cf]/10 text-body hover:bg-[#39c5cf]/20' : ($job->cover_letter_not_needed ? 'border-white/10 text-muted hover:border-white/30 hover:text-body' : 'border-white/15 text-body hover:border-[#39c5cf]/60') }}">
                @if ($job->hasCoverLetter())
                    Cover letter &amp; CV ✓
                @elseif ($job->cover_letter_not_needed)
                    No cover letter
                @else
                    Cover letter &amp; CV
                @endif
            </button>
            <a href="{{ $job->threadsUrl() }}" data-comm-threads="{{ $job->id }}" @if (! empty($markCurrent)) data-next-step-comm-thread @endif
               class="rounded border border-white/15 px-3 py-1.5 text-center text-sm text-body no-underline hover:border-[#39c5cf]/60"
               aria-label="Comm. Thread for {{ $job->label() }}">Comm. Thread{!! ($job->messages_count ?? 0) > 0 ? ' <span class="tabular-nums text-muted">(' . e($job->messages_count) . ')</span>' : '' !!}</a>
            <button type="button" wire:click="markApplied({{ $job->id }})" wire:loading.attr="disabled"
                    class="rounded border border-[#f0883e]/60 bg-[#f0883e]/10 px-3 py-1.5 text-sm font-medium text-body hover:bg-[#f0883e]/20">Done, I applied</button>
        </div>
        <div class="flex flex-col gap-2">
            @if ($job->link)
                <a href="{{ $job->link }}" target="_blank" rel="noopener noreferrer"
                   class="rounded border border-white/15 px-3 py-1.5 text-center text-sm text-link no-underline hover:border-[#39c5cf]/60">Open job ↗</a>
            @else
                <span class="invisible rounded border px-3 py-1.5 text-sm" aria-hidden="true">Open job ↗</span>
            @endif
            <div class="min-h-[2.25rem] flex-1" aria-hidden="true"></div>
            <button type="button" wire:click="markClosed({{ $job->id }})" data-mark-closed="{{ $job->id }}" @if (! empty($markCurrent)) data-next-step-mark-closed @endif
                    class="rounded border border-white/15 px-3 py-1.5 text-sm font-medium text-body hover:border-[#da3633]/60"
                    aria-label="Mark {{ $job->label() }} as closed">Mark as closed</button>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-white/10 pt-3 text-xs text-muted">
        <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-full" style="background: {{ JA::statusColor($job->status) }}"></span>{{ $job->status }}</span>
        @foreach (array_filter([$categories[$job->category] ?? null, $job->type, $job->rate, $job->ir35 && $job->ir35 !== 'N/A' ? 'IR35 '.$job->ir35 : null, $job->location]) as $bit)
            <span>· {{ $bit }}</span>
        @endforeach
    </div>
</div>
