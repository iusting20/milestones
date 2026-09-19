<?php

namespace App\Enums;

enum DripTag: string
{
    case D = 'D';
    case R = 'R';
    case I = 'I';
    case P = 'P';

    public function color(): string
    {
        return match ($this) {
            self::D => '#d81159',
            self::R => '#0496ff',
            self::I => '#ffbc42',
            self::P => '#52aa5e',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::D => 'Delegation',
            self::R => 'Replacement',
            self::I => 'Investment',
            self::P => 'Production',
        };
    }

    public function axes(): string
    {
        return $this->money()->label().', '.$this->energy()->label();
    }

    public function description(): string
    {
        return match ($this) {
            self::D => 'Routine, repetitive, or administrative tasks that generate little to no revenue and completely suck the life out of you. Get rid of these immediately - outsource to VAs, automation tools, or junior staff.',
            self::R => 'High-value activities that bring in revenue or keep the business moving, but you dread because they exhaust you. Systematize and replace yourself - document via playbooks and hire specialists or managers to take them over.',
            self::I => 'Passion projects, networking, learning, or long-term creative brainstorming that lights you up but does not yield an immediate financial return. Protect and time-block - fuel creativity and growth without living here.',
            self::P => 'Your zone of genius: tasks that generate significant income and deeply energize you. Maximize your time here - clear everything else so you can spend 80% to 90% of working hours in this quadrant.',
        };
    }

    public function money(): DripMoney
    {
        return match ($this) {
            self::D, self::I => DripMoney::Low,
            self::R, self::P => DripMoney::High,
        };
    }

    public function energy(): DripEnergy
    {
        return match ($this) {
            self::D, self::R => DripEnergy::Drains,
            self::I, self::P => DripEnergy::Lights,
        };
    }

    public static function fromAxes(DripMoney $money, DripEnergy $energy): self
    {
        return match ([$money, $energy]) {
            [DripMoney::Low, DripEnergy::Drains] => self::D,
            [DripMoney::High, DripEnergy::Drains] => self::R,
            [DripMoney::Low, DripEnergy::Lights] => self::I,
            [DripMoney::High, DripEnergy::Lights] => self::P,
        };
    }
}
