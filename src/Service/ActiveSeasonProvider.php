<?php

namespace App\Service;

use App\Entity\Season;
use Doctrine\ORM\EntityManagerInterface;

class ActiveSeasonProvider
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getActiveSeason(): Season
    {
        $season = $this->entityManager->getRepository(Season::class)->findOneBy(['active' => true], ['startsAt' => 'DESC']);

        if ($season instanceof Season) {
            return $season;
        }

        $season = (new Season())
            ->setName('Juillet ' . (new \DateTimeImmutable())->format('Y'))
            ->setStartsAt(new \DateTimeImmutable('first day of july this year'))
            ->setEndsAt(new \DateTimeImmutable('last day of july this year'))
            ->setActive(true);

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        return $season;
    }
}
