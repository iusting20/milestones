<?php

namespace App\Enums;

enum DripMoney: string
{
    case Low = 'low';
    case High = 'high';

    public function color(): string
    {
        return match ($this) {
            self::Low => '#d00000',
            self::High => '#16db65',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Low => '😡',
            self::High => '💰',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low Money',
            self::High => 'High Money',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Low => 'Little to no immediate revenue. Pair with energy to land on Delegation or Investment.',
            self::High => 'Brings in revenue or keeps the business moving. Pair with energy to land on Replacement or Production.',
        };
    }
}
