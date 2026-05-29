<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Outing;
use App\Entity\Season;
use App\Enum\AgeGroup;
use App\Enum\OutingStatus;
use App\Form\ChildType;
use App\Service\ActiveSeasonProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/enfants')]
#[IsGranted('ROLE_DIRECTION')]
class ChildController extends AbstractController
{
    #[Route('', name: 'app_children')]
    public function index(ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $children = $entityManager->getRepository(Child::class)->findBy(
            ['season' => $season],
            ['ageGroup' => 'ASC', 'lastName' => 'ASC', 'firstName' => 'ASC']
        );

        return $this->render('children/index.html.twig', [
            'season' => $season,
            'children' => $children,
            'groups' => AgeGroup::cases(),
            'participation_counts' => $this->participationSummary($season, $entityManager),
        ]);
    }

    #[Route('/nouveau', name: 'app_child_new')]
    public function new(Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        $child = (new Child())->setSeason($seasonProvider->getActiveSeason());
        $form = $this->createForm(ChildType::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($child);
            $entityManager->flush();

            $this->addFlash('success', 'Enfant ajouté.');

            return $this->redirectToRoute('app_children');
        }

        return $this->render('children/form.html.twig', [
            'child' => $child,
            'form' => $form,
            'title' => 'Ajouter un enfant',
        ]);
    }

    #[Route('/{id}', name: 'app_child_show', requirements: ['id' => '\d+'])]
    public function show(Child $child, EntityManagerInterface $entityManager): Response
    {
        $season = $child->getSeason();
        if (!$season instanceof Season) {
            throw $this->createNotFoundException('Saison introuvable pour cet enfant.');
        }

        $outings = $entityManager->getRepository(Outing::class)->createQueryBuilder('outing')
            ->innerJoin('outing.children', 'child')
            ->leftJoin('outing.animators', 'animator')
            ->addSelect('animator')
            ->andWhere('outing.season = :season')
            ->andWhere('child = :child')
            ->setParameter('season', $season)
            ->setParameter('child', $child)
            ->orderBy('outing.departureAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('children/show.html.twig', [
            'child' => $child,
            'season' => $season,
            'outings' => $outings,
            'stats' => $this->childStats($outings),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_child_edit', requirements: ['id' => '\d+'])]
    public function edit(Child $child, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ChildType::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Enfant mis à jour.');

            return $this->redirectToRoute('app_child_show', ['id' => $child->getId()]);
        }

        return $this->render('children/form.html.twig', [
            'child' => $child,
            'form' => $form,
            'title' => 'Modifier un enfant',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function participationSummary(Season $season, EntityManagerInterface $entityManager): array
    {
        $rows = $entityManager->getRepository(Child::class)->createQueryBuilder('child')
            ->select('child.id AS childId')
            ->addSelect('COUNT(outing.id) AS outingCount')
            ->leftJoin('child.outings', 'outing', 'WITH', 'outing.season = :season AND outing.status != :refused')
            ->andWhere('child.season = :season')
            ->setParameter('season', $season)
            ->setParameter('refused', OutingStatus::Refused->value)
            ->groupBy('child.id')
            ->getQuery()
            ->getArrayResult();

        $byChild = [];
        foreach ($rows as $row) {
            $childId = (int) $row['childId'];
            $outingCount = (int) $row['outingCount'];
            $byChild[$childId] = $outingCount;
        }

        return $byChild;
    }

    /**
     * @param list<Outing> $outings
     *
     * @return array<string, mixed>
     */
    private function childStats(array $outings): array
    {
        $validatedCount = 0;
        $pendingCount = 0;
        $refusedCount = 0;
        $effectiveCount = 0;
        $lastEffectiveOuting = null;

        foreach ($outings as $outing) {
            if (!$outing instanceof Outing) {
                continue;
            }

            if ($outing->getStatus() === OutingStatus::Validated->value) {
                ++$validatedCount;
            } elseif ($outing->getStatus() === OutingStatus::Pending->value) {
                ++$pendingCount;
            } elseif ($outing->getStatus() === OutingStatus::Refused->value) {
                ++$refusedCount;
            }

            if ($outing->getStatus() !== OutingStatus::Refused->value) {
                ++$effectiveCount;
                if ($lastEffectiveOuting === null) {
                    $lastEffectiveOuting = $outing;
                }
            }
        }

        return [
            'total_count' => count($outings),
            'effective_count' => $effectiveCount,
            'validated_count' => $validatedCount,
            'pending_count' => $pendingCount,
            'refused_count' => $refusedCount,
            'last_outing' => $lastEffectiveOuting,
        ];
    }
}
