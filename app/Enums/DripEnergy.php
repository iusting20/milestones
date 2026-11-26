<?php

namespace App\Enums;

enum DripEnergy: string
{
    case Drains = 'drains';
    case Lights = 'lights';

    public function color(): string
    {
        return match ($this) {
            self::Drains => '#d00000',
            self::Lights => '#16db65',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Drains => '🌧️',
            self::Lights => '🌞',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Drains => 'Drains Energy',
            self::Lights => 'Lights You Up',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Drains => 'Sucks the life out of you. Pair with money to land on Delegation or Replacement.',
            self::Lights => 'Energizes and lights you up. Pair with money to land on Investment or Production.',
        };
    }
}
