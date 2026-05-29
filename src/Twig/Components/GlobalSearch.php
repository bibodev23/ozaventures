<?php

namespace App\Twig\Components;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
#[IsGranted('ROLE_DIRECTOR')]
final class GlobalSearch
{
}
