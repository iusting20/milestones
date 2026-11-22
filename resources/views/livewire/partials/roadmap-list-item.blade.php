<li wire:key="range-{{ $item->id }}"
    @mouseenter="hoverId = {{ $item->id }}"
    @mouseleave="hoverId = null"
    :class="{ 'roadmap-bar-dimmed': hoverId !== null && hoverId !== {{ $item->id }} }"
    class="group flex items-center gap-2 rounded px-1 py-1.5 transition-opacity hover:bg-white/5">
    @if ($item->isDeliverable())
        <span @class([
            'mr-[3ch] w-3 shrink-0 text-center text-sm leading-none',
            'roadmap-color-'.$this->colorIndexFor($item->id) => $item->isPending(),
        ]) style="color: {{ $item->isPending() ? 'var(--roadmap-color)' : '#7cb518' }}" aria-hidden="true">★</span>
    @else
        <span @class([
            'mr-[3ch] h-2 w-2 shrink-0 rounded-full',
            'roadmap-color-'.$this->colorIndexFor($item->id) => $item->isPending(),
            'roadmap-swatch' => $item->isPending(),
            'roadmap-swatch-done' => ! $item->isPending(),
        ])></span>
    @endif

    <span @class([
        'min-w-0 flex-1 truncate text-sm',
        'text-muted' => ! $item->isPending(),
        'text-body' => $item->isPending(),
    ])>
        <button type="button"
                wire:click="openEdit({{ $item->id }})"
                class="truncate text-left hover:underline"
                title="Edit {{ $item->title }}">
            {{ $item->title }}
        </button>
        @if ($item->hasLink())
            <a href="{{ $item->link_url }}"
               target="_blank"
               rel="noopener noreferrer"
               class="ml-2 text-link hover:underline"
               title="{{ $item->link_url }}">{{ $item->linkLabel() }}</a>
        @endif
        @if ($item->project)
            <span class="ml-2 text-xs text-muted"
                  title="{{ $item->project->hub === 'assets' ? 'Asset' : 'Client' }}">{{ $item->project->name }}</span>
        @endif
    </span>

    <span class="flex shrink-0 items-center gap-1 text-xs tabular-nums text-muted">
        <label class="roadmap-date-hit" title="{{ $item->isDeliverable() ? 'Edit date' : 'Edit start date' }}">
            <span class="pointer-events-none">{{ $item->started_on->format('j M') }}</span>
            <input type="date"
                   value="{{ $item->started_on->toDateString() }}"
                   wire:change="updateStartedOn({{ $item->id }}, $event.target.value)"
                   class="roadmap-date-input"
                   aria-label="{{ $item->isDeliverable() ? 'Date' : 'Start date' }} for {{ $item->title }}"
                   onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
        </label>
        @unless ($item->isDeliverable())
            <span aria-hidden="true">→</span>
            <label @class(['roadmap-date-hit', 'text-muted/60' => $item->finish_on === null]) title="{{ $item->finish_on ? 'Edit scheduled finish' : 'Set scheduled finish' }}" data-finish-for="{{ $item->id }}">
                <span class="pointer-events-none">{{ $item->finish_on ? $item->finish_on->format('j M') : 'finish?' }}</span>
                <input type="date"
                       value="{{ $item->finish_on?->toDateString() }}"
                       min="{{ $item->started_on->toDateString() }}"
                       wire:change="updateFinishOn({{ $item->id }}, $event.target.value)"
                       class="roadmap-date-input"
                       aria-label="Scheduled finish for {{ $item->title }}"
                       onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
            </label>
        @endunless
        <span aria-hidden="true" class="px-0.5">·</span>
        @if ($item->isPending())
            <label class="roadmap-date-hit text-mark" title="Set completion date">
                <span class="pointer-events-none">pending</span>
                <input type="date"
                       value=""
                       wire:change="updateCompletedOn({{ $item->id }}, $event.target.value)"
                       class="roadmap-date-input"
                       aria-label="Set completion date for {{ $item->title }}"
                       onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
            </label>
        @else
            <label class="roadmap-date-hit" title="Edit completion date">
                <span class="pointer-events-none">{{ $item->completed_on->format('j M') }}</span>
                <input type="date"
                       value="{{ $item->completed_on->toDateString() }}"
                       wire:change="updateCompletedOn({{ $item->id }}, $event.target.value)"
                       class="roadmap-date-input"
                       aria-label="Completion date for {{ $item->title }}"
                       onpointerdown="if (this.showPicker) { event.preventDefault(); this.showPicker(); }">
            </label>
        @endif
    </span>

    <button type="button"
            wire:click="toggleComplete({{ $item->id }})"
            class="mt-0 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition
                {{ $item->isPending() ? 'border-muted/60 bg-transparent' : 'todo-check-done' }}"
            aria-label="{{ $item->isPending() ? 'Mark complete' : 'Mark incomplete' }}">
        @unless ($item->isPending())
            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 6l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endunless
    </button>

    <button type="button"
            wire:click="deleteItem({{ $item->id }})"
            wire:confirm="Delete this range?"
            class="shrink-0 px-1 text-xs text-muted opacity-0 transition group-hover:opacity-100 hover:text-red-400 focus:opacity-100"
            aria-label="Delete">×</button>
</li>
