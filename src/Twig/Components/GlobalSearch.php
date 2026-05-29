<?php

namespace App\Twig\Components;

use App\Entity\Child;
use App\Entity\Outing;
use App\Service\ActiveSeasonProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
#[IsGranted('ROLE_DIRECTOR')]
final class GlobalSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveSeasonProvider $seasonProvider,
    ) {
    }

    /**
     * @return list<array{type: string, label: string, meta: string, route: string, parameters: array{id: int}}>
     */
    public function getResults(): array
    {
        $query = trim($this->query);
        if (strlen($query) < 2) {
            return [];
        }

        $season = $this->seasonProvider->getActiveSeason();
        $term = '%'.strtolower($query).'%';
        $results = [];

        $children = $this->entityManager->getRepository(Child::class)->createQueryBuilder('child')
            ->andWhere('child.season = :season')
            ->andWhere('LOWER(child.firstName) LIKE :term OR LOWER(child.lastName) LIKE :term')
            ->setParameter('season', $season)
            ->setParameter('term', $term)
            ->orderBy('child.lastName', 'ASC')
            ->addOrderBy('child.firstName', 'ASC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        foreach ($children as $child) {
            \assert($child instanceof Child);
            $results[] = [
                'type' => 'Enfant',
                'label' => $child->getFullName(),
                'meta' => $child->getAgeGroupLabel(),
                'route' => 'app_child_show',
                'parameters' => ['id' => (int) $child->getId()],
            ];
        }

        $outings = $this->entityManager->getRepository(Outing::class)->createQueryBuilder('outing')
            ->andWhere('outing.season = :season')
            ->andWhere('LOWER(outing.destination) LIKE :term OR LOWER(outing.number) LIKE :term')
            ->setParameter('season', $season)
            ->setParameter('term', $term)
            ->orderBy('outing.departureAt', 'ASC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        foreach ($outings as $outing) {
            \assert($outing instanceof Outing);
            $results[] = [
                'type' => 'Sortie',
                'label' => $outing->getDestination(),
                'meta' => sprintf('Sortie %s - %s', $outing->getNumber(), $outing->getDepartureAt()->format('d/m H:i')),
                'route' => 'app_outing_show',
                'parameters' => ['id' => (int) $outing->getId()],
            ];
        }

        return $results;
    }
}
