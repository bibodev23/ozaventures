<?php

namespace App\Enum;

enum AgeGroup: string
{
    case Little = '3-5';
    case Big = '6-12';

    public function label(): string
    {
        return match ($this) {
            self::Little => '3-5 ans',
            self::Big => '6-12 ans',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return [
            self::Little->label() => self::Little->value,
            self::Big->label() => self::Big->value,
        ];
    }
}
