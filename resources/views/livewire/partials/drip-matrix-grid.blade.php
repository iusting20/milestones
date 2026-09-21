@php
    /** @var \Illuminate\Support\Collection $quadrants */
    /** @var \Illuminate\Support\Collection $untagged */
    /** @var array $grid */
    $showTaskDate = $showTaskDate ?? true;
@endphp

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
        <section class="rounded-lg border border-white/10 bg-panel/60 p-3"
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
        <div class="flex items-center justify-center gap-1 py-1">
            <span aria-hidden="true">🌧️</span>
            <span>Drains Energy</span>
        </div>
        <div class="flex items-center justify-center gap-1 py-1">
            <span aria-hidden="true">💡</span>
            <span>Lights You Up</span>
        </div>
    </div>

    <div class="grid grid-cols-[5.5rem_1fr_1fr] gap-2">
        @foreach ($grid as $rowIndex => $row)
            <div class="flex flex-col items-center justify-center gap-1 text-center text-xs font-semibold uppercase tracking-wide text-muted">
                @if ($rowIndex === 0)
                    <span aria-hidden="true" class="text-base">😡</span>
                    <span>Low Money</span>
                @else
                    <span aria-hidden="true" class="text-base">💰</span>
                    <span>High Money</span>
                @endif
            </div>

            @foreach ($row as $tag)
                @php
                    /** @var \App\Enums\DripTag $tag */
                    $tasks = $quadrants[$tag->value];
                @endphp
                <section class="min-h-48 rounded-lg border border-white/10 bg-panel/60 p-3"
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

@if ($untagged->isNotEmpty())
    <section class="mt-6 rounded-lg border border-dashed border-white/10 p-3">
        <h2 class="mb-3 text-sm font-bold text-muted">Untagged ({{ $untagged->count() }})</h2>
        <ul class="space-y-2">
            @foreach ($untagged as $task)
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
    </section>
@endif
