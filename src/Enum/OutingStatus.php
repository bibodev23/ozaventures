<?php

namespace App\Enum;

enum OutingStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Validated => 'Validée',
            self::Refused => 'Refusée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-waiting',
            self::Validated => 'badge-success',
            self::Refused => 'badge-danger',
        };
    }
}
