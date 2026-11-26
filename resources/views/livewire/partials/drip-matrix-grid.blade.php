@php
    /** @var \Illuminate\Support\Collection $quadrants */
    /** @var \Illuminate\Support\Collection $untagged */
    /** @var array $grid */
    $showTaskDate = $showTaskDate ?? true;
@endphp

<div x-data="{ focus: null }" @mouseleave="focus = null">
{{-- Mobile / tablet: stacked then 2-col cards with axis hints --}}
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:hidden">
    @foreach ([
        \App\Enums\DripTag::D,
        \App\Enums\DripTag::R,
        \App\Enums\DripTag::I,
        \App\Enums\DripTag::P,
    ] as $tag)
        @php
            /** @var \App\Enums\DripTag $tag */
            $tasks = $quadrants[$tag->value];
        @endphp
        <section class="rounded-lg border border-white/10 bg-panel/60 p-3 transition-opacity"
                 :class="{ 'opacity-5': focus && focus !== '{{ $tag->value }}' }"
                 @mouseenter="focus = '{{ $tag->value }}'"
                 style="border-top: 3px solid {{ $tag->color() }}">
            <p class="mb-2 flex flex-wrap items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted">
                <span aria-hidden="true">{{ $tag->money()->shortLabel() }}</span>
                {{ $tag->money()->label() }}
                <span class="text-faint">·</span>
                <span aria-hidden="true">{{ $tag->energy()->shortLabel() }}</span>
                {{ $tag->energy() === \App\Enums\DripEnergy::Drains ? 'Drains' : 'Lights' }}
            </p>

            @include('livewire.partials.drip-matrix-quadrant', [
                'tag' => $tag,
                'tasks' => $tasks,
                'showTaskDate' => $showTaskDate,
            ])
        </section>
    @endforeach
</div>

{{-- Desktop: classic 2x2 with axis labels --}}
<div class="hidden md:block">
    <div class="mb-2 grid grid-cols-[5.5rem_1fr_1fr] gap-2 text-center text-xs font-semibold uppercase tracking-wide text-muted">
        <div></div>
        <div class="flex items-center justify-center gap-1 py-1 transition-opacity"
             :class="{ 'opacity-5': focus === 'I' || focus === 'P' }">
            <span aria-hidden="true">{{ \App\Enums\DripEnergy::Drains->shortLabel() }}</span>
            <span>Drains Energy</span>
        </div>
        <div class="flex items-center justify-center gap-1 py-1 transition-opacity"
             :class="{ 'opacity-5': focus === 'D' || focus === 'R' }">
            <span aria-hidden="true">{{ \App\Enums\DripEnergy::Lights->shortLabel() }}</span>
            <span>Lights You Up</span>
        </div>
    </div>

    <div class="grid grid-cols-[5.5rem_1fr_1fr] gap-2">
        @foreach ($grid as $rowIndex => $row)
            <div class="flex flex-col items-center justify-center gap-1 text-center text-xs font-semibold uppercase tracking-wide text-muted transition-opacity"
                 :class="{ 'opacity-5': focus && focus !== '{{ $rowIndex === 0 ? 'D' : 'R' }}' && focus !== '{{ $rowIndex === 0 ? 'I' : 'P' }}' }">
                @if ($rowIndex === 0)
                    <span aria-hidden="true" class="text-base">{{ \App\Enums\DripMoney::Low->shortLabel() }}</span>
                    <span>Low Money</span>
                @else
                    <span aria-hidden="true" class="text-base">{{ \App\Enums\DripMoney::High->shortLabel() }}</span>
                    <span>High Money</span>
                @endif
            </div>

            @foreach ($row as $tag)
                @php
                    /** @var \App\Enums\DripTag $tag */
                    $tasks = $quadrants[$tag->value];
                @endphp
                <section class="min-h-48 rounded-lg border border-white/10 bg-panel/60 p-3 transition-opacity"
                         :class="{ 'opacity-5': focus && focus !== '{{ $tag->value }}' }"
                         @mouseenter="focus = '{{ $tag->value }}'"
                         style="border-top: 3px solid {{ $tag->color() }}">
                    @include('livewire.partials.drip-matrix-quadrant', [
                        'tag' => $tag,
                        'tasks' => $tasks,
                        'showTaskDate' => $showTaskDate,
                    ])
                </section>
            @endforeach
        @endforeach
    </div>
</div>
</div>

@php
    $untaggedWip = $untagged->where('is_completed', false)->values();
    $untaggedDone = $untagged->where('is_completed', true)->values();
@endphp

@if ($untaggedWip->isNotEmpty() || $untaggedDone->isNotEmpty())
    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach ([
            ['label' => 'Untagged WIP', 'tasks' => $untaggedWip],
            ['label' => 'Untagged done', 'tasks' => $untaggedDone],
        ] as $column)
            <section class="rounded-lg border border-dashed border-white/10 p-3">
                <h2 class="mb-3 text-sm font-bold text-muted">
                    {{ $column['label'] }} ({{ $column['tasks']->count() }})
                </h2>

                @if ($column['tasks']->isEmpty())
                    <p class="text-sm text-muted/60">No tasks</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($column['tasks'] as $task)
                            <li @class([
                                'flex items-start gap-2 text-sm leading-snug',
                                'has-parent-tip' => $task->parent,
                            ])
                                @if ($task->parent)
                                    data-tooltip="from: {{ $task->parent->title }}"
                                @endif
                            >
                                @include('livewire.partials.drip-chips-row', [
                                    'item' => $task,
                                    'axesOnly' => true,
                                ])

                                <div @class([
                                    'min-w-0 flex-1 break-words',
                                    'text-muted' => $task->is_completed,
                                    'text-body' => ! $task->is_completed,
                                ])>
                                    @if ($task->is_completed)
                                        <span class="mr-1 text-muted" aria-hidden="true">✔</span>
                                    @else
                                        <span class="mr-1 text-muted" aria-hidden="true">○</span>
                                    @endif
                                    @if ($showTaskDate && isset($task->date))
                                        <span class="mr-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted">
                                            {{ $task->date->format('D') }}
                                        </span>
                                    @endif
                                    {{ $task->title }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
@endif
