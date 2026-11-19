<div @class(['shrink-0' => ! $dismissed, 'hidden' => $dismissed])>
    @unless ($dismissed)
    <button type="button"
            wire:click="toggleOpen"
            class="relative flex h-8 w-8 items-center justify-center rounded-full border border-white/10 text-sm text-body transition hover:bg-panel"
            aria-label="Onboarding, {{ $done }} of {{ $total }}"
            aria-expanded="{{ $open ? 'true' : 'false' }}">
        <svg class="absolute inset-0 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
            <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2"></circle>
            <circle cx="18" cy="18" r="15" fill="none" stroke="#ffe600" stroke-width="2"
                    stroke-linecap="round"
                    stroke-dasharray="{{ $circumference }}"
                    stroke-dashoffset="{{ $offset }}"></circle>
        </svg>
        <span class="relative text-sm leading-none">?</span>
    </button>
    @endunless

    @if ($open)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-start justify-center px-4 py-16 sm:py-24">
            <button type="button" class="absolute inset-0 bg-black/50" wire:click="close" aria-label="Close tour"></button>

            <div class="relative flex max-h-[min(32rem,calc(100vh-6rem))] w-full max-w-md flex-col overflow-hidden rounded-lg border border-white/10 bg-sidebar"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="tour-title"
                 x-data
                 x-on:keydown.escape.window="$wire.close()">
                <div class="flex items-center gap-3 border-b border-white/10 px-4 py-3">
                    <h2 id="tour-title" class="inline-block bg-mark px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-black">Tour</h2>
                    <span class="text-xs tabular-nums text-muted">{{ $done }}/{{ $total }}</span>
                    <button type="button" wire:click="close" class="ml-auto text-lg leading-none text-muted hover:text-body" aria-label="Close">&times;</button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-2">
                    @foreach ($groups as $group)
                        @php
                            $groupOpen = in_array($group['key'], $expanded, true);
                        @endphp
                        <section class="border-b border-white/5 py-2 last:border-0" wire:key="tour-group-{{ $group['key'] }}">
                            <button type="button"
                                    wire:click="toggleGroup('{{ $group['key'] }}')"
                                    class="flex w-full items-center gap-2 py-1 text-left">
                                @if ($groupOpen && $activeGroup === $group['key'])
                                    <span class="h-3 w-0.5 bg-mark" aria-hidden="true"></span>
                                @endif
                                <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">{{ $group['label'] }}</span>
                            </button>

                            @if ($groupOpen)
                                <p class="mb-2 text-xs text-muted">{{ $group['purpose'] }}</p>

                                @foreach ($group['steps'] as $step)
                                    @php
                                        $checked = in_array($step['key'], $completed, true);
                                        $here = request()->routeIs($step['route']);
                                        foreach ($step['parameters'] as $name => $value) {
                                            if ((string) request()->route($name) !== (string) $value) {
                                                $here = false;
                                            }
                                        }
                                    @endphp
                                    <div class="flex items-start gap-2 py-1.5" wire:key="tour-step-{{ $step['key'] }}">
                                        <button type="button"
                                                wire:click="toggleStep('{{ $step['key'] }}')"
                                                @class([
                                                    'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-sm border border-white/15',
                                                    'todo-check-done' => $checked,
                                                ])
                                                aria-pressed="{{ $checked ? 'true' : 'false' }}"
                                                aria-label="{{ $checked ? 'Uncheck' : 'Check' }} {{ $step['title'] }}">
                                            @if ($checked)
                                                <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2 6.5 4.5 9 10 3" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                            @endif
                                        </button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <p @class(['text-sm', 'text-muted' => $checked, 'text-body' => ! $checked])>{{ $step['title'] }}</p>
                                                @unless ($here)
                                                    <a href="{{ route($step['route'], $step['parameters']) }}"
                                                       wire:click="close"
                                                       class="shrink-0 text-xs text-link no-underline">Go</a>
                                                @endunless
                                            </div>
                                            <p class="text-xs text-muted">{{ $step['purpose'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </section>
                    @endforeach
                </div>

                <div class="flex items-center justify-between border-t border-white/10 px-4 py-2.5">
                    <button type="button" wire:click="resetTour" class="text-xs text-muted hover:text-body">Reset tour</button>
                    <button type="button" wire:click="askSkip" class="text-xs text-muted hover:text-body">Skip onboarding</button>
                </div>

                @if ($confirmingSkip)
                    <div class="absolute inset-0 z-10 flex items-center justify-center bg-black/50 px-6">
                        <div class="w-full max-w-xs rounded-lg border border-white/10 bg-sidebar p-4" role="dialog" aria-labelledby="skip-title">
                            <p id="skip-title" class="text-sm text-body">Skip removes the ? from the header.</p>
                            <div class="mt-4 flex items-center justify-end gap-3">
                                <button type="button" wire:click="close" class="text-xs text-muted hover:text-body">Cancel</button>
                                <button type="button" wire:click="skip" class="rounded bg-mark px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-black">Skip</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @endteleport
    @endif
</div>
