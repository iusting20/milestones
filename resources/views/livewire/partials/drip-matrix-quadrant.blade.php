@php
    $showTaskDate = $showTaskDate ?? true;
@endphp
<header class="mb-3 flex items-baseline justify-between gap-2">
    <h2 class="text-sm font-bold" style="color: {{ $tag->color() }}">
        <span class="mr-1 inline-flex h-5 w-5 items-center justify-center rounded text-[10px] font-bold text-black"
              style="background-color: {{ $tag->color() }}">{{ $tag->value }}</span>
        {{ $tag->label() }}
    </h2>
    <span class="shrink-0 text-xs text-muted">{{ $tasks->count() }}</span>
</header>

@if ($tasks->isEmpty())
    <p class="text-sm text-muted/60">No tasks</p>
@else
    <ul class="space-y-2">
        @foreach ($tasks as $task)
            <li @class([
                'break-words text-sm leading-snug',
                'text-muted' => $task->is_completed,
                'text-body' => ! $task->is_completed,
            ])>
                @if ($task->is_completed)
                    <span class="mr-1 text-muted" aria-hidden="true">✔</span>
                @endif
                @if ($showTaskDate && isset($task->date))
                    <span class="mr-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted">
                        {{ $task->date->format('D') }}
                    </span>
                @endif
                {{ $task->title }}
            </li>
        @endforeach
    </ul>
@endif
