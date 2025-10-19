<?php

namespace App\Enum;

/**
 * Enum class representing the status of a invoice.
 */

use Symfony\Component\Form\FormTypeInterfac;

enum TaskType: string
{
    case LISTINGMATIN = 'listingmatin';
    case LISTINGMIDI = 'listingmidi';
    case LISTINGSOIR = 'listingsoir';
    case CANTINE = 'cantine';
    case GOUTER = 'gouter';
    case VAISSELLE = 'vaisselle';
    public function label(): string
    {
        return match ($this) {
            self::LISTINGMATIN => 'Listing du matin',
            self::LISTINGMIDI => 'Listing du midi',
            self::LISTINGSOIR => 'Listing du soir',
            self::CANTINE => 'Cantine',
            self::GOUTER => 'Goûter',
            self::VAISSELLE => 'Vaisselle',
        };
    }
}
