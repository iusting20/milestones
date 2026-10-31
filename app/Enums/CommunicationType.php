<?php

namespace App\Enums;

enum CommunicationType: string
{
    case QuickUpdate = 'quick_update';
    case ReviewPlanning = 'review_planning';

    public function label(): string
    {
        return match ($this) {
            self::QuickUpdate => 'quick update',
            self::ReviewPlanning => 'review & planning meeting',
        };
    }
}
