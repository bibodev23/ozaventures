<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\Child;
use App\Entity\Outing;
use App\Enum\AgeGroup;
use App\Enum\OutingStatus;
use App\Service\ActiveSeasonProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[IsGranted('ROLE_DIRECTOR')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager, ChartBuilderInterface $chartBuilder): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $outingRepository = $entityManager->getRepository(Outing::class);
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        $upcomingOutings = $outingRepository->createQueryBuilder('outing')
            ->andWhere('outing.season = :season')
            ->andWhere('outing.departureAt >= :today')
            ->setParameter('season', $season)
            ->setParameter('today', $today)
            ->orderBy('outing.departureAt', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $pendingOutings = $outingRepository->createQueryBuilder('outing')
            ->andWhere('outing.season = :season')
            ->andWhere('outing.status = :status')
            ->setParameter('season', $season)
            ->setParameter('status', OutingStatus::Pending->value)
            ->orderBy('outing.departureAt', 'ASC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        $childAgeRows = $entityManager->getRepository(Child::class)->createQueryBuilder('child')
            ->select('child.age AS age, COUNT(child.id) AS total')
            ->andWhere('child.season = :season')
            ->andWhere('child.age IS NOT NULL')
            ->andWhere('child.age BETWEEN 3 AND 12')
            ->setParameter('season', $season)
            ->groupBy('child.age')
            ->orderBy('child.age', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $ageCounts = array_fill_keys(range(3, 12), 0);
        foreach ($childAgeRows as $row) {
            $age = (int) $row['age'];
            if (isset($ageCounts[$age])) {
                $ageCounts[$age] = (int) $row['total'];
            }
        }

        $childrenAgeChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $childrenAgeChart->setData([
            'labels' => array_map(static fn (int $age): string => sprintf('%d ans', $age), array_keys($ageCounts)),
            'datasets' => [
                [
                    'label' => 'Enfants',
                    'data' => array_values($ageCounts),
                    'backgroundColor' => ['#c7dcff', '#dff3ec', '#9edbc3', '#ffd6c8', '#e9e1ff', '#f7e7b7', '#cdeade', '#f7d6e6', '#d7f0ff', '#e2f4d6'],
                    'borderColor' => '#fffdfa',
                    'borderWidth' => 2,
                    'borderRadius' => 18,
                    'barPercentage' => 0.72,
                    'categoryPercentage' => 0.72,
                ],
            ],
        ]);
        $childrenAgeChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'displayColors' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'color' => '#8c8392',
                        'font' => [
                            'weight' => 800,
                        ],
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'color' => '#8c8392',
                    ],
                    'grid' => [
                        'color' => 'rgba(223, 209, 200, .55)',
                    ],
                ],
            ],
        ]);

        $totalSeasonDays = max(1, ((int) $season->getStartsAt()->diff($season->getEndsAt())->format('%a')) + 1);
        if ($today < $season->getStartsAt()) {
            $elapsedSeasonDays = 0;
        } elseif ($today > $season->getEndsAt()) {
            $elapsedSeasonDays = $totalSeasonDays;
        } else {
            $elapsedSeasonDays = ((int) $season->getStartsAt()->diff($today)->format('%a')) + 1;
        }

        $childrenCount = $entityManager->getRepository(Child::class)->count(['season' => $season]);
        $animatorsCount = $entityManager->getRepository(Animator::class)->count(['active' => true]);
        $totalOutingsCount = $outingRepository->count(['season' => $season]);
        $pendingCount = $outingRepository->count(['season' => $season, 'status' => OutingStatus::Pending->value]);
        $validatedCount = $outingRepository->count(['season' => $season, 'status' => OutingStatus::Validated->value]);
        $refusedCount = $outingRepository->count(['season' => $season, 'status' => OutingStatus::Refused->value]);
        $completedValidatedCount = $outingRepository->createQueryBuilder('outing')
            ->select('COUNT(outing.id)')
            ->andWhere('outing.season = :season')
            ->andWhere('outing.status = :status')
            ->andWhere('outing.departureAt < :today')
            ->setParameter('season', $season)
            ->setParameter('status', OutingStatus::Validated->value)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        $seasonProgress = (int) round(($elapsedSeasonDays / $totalSeasonDays) * 100);
        $seasonState = $this->seasonState($today, $season->getStartsAt(), $season->getEndsAt(), $seasonProgress);
        $nextOuting = $upcomingOutings[0] ?? null;
        $nextOutingChecklist = $nextOuting instanceof Outing ? $this->nextOutingChecklist($nextOuting) : null;
        $operationalAlerts = $this->operationalAlerts($pendingCount, $animatorsCount, $nextOuting, $nextOutingChecklist);

        return $this->render('dashboard/index.html.twig', [
            'season' => $season,
            'children_count' => $childrenCount,
            'animators_count' => $animatorsCount,
            'total_outings_count' => $totalOutingsCount,
            'pending_count' => $pendingCount,
            'validated_count' => $validatedCount,
            'refused_count' => $refusedCount,
            'completed_validated_count' => (int) $completedValidatedCount,
            'season_progress' => $seasonProgress,
            'season_state' => $seasonState,
            'season_days_remaining' => $today <= $season->getEndsAt() ? (int) $today->diff($season->getEndsAt())->format('%a') : 0,
            'pending_outings' => $pendingOutings,
            'upcoming_outings' => $upcomingOutings,
            'next_outing' => $nextOuting,
            'next_outing_checklist' => $nextOutingChecklist,
            'operational_alerts' => $operationalAlerts,
            'children_age_chart' => $childrenAgeChart,
        ]);
    }

    /**
     * @return array{label:string, caption:string, metric_caption:string}
     */
    private function seasonState(\DateTimeImmutable $today, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, int $progress): array
    {
        if ($today < $startsAt) {
            return [
                'label' => 'Saison non commencée',
                'caption' => sprintf('Début le %s', $startsAt->format('d/m/Y')),
                'metric_caption' => 'Préparation de saison',
            ];
        }

        if ($today > $endsAt) {
            return [
                'label' => 'Saison terminée',
                'caption' => sprintf('Bilan depuis le %s', $endsAt->format('d/m/Y')),
                'metric_caption' => 'Bilan de saison',
            ];
        }

        return [
            'label' => 'Progression juillet',
            'caption' => sprintf('%d %% du séjour écoulé', $progress),
            'metric_caption' => sprintf('%d %% de séjour écoulé', $progress),
        ];
    }

    /**
     * @return array{children_count:int, animators_count:int, required_animators:int, staffing_ok:bool, missing_documents:int, allergy_count:int, photo_restricted:int, items:list<array{label:string, value:string, state:string}>}
     */
    private function nextOutingChecklist(Outing $outing): array
    {
        $children = $outing->getChildren()->toArray();
        $animatorsCount = $outing->getAnimators()->count();
        $littleChildren = 0;
        $bigChildren = 0;
        $missingDocuments = 0;
        $allergyCount = 0;
        $photoRestricted = 0;

        foreach ($children as $child) {
            if (!$child instanceof Child) {
                continue;
            }

            if ($child->getAgeGroup() === AgeGroup::Little->value) {
                ++$littleChildren;
            } else {
                ++$bigChildren;
            }

            if ($child->getLegalGuardians() === null || $child->getLegalGuardianPhones() === null) {
                ++$missingDocuments;
            }

            if ($child->hasAllergies()) {
                ++$allergyCount;
            }

            if (!$child->hasPhotoPermission()) {
                ++$photoRestricted;
            }
        }

        $requiredAnimators = $children === []
            ? 0
            : max(1, (int) ceil($littleChildren / 8) + (int) ceil($bigChildren / 12));
        $staffingOk = $requiredAnimators > 0 && $animatorsCount >= $requiredAnimators;
        $transportReady = trim($outing->getTransportMode()) !== '';
        $statusReady = $outing->getStatus() === OutingStatus::Validated->value;

        return [
            'children_count' => count($children),
            'animators_count' => $animatorsCount,
            'required_animators' => $requiredAnimators,
            'staffing_ok' => $staffingOk,
            'missing_documents' => $missingDocuments,
            'allergy_count' => $allergyCount,
            'photo_restricted' => $photoRestricted,
            'items' => [
                [
                    'label' => 'Encadrement',
                    'value' => $requiredAnimators === 0 ? 'À compléter' : sprintf('%d/%d anim.', $animatorsCount, $requiredAnimators),
                    'state' => $staffingOk ? 'ok' : 'critical',
                ],
                [
                    'label' => 'Transport',
                    'value' => $transportReady ? $outing->getTransportMode() : 'À renseigner',
                    'state' => $transportReady ? 'ok' : 'warning',
                ],
                [
                    'label' => 'Documents',
                    'value' => $missingDocuments === 0 ? 'OK' : sprintf('%d fiche(s) à vérifier', $missingDocuments),
                    'state' => $missingDocuments === 0 ? 'ok' : 'warning',
                ],
                [
                    'label' => 'Statut',
                    'value' => $outing->getStatusLabel(),
                    'state' => $statusReady ? 'ok' : 'warning',
                ],
                [
                    'label' => 'Pique-nique',
                    'value' => $outing->isPicnicRequired() ? 'À prévoir' : 'Non',
                    'state' => $outing->isPicnicRequired() ? 'warning' : 'ok',
                ],
                [
                    'label' => 'Météo',
                    'value' => 'À vérifier',
                    'state' => 'neutral',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $nextOutingChecklist
     *
     * @return list<array{level:string, title:string, message:string, href:string}>
     */
    private function operationalAlerts(int $pendingCount, int $animatorsCount, ?Outing $nextOuting, ?array $nextOutingChecklist): array
    {
        $alerts = [];

        if ($pendingCount > 0) {
            $alerts[] = [
                'level' => 'critical',
                'title' => 'Sorties à valider',
                'message' => sprintf('%d demande(s) attendent une décision direction.', $pendingCount),
                'href' => $this->generateUrl('app_outings', ['status' => OutingStatus::Pending->value]),
            ];
        }

        if ($animatorsCount === 0) {
            $alerts[] = [
                'level' => 'critical',
                'title' => 'Aucun animateur actif',
                'message' => 'Ajoute au moins un compte animateur avant la saison.',
                'href' => $this->generateUrl('app_animators'),
            ];
        }

        if ($nextOuting instanceof Outing && $nextOutingChecklist !== null) {
            if (($nextOutingChecklist['staffing_ok'] ?? false) === false) {
                $alerts[] = [
                    'level' => 'critical',
                    'title' => 'Encadrement à vérifier',
                    'message' => sprintf('%s : %d/%d animateur(s).', $nextOuting->getDestination(), $nextOutingChecklist['animators_count'], $nextOutingChecklist['required_animators']),
                    'href' => $this->generateUrl('app_outing_show', ['id' => $nextOuting->getId()]),
                ];
            }

            if (($nextOutingChecklist['missing_documents'] ?? 0) > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'Fiches enfants incomplètes',
                    'message' => sprintf('%d fiche(s) à relire avant la prochaine sortie.', $nextOutingChecklist['missing_documents']),
                    'href' => $this->generateUrl('app_outing_show', ['id' => $nextOuting->getId()]),
                ];
            }

            if (($nextOutingChecklist['allergy_count'] ?? 0) > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'Vigilance allergies',
                    'message' => sprintf('%d enfant(s) avec vigilance sur la prochaine sortie.', $nextOutingChecklist['allergy_count']),
                    'href' => $this->generateUrl('app_outing_show', ['id' => $nextOuting->getId()]),
                ];
            }
        } else {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Aucune sortie planifiée',
                'message' => 'Crée une première sortie pour lancer la préparation.',
                'href' => $this->generateUrl('app_outing_new'),
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'ok',
                'title' => 'Tout est clair',
                'message' => 'Aucune urgence opérationnelle détectée.',
                'href' => $this->generateUrl('app_outings'),
            ];
        }

        return array_slice($alerts, 0, 4);
    }
}
