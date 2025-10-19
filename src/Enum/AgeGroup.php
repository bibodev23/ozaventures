<?php

namespace App\Enum;

/**
 * Enum class representing tkid's ageGroup
 */


enum AgeGroup: string
{
    case BABIES = "3-5";
    case CHILDREN = "6-12";

    public function getLabel(): string
    {
        return match ($this) {
            self::BABIES => "3-5 ans",
            self::CHILDREN => "6-12 ans",
        };
    }
}