<?php

namespace App\Enum;

enum DailyTaskType: string
{
    case MorningCare = 'morning_care';
    case MorningListing = 'morning_listing';
    case NoonListing = 'noon_listing';
    case AfternoonListing = 'afternoon_listing';
    case SnackPreparation = 'snack_preparation';
    case Dishes = 'dishes';
    case CanteenSmall = 'canteen_small';
    case CanteenBig = 'canteen_big';

    public function label(): string
    {
        return match ($this) {
            self::MorningCare => 'Garderie du matin',
            self::MorningListing => 'Listing 8h30 - 9h',
            self::NoonListing => 'Listing 13h30 - 14h',
            self::AfternoonListing => 'Listing 17h30 - 18h',
            self::SnackPreparation => 'Préparation du goûter',
            self::Dishes => 'Vaisselle',
            self::CanteenSmall => 'Cantine 3-5 ans',
            self::CanteenBig => 'Cantine 6-12 ans',
        };
    }

    public function timeLabel(): string
    {
        return match ($this) {
            self::MorningCare => '7h15 - 8h30',
            self::MorningListing => '8h30 - 9h',
            self::NoonListing => '13h30 - 14h',
            self::AfternoonListing => '17h30 - 18h',
            self::SnackPreparation => 'Avant le goûter',
            self::Dishes => 'Après repas / goûter',
            self::CanteenSmall,
            self::CanteenBig => 'Temps de cantine',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MorningCare => 'Accueil des enfants présents avant le début de journée.',
            self::MorningListing => 'Pointage des présences du matin, demi-journée/journée, avec ou sans cantine.',
            self::NoonListing => "Pointage des présences de l'après-midi et vérification des informations famille.",
            self::AfternoonListing => 'Pointage du départ du soir.',
            self::SnackPreparation => 'Préparation et organisation du goûter.',
            self::Dishes => 'Rangement et vaisselle.',
            self::CanteenSmall => 'Encadrement cantine du groupe 3-5 ans.',
            self::CanteenBig => 'Encadrement cantine du groupe 6-12 ans.',
        };
    }

    public function groupLabel(): ?string
    {
        return match ($this) {
            self::CanteenSmall => 'Groupe 3-5 ans',
            self::CanteenBig => 'Groupe 6-12 ans',
            default => null,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::MorningCare => 'badge-blue',
            self::MorningListing,
            self::NoonListing,
            self::AfternoonListing => 'badge-waiting',
            self::SnackPreparation => 'badge-success',
            self::Dishes => 'badge-danger',
            self::CanteenSmall,
            self::CanteenBig => 'badge-canteen',
        };
    }
}
