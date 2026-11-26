<div class="mx-auto max-w-4xl px-3 py-6 sm:px-4">
    <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
        <div class="min-w-0">
            <p class="mb-1 text-xs">
                <a href="{{ route('jobs.secret', config('jobs.page_token')) }}" class="text-muted no-underline hover:text-link">← Gigs</a>
            </p>
            <h1 class="text-xl font-bold text-body">{{ $application->role ?: 'Role' }}</h1>
            <p class="text-sm text-muted">{{ $application->company }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="flex items-center gap-1.5 text-xs text-muted">
                <span class="inline-block h-2 w-2 rounded-full" style="background: {{ \App\Livewire\JobApplications::statusColor($application->status) }}"></span>
                {{ $application->status }}
            </span>
            @if ($application->isClosed())
                <span class="rounded border border-[#da3633]/50 bg-[#da3633]/10 px-2 py-0.5 text-xs font-medium text-body" data-closed-badge>Closed</span>
                <button type="button" wire:click="reopen" data-reopen="{{ $application->id }}"
                        class="rounded border border-white/15 bg-panel px-3 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">Reopen</button>
            @endif
        </div>
    </div>
    @if ($flash)
        <p class="mb-2 text-xs text-[#39c5cf]" wire:key="flash">{{ $flash }}</p>
    @endif

    <section class="mt-6 rounded border border-white/10 bg-sidebar p-4 {{ $application->isClosed() ? 'opacity-80' : '' }}" data-cover-letter data-cover-letter-job="{{ $application->id }}">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.16em] text-muted">Cover letter</h2>
        <form wire:submit="saveCoverLetter" x-data="{ copied: false }">
            <label class="mb-3 flex items-center gap-2 text-sm text-body">
                <input type="checkbox" wire:model="coverLetterNotNeeded" class="rounded border-white/20 bg-panel">
                This job does not need a cover letter
            </label>
            <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                Cover letter
                <textarea wire:model="coverLetterBody" x-ref="letter" rows="12"
                          class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none"
                          placeholder="Paste your cover letter here">{{ $coverLetterBody }}</textarea>
                @error('coverLetterBody')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
            </label>
            @if ($application->cover_letter_updated_at)
                <p class="mt-2 text-[11px] text-faint">Last saved {{ $application->cover_letter_updated_at->copy()->timezone($tz)->format('j M Y, H:i') }} ({{ $tz }})</p>
            @endif
            <div class="mt-3 flex flex-wrap justify-end gap-2">
                <button type="button"
                        x-on:click="navigator.clipboard.writeText($refs.letter.value); copied = true; setTimeout(() => copied = false, 1500)"
                        class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                        :disabled="!($refs.letter && $refs.letter.value)">
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
                <button type="submit" class="rounded border border-white/15 bg-panel px-4 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">Save cover letter</button>
            </div>
        </form>
    </section>

    <section class="mt-6 rounded border border-white/10 bg-sidebar p-4" data-conversation data-conversation-job="{{ $application->id }}">
        <h2 class="mb-1 text-sm font-semibold uppercase tracking-[0.16em] text-muted">
            Comm. Threads{!! $messages->isEmpty() ? '' : ' <span class="tabular-nums normal-case tracking-normal text-faint">(' . e($messages->count()) . ')</span>' !!}
        </h2>
        <p class="mb-3 text-xs text-muted">Paste messages from the conversation. Date is required; time is optional. Listed oldest first.</p>
        <ol class="mb-4 space-y-3">
            @forelse ($messages as $m)
                <li class="rounded border border-white/10 bg-panel p-3" wire:key="msg-{{ $m->id }}" @if ($editingMessageId === $m->id) data-editing-message @endif>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-xs font-semibold" style="color: {{ $m->direction === 'sent' ? '#39c5cf' : '#f778ba' }}">
                            {{ $m->directionLabel() }}
                        </span>
                        <span class="text-xs tabular-nums text-muted">{{ $m->occurredLabel($tz) }}</span>
                    </div>
                    @if ($m->subject)
                        <div class="mt-1 text-sm font-medium text-body">{{ $m->subject }}</div>
                    @endif
                    <div class="mt-1 whitespace-pre-line break-words text-sm text-body">{{ $m->body }}</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" wire:click="startEditMessage({{ $m->id }})"
                                class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted hover:border-[#39c5cf]/60 hover:text-body">Edit</button>
                        <button type="button" wire:click="deleteMessage({{ $m->id }})"
                                wire:confirm="Delete this conversation entry?"
                                class="rounded border border-white/10 px-2 py-0.5 text-xs text-muted hover:border-[#da3633]/60 hover:text-body">Delete</button>
                    </div>
                </li>
            @empty
                <li class="text-sm text-muted">No messages yet. Paste a message from the conversation below.</li>
            @endforelse
        </ol>

        <form wire:submit="saveMessage" class="rounded border border-white/10 bg-panel p-3">
            <p class="mb-2 text-xs font-medium text-body">{{ $editingMessageId ? 'Edit entry' : 'Add a message' }}</p>
            <div class="mb-2 flex flex-wrap gap-1" role="group" aria-label="Direction">
                <button type="button" wire:click="$set('messageForm.direction', 'sent')"
                        class="rounded border px-2.5 py-1 text-xs {{ $messageForm['direction'] === 'sent' ? 'border-[#39c5cf]/70 bg-[#39c5cf]/10 font-medium text-body' : 'border-white/15 text-muted hover:text-body' }}">From me</button>
                <button type="button" wire:click="$set('messageForm.direction', 'received')"
                        class="rounded border px-2.5 py-1 text-xs {{ $messageForm['direction'] === 'received' ? 'border-[#f778ba]/70 bg-[#f778ba]/10 font-medium text-body' : 'border-white/15 text-muted hover:text-body' }}">From them</button>
            </div>
            @error('messageForm.direction')<p class="mb-2 text-xs text-[#da3633]">{{ $message }}</p>@enderror
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                    Date
                    <input type="date" wire:model="messageForm.occurred_on"
                           class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                    @error('messageForm.occurred_on')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                </label>
                <label class="block text-xs uppercase tracking-[0.14em] text-muted">
                    Time <span class="normal-case tracking-normal text-faint">(optional)</span>
                    <input type="time" wire:model="messageForm.occurred_time"
                           class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                    @error('messageForm.occurred_time')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                </label>
                <label class="block text-xs uppercase tracking-[0.14em] text-muted sm:col-span-2">
                    Subject <span class="normal-case tracking-normal text-faint">(optional)</span>
                    <input type="text" wire:model="messageForm.subject"
                           class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none">
                    @error('messageForm.subject')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                </label>
                <label class="block text-xs uppercase tracking-[0.14em] text-muted sm:col-span-2">
                    Message
                    <textarea wire:model="messageForm.body" rows="6"
                              class="mt-1 w-full rounded border border-white/10 bg-panel px-3 py-2 text-sm normal-case tracking-normal text-body focus:border-link focus:outline-none"
                              placeholder="Paste the message here"></textarea>
                    @error('messageForm.body')<span class="mt-1 block normal-case tracking-normal text-[#da3633]">{{ $message }}</span>@enderror
                </label>
            </div>
            <div class="mt-3 flex justify-end gap-2">
                @if ($editingMessageId)
                    <button type="button" wire:click="cancelEditMessage"
                            class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body">Cancel</button>
                @endif
                <button type="submit" class="rounded border border-white/15 bg-sidebar px-3 py-1.5 text-sm font-medium text-body hover:border-[#39c5cf]/60">
                    {{ $editingMessageId ? 'Save entry' : 'Add to thread' }}
                </button>
            </div>
        </form>
    </section>
</div>
