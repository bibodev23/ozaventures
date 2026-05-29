<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\Child;
use App\Entity\Outing;
use App\Enum\OutingStatus;
use App\Service\ActiveSeasonProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[IsGranted('ROLE_DIRECTION')]
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

        return $this->render('dashboard/index.html.twig', [
            'season' => $season,
            'children_count' => $entityManager->getRepository(Child::class)->count(['season' => $season]),
            'animators_count' => $entityManager->getRepository(Animator::class)->count(['active' => true]),
            'total_outings_count' => $outingRepository->count(['season' => $season]),
            'pending_count' => $outingRepository->count(['season' => $season, 'status' => OutingStatus::Pending->value]),
            'validated_count' => $outingRepository->count(['season' => $season, 'status' => OutingStatus::Validated->value]),
            'refused_count' => $outingRepository->count(['season' => $season, 'status' => OutingStatus::Refused->value]),
            'season_progress' => (int) round(($elapsedSeasonDays / $totalSeasonDays) * 100),
            'season_days_remaining' => $today <= $season->getEndsAt() ? (int) $today->diff($season->getEndsAt())->format('%a') : 0,
            'pending_outings' => $pendingOutings,
            'upcoming_outings' => $upcomingOutings,
            'next_outing' => $upcomingOutings[0] ?? null,
            'children_age_chart' => $childrenAgeChart,
        ]);
    }
}
