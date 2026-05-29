<?php

namespace App\Enum;

enum UserRole: string
{
    case Director = 'director';
    case Animator = 'animator';

    public function securityRole(): string
    {
        return match ($this) {
            self::Director => 'ROLE_DIRECTOR',
            self::Animator => 'ROLE_ANIMATOR',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Director => 'Direction',
            self::Animator => 'Animateur',
        };
    }
}
