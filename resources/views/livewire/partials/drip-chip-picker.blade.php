@php
    /** @var \App\Models\TodoItem $item */
    /** @var string $method */
    /** @var string $ariaEmpty */
    /** @var string $listLabel */
    /** @var string|null $selectedKey */
    /** @var string|null $selectedDisplay */
    /** @var string|null $selectedColor */
    /** @var string|null $selectedLabel */
    /** @var string $chipClass */
    /** @var bool $withBackground */
    /** @var iterable<int, object{key: string, display: string, color: string, label: string, description: string}> $options */
    $withBackground = $withBackground ?? true;
    $chipTooltip = $selectedLabel ?? $ariaEmpty;
@endphp
<div class="relative shrink-0" x-data="{ open: false, tip: null, chipTip: false }" @click.outside="open = false; tip = null; chipTip = false">
    <button type="button"
            @click.stop="open = !open; tip = null; chipTip = false"
            @mouseenter="chipTip = true"
            @mouseleave="chipTip = false"
            @focus="chipTip = true"
            @blur="chipTip = false"
            @class([
                'flex h-5 items-center justify-center rounded px-1 text-[9px] font-bold leading-none transition '.$chipClass,
                'border border-muted/50 bg-transparent text-muted hover:border-muted hover:text-body' => $selectedKey === null,
                'text-black' => $withBackground && $selectedKey !== null,
                'bg-transparent text-base leading-none hover:opacity-80' => ! $withBackground && $selectedKey !== null,
            ])
            @if ($withBackground && $selectedColor)
                style="background-color: {{ $selectedColor }}"
            @endif
            aria-label="{{ $selectedLabel ? 'Change '.$selectedLabel : $ariaEmpty }}"
            aria-haspopup="listbox"
            aria-expanded="false"
            :aria-expanded="open.toString()">
        {{ $selectedDisplay }}
    </button>

    <div x-show="chipTip && !open"
         x-cloak
         x-transition.opacity.duration.100ms
         role="tooltip"
         class="pointer-events-none absolute left-1/2 top-full z-30 mt-1.5 -translate-x-1/2 whitespace-nowrap rounded border border-white/10 bg-panel px-2 py-1 text-[11px] font-medium text-body shadow-lg">
        {{ $chipTooltip }}
    </div>

    <div x-show="open"
         x-cloak
         x-transition.opacity.duration.100ms
         class="absolute left-0 z-20 mt-1 rounded border border-white/10 bg-panel p-1 shadow-lg"
         role="listbox"
         aria-label="{{ $listLabel }}">
        <div class="flex gap-1">
            <button type="button"
                    wire:click="{{ $method }}({{ $item->id }}, null)"
                    @click="open = false; tip = null"
                    @mouseenter="tip = null"
                    @class([
                        'flex h-7 min-w-7 items-center justify-center rounded border border-muted/50 bg-transparent px-1.5 text-muted transition hover:border-muted hover:text-body',
                        'ring-1 ring-link' => $selectedKey === null,
                    ])
                    role="option"
                    aria-label="Clear"
                    aria-selected="{{ $selectedKey === null ? 'true' : 'false' }}">
            </button>
            @foreach ($options as $option)
                <button type="button"
                        wire:click="{{ $method }}({{ $item->id }}, '{{ $option->key }}')"
                        @click="open = false; tip = null"
                        @mouseenter="tip = '{{ $option->key }}'"
                        @mouseleave="tip = null"
                        @focus="tip = '{{ $option->key }}'"
                        @blur="tip = null"
                        @if ($withBackground)
                            style="background-color: {{ $option->color }}"
                        @endif
                        @class([
                            'flex h-7 items-center justify-center rounded px-1.5 transition hover:opacity-90',
                            'text-[10px] font-bold text-black' => $withBackground,
                            'bg-transparent text-base leading-none' => ! $withBackground,
                            'ring-1 ring-white/80' => $selectedKey === $option->key,
                        ])
                        role="option"
                        aria-label="{{ $option->label }}"
                        aria-describedby="drip-axis-tip-{{ $item->id }}-{{ $method }}-{{ $option->key }}"
                        aria-selected="{{ $selectedKey === $option->key ? 'true' : 'false' }}">
                    {{ $option->display }}
                </button>
            @endforeach
        </div>

        <div class="relative">
            @foreach ($options as $option)
                <div x-show="tip === '{{ $option->key }}'"
                     x-cloak
                     id="drip-axis-tip-{{ $item->id }}-{{ $method }}-{{ $option->key }}"
                     role="tooltip"
                     class="absolute left-0 top-full z-30 mt-2 w-72 rounded border border-white/10 bg-panel p-3 shadow-lg">
                    <p class="text-sm font-bold leading-tight" style="color: {{ $option->color }}">
                        {{ $option->label }}
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-body/90">
                        {{ $option->description }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>
