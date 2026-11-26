@php
    /** @var object{drip_tag: ?\App\Enums\DripTag, drip_money: ?\App\Enums\DripMoney, drip_energy: ?\App\Enums\DripEnergy} $item */
    $axesOnly = $axesOnly ?? false;
@endphp
<div class="mt-0.5 flex shrink-0 items-center gap-1">
    @unless ($axesOnly)
        @include('livewire.partials.drip-chip-picker', [
            'item' => $item,
            'method' => 'setDripTag',
            'ariaEmpty' => 'Set drip tag',
            'listLabel' => 'DRIP tags',
            'selectedKey' => $item->drip_tag?->value,
            'selectedDisplay' => $item->drip_tag?->value,
            'selectedColor' => $item->drip_tag?->color(),
            'selectedLabel' => $item->drip_tag?->label(),
            'chipClass' => 'w-5',
            'options' => collect(\App\Enums\DripTag::cases())->map(fn (\App\Enums\DripTag $tag) => (object) [
                'key' => $tag->value,
                'display' => $tag->value,
                'color' => $tag->color(),
                'label' => $tag->label(),
                'description' => $tag->axes().'. '.$tag->description(),
            ]),
        ])
    @endunless

    @include('livewire.partials.drip-chip-picker', [
        'item' => $item,
        'method' => 'setDripMoney',
        'ariaEmpty' => 'Set money axis',
        'listLabel' => 'Money tags',
        'selectedKey' => ($item->drip_money ?? $item->drip_tag?->money())?->value,
        'selectedDisplay' => ($item->drip_money ?? $item->drip_tag?->money())?->shortLabel(),
        'selectedColor' => ($item->drip_money ?? $item->drip_tag?->money())?->color(),
        'selectedLabel' => ($item->drip_money ?? $item->drip_tag?->money())?->label(),
        'chipClass' => 'min-w-5 px-1',
        'withBackground' => false,
        'options' => collect(\App\Enums\DripMoney::cases())->map(fn (\App\Enums\DripMoney $money) => (object) [
            'key' => $money->value,
            'display' => $money->shortLabel(),
            'color' => $money->color(),
            'label' => $money->label(),
            'description' => $money->description(),
        ]),
    ])

    @include('livewire.partials.drip-chip-picker', [
        'item' => $item,
        'method' => 'setDripEnergy',
        'ariaEmpty' => 'Set energy axis',
        'listLabel' => 'Energy tags',
        'selectedKey' => ($item->drip_energy ?? $item->drip_tag?->energy())?->value,
        'selectedDisplay' => ($item->drip_energy ?? $item->drip_tag?->energy())?->shortLabel(),
        'selectedColor' => ($item->drip_energy ?? $item->drip_tag?->energy())?->color(),
        'selectedLabel' => ($item->drip_energy ?? $item->drip_tag?->energy())?->label(),
        'chipClass' => 'min-w-5 px-1',
        'withBackground' => false,
        'options' => collect(\App\Enums\DripEnergy::cases())->map(fn (\App\Enums\DripEnergy $energy) => (object) [
            'key' => $energy->value,
            'display' => $energy->shortLabel(),
            'color' => $energy->color(),
            'label' => $energy->label(),
            'description' => $energy->description(),
        ]),
    ])
</div>
