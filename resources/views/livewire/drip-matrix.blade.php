<div class="mx-auto max-w-5xl px-3 py-4 sm:px-4 sm:py-6">
    <div class="mb-5 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('milestones', ['week' => $week]) }}"
               class="rounded border border-white/10 px-2.5 py-1.5 text-sm text-muted no-underline hover:text-body sm:px-3">
                ‹ <span class="hidden sm:inline">Milestones</span>
            </a>

            <button type="button" wire:click="previousWeek"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                    aria-label="Previous week">‹</button>

            <h1 class="inline-block max-w-[min(100%,12rem)] truncate bg-mark px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-black sm:max-w-none sm:px-3 sm:text-sm md:text-base">
                <span class="sm:hidden">DRIP · W{{ $weekNumber }}</span>
                <span class="hidden sm:inline">DRIP Matrix - Week {{ $weekNumber }}</span>
            </h1>

            <button type="button" wire:click="nextWeek"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-muted hover:text-body"
                    aria-label="Next week">›</button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="goToToday"
                    class="rounded border border-white/10 px-3 py-1.5 text-sm text-link no-underline hover:bg-panel">
                Today
            </button>

            <select wire:model.live="status"
                    aria-label="Task status filter"
                    class="min-w-0 flex-1 rounded border border-white/10 bg-panel px-2 py-1.5 text-sm text-body focus:outline-none focus:ring-1 focus:ring-link sm:flex-none">
                <option value="all">all</option>
                <option value="pending">pending</option>
                <option value="completed">completed</option>
            </select>

            <label class="flex cursor-pointer items-center gap-1.5 text-sm text-muted hover:text-body">
                <input type="checkbox"
                       wire:model.live="todayOnly"
                       class="h-3.5 w-3.5 shrink-0 rounded border-white/20 bg-panel text-check focus:ring-link focus:ring-offset-0">
                <span>today tasks</span>
            </label>
        </div>
    </div>

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

                @include('livewire.partials.drip-matrix-quadrant', ['tag' => $tag, 'tasks' => $tasks])
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
                        @include('livewire.partials.drip-matrix-quadrant', ['tag' => $tag, 'tasks' => $tasks])
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
                        <span class="mr-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted">
                            {{ $task->date->format('D') }}
                        </span>
                        {{ $task->title }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
