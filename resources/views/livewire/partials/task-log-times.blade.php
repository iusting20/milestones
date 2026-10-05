@php
    $timed = $timed ?? true;
    $todoItemId = $todoItemId ?? null;
    $isEditingUntimed = ! $timed
        && $editingTodoItemId !== null
        && $editingTodoItemId === $todoItemId
        && $editingContext === $context;
@endphp

@if (! $timed)
    <div @class(['flex flex-wrap items-center gap-1 tabular-nums', $textClass ?? 'text-[10px] text-muted/70'])>
        @if ($isEditingUntimed)
            <div class="flex flex-wrap items-center gap-1"
                 data-untimed-edit>
                <input type="time"
                       wire:model="editStartedAt"
                       x-data="{ armed: false }"
                       x-init="$nextTick(() => { if (@js($editingField === 'started')) { $el.focus(); } setTimeout(() => armed = true, 150) })"
                       x-on:blur="setTimeout(() => {
                            if (!armed) return;
                            const root = $el.closest('[data-untimed-edit]');
                            if (root && root.contains(document.activeElement)) return;
                            $wire.finishEditingTime();
                       }, 100)"
                       x-on:keydown.enter.prevent="$wire.finishEditingTime()"
                       x-on:keydown.escape.prevent="armed = false; $wire.cancelEditingTime()"
                       class="w-[7.5rem] rounded border border-white/15 bg-panel px-1 py-0.5 text-inherit text-body focus:border-link focus:outline-none"
                       aria-label="Edit start time" />

                <span aria-hidden="true">→</span>

                <input type="time"
                       wire:model="editFinishedAt"
                       x-data="{ armed: false }"
                       x-init="$nextTick(() => { if (@js($editingField === 'finished')) { $el.focus(); } setTimeout(() => armed = true, 150) })"
                       x-on:blur="setTimeout(() => {
                            if (!armed) return;
                            const root = $el.closest('[data-untimed-edit]');
                            if (root && root.contains(document.activeElement)) return;
                            $wire.finishEditingTime();
                       }, 100)"
                       x-on:keydown.enter.prevent="$wire.finishEditingTime()"
                       x-on:keydown.escape.prevent="armed = false; $wire.cancelEditingTime()"
                       class="w-[7.5rem] rounded border border-white/15 bg-panel px-1 py-0.5 text-inherit text-body focus:border-link focus:outline-none"
                       aria-label="Edit finish time" />
            </div>

            @error('editStartedAt')
                <p class="basis-full text-[10px] text-red-400">{{ $message }}</p>
            @enderror
            @error('editFinishedAt')
                <p class="basis-full text-[10px] text-red-400">{{ $message }}</p>
            @enderror
        @else
            <button type="button"
                    wire:click="startEditingUntimedTime({{ $todoItemId }}, 'started', '{{ $context }}')"
                    class="cursor-text rounded px-0.5 hover:bg-white/5 hover:text-body"
                    aria-label="Edit start time N/A">
                {{ $started }}
            </button>

            <span aria-hidden="true">→</span>

            <button type="button"
                    wire:click="startEditingUntimedTime({{ $todoItemId }}, 'finished', '{{ $context }}')"
                    class="cursor-text rounded px-0.5 hover:bg-white/5 hover:text-body"
                    aria-label="Edit finish time N/A">
                {{ $finished }}
            </button>
        @endif
    </div>
@else
    <div @class(['flex flex-wrap items-center gap-1 tabular-nums', $textClass ?? 'text-[10px] text-muted/70'])>
        @if ($editingLogId === $logId && $editingField === 'started' && $editingContext === $context)
            <input type="time"
                   wire:model="editTime"
                   x-data="{ armed: false }"
                   x-init="$nextTick(() => { $el.focus(); setTimeout(() => armed = true, 150) })"
                   x-on:blur="if (armed) $wire.finishEditingTime()"
                   x-on:keydown.enter.prevent="$wire.finishEditingTime()"
                   x-on:keydown.escape.prevent="armed = false; $wire.cancelEditingTime()"
                   class="w-[7.5rem] rounded border border-white/15 bg-panel px-1 py-0.5 text-inherit text-body focus:border-link focus:outline-none"
                   aria-label="Edit start time" />
        @else
            <button type="button"
                    wire:click="startEditingTime({{ $logId }}, 'started', '{{ $context }}')"
                    class="cursor-text rounded px-0.5 hover:bg-white/5 hover:text-body"
                    aria-label="Edit start time {{ $started }}">
                {{ $started }}
            </button>
        @endif

        <span aria-hidden="true">→</span>

        @if ($editingLogId === $logId && $editingField === 'finished' && $editingContext === $context)
            <input type="time"
                   wire:model="editTime"
                   x-data="{ armed: false }"
                   x-init="$nextTick(() => { $el.focus(); setTimeout(() => armed = true, 150) })"
                   x-on:blur="if (armed) $wire.finishEditingTime()"
                   x-on:keydown.enter.prevent="$wire.finishEditingTime()"
                   x-on:keydown.escape.prevent="armed = false; $wire.cancelEditingTime()"
                   class="w-[7.5rem] rounded border border-white/15 bg-panel px-1 py-0.5 text-inherit text-body focus:border-link focus:outline-none"
                   aria-label="Edit finish time" />
        @else
            <button type="button"
                    wire:click="startEditingTime({{ $logId }}, 'finished', '{{ $context }}')"
                    class="cursor-text rounded px-0.5 hover:bg-white/5 hover:text-body"
                    aria-label="Edit finish time {{ $finished }}">
                {{ $finished }}
            </button>
        @endif

        @error('editTime')
            @if ($editingLogId === $logId && $editingContext === $context)
                <p class="basis-full text-[10px] text-red-400">{{ $message }}</p>
            @endif
        @enderror
    </div>
@endif
