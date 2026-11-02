<?php

namespace App\Enums;

enum WeekBoardStatus: string
{
    case Planned = 'planned';
    case Wip = 'wip';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Wip => 'WIP',
            self::Done => 'Done',
        };
    }

    /**
     * Numeric code used to identify a board lane in drag-and-drop payloads.
     */
    public function code(): int
    {
        return match ($this) {
            self::Planned => 1,
            self::Wip => 2,
            self::Done => 3,
        };
    }

    public static function fromCode(int $code): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->code() === $code) {
                return $case;
            }
        }

        return null;
    }
}
