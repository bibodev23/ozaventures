<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\Child;
use App\Entity\Outing;
use App\Service\ActiveSeasonProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_DIRECTOR')]
class GlobalSearchController extends AbstractController
{
    #[Route('/recherche/autocomplete', name: 'app_global_search_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = trim((string) $request->query->get('query', ''));
        if (mb_strlen($query) < 2) {
            return $this->json(['results' => []]);
        }

        $season = $seasonProvider->getActiveSeason();
        $term = '%' . mb_strtolower($query) . '%';
        $results = [];

        $children = $entityManager->getRepository(Child::class)->createQueryBuilder('child')
            ->andWhere('child.season = :season')
            ->andWhere('LOWER(child.firstName) LIKE :term OR LOWER(child.lastName) LIKE :term')
            ->setParameter('season', $season)
            ->setParameter('term', $term)
            ->orderBy('child.lastName', 'ASC')
            ->addOrderBy('child.firstName', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($children as $child) {
            \assert($child instanceof Child);
            $results[] = [
                'value' => $this->generateUrl('app_child_show', ['id' => $child->getId()]),
                'text' => sprintf('Enfant - %s - %s', $child->getFullName(), $child->getAgeGroupLabel()),
            ];
        }

        $animators = $entityManager->getRepository(Animator::class)->createQueryBuilder('animator')
            ->andWhere('animator.active = true')
            ->andWhere('LOWER(animator.firstName) LIKE :term OR LOWER(animator.lastName) LIKE :term OR LOWER(animator.username) LIKE :term')
            ->setParameter('term', $term)
            ->orderBy('animator.lastName', 'ASC')
            ->addOrderBy('animator.firstName', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($animators as $animator) {
            \assert($animator instanceof Animator);
            $results[] = [
                'value' => $this->generateUrl('app_animator_edit', ['id' => $animator->getId()]),
                'text' => sprintf('Animateur - %s - %s', $animator->getFullName(), $animator->getAgeGroupLabel()),
            ];
        }

        $outings = $entityManager->getRepository(Outing::class)->createQueryBuilder('outing')
            ->andWhere('outing.season = :season')
            ->andWhere('LOWER(outing.destination) LIKE :term OR LOWER(outing.number) LIKE :term OR LOWER(outing.transportMode) LIKE :term')
            ->setParameter('season', $season)
            ->setParameter('term', $term)
            ->orderBy('outing.departureAt', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($outings as $outing) {
            \assert($outing instanceof Outing);
            $results[] = [
                'value' => $this->generateUrl('app_outing_show', ['id' => $outing->getId()]),
                'text' => sprintf('Sortie - %s - %s à %s', $outing->getDestination(), $outing->getDepartureAt()->format('d/m'), $outing->getDepartureAt()->format('H:i')),
            ];
        }

        return $this->json([
            'results' => array_slice($results, 0, 12),
            'next_page' => null,
        ]);
    }
}
