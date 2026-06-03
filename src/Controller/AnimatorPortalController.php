<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\AnimatorWorkShift;
use App\Entity\DailyTaskAssignment;
use App\Entity\Outing;
use App\Entity\Season;
use App\Entity\User;
use App\Enum\OutingStatus;
use App\Service\ActiveSeasonProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-espace')]
#[IsGranted('ROLE_ANIMATOR')]
class AnimatorPortalController extends AbstractController
{
    #[Route('', name: 'app_animator_portal')]
    public function index(ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getAnimator() instanceof Animator) {
            throw $this->createAccessDeniedException();
        }

        $animator = $user->getAnimator();
        $season = $seasonProvider->getActiveSeason();
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);
        $weekStart = $this->weekStart($this->dateInsideSeason($today, $season));
        $weekEnd = $weekStart->modify('+4 days');
        $shifts = $this->findWeekShifts($entityManager, $season, $animator, $weekStart, $weekEnd);
        $weekDays = $this->buildWeekDays($weekStart, $shifts);
        $weeklyTotalMinutes = array_reduce(
            $shifts,
            static fn (int $total, AnimatorWorkShift $shift): int => $total + $shift->getWorkedMinutes(),
            0,
        );

        return $this->render('animator_portal/index.html.twig', [
            'animator' => $animator,
            'user' => $user,
            'season' => $season,
            'today' => $today,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_days' => $weekDays,
            'weekly_total_label' => $this->formatMinutes($weeklyTotalMinutes),
            'today_tasks' => $this->findTodayTasks($entityManager, $season, $animator, $today),
            'upcoming_outings' => $this->findUpcomingOutings($entityManager, $season, $animator, $today),
            'upcoming_outings_count' => $this->outingCount($entityManager, $season, $animator, null, $today),
            'pending_outings_count' => $this->outingCount($entityManager, $season, $animator, OutingStatus::Pending->value),
            'validated_outings_count' => $this->outingCount($entityManager, $season, $animator, OutingStatus::Validated->value),
        ]);
    }

    /**
     * @return list<AnimatorWorkShift>
     */
    private function findWeekShifts(EntityManagerInterface $entityManager, Season $season, Animator $animator, \DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd): array
    {
        return $entityManager->getRepository(AnimatorWorkShift::class)
            ->createQueryBuilder('shift')
            ->andWhere('shift.season = :season')
            ->andWhere('shift.animator = :animator')
            ->andWhere('shift.workDate BETWEEN :start AND :end')
            ->setParameter('season', $season)
            ->setParameter('animator', $animator)
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->orderBy('shift.workDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<AnimatorWorkShift> $shifts
     *
     * @return list<array{date: \DateTimeImmutable, label: string, shift: AnimatorWorkShift|null}>
     */
    private function buildWeekDays(\DateTimeImmutable $weekStart, array $shifts): array
    {
        $indexedShifts = [];
        foreach ($shifts as $shift) {
            $indexedShifts[$shift->getWorkDate()->format('Y-m-d')] = $shift;
        }

        $days = [];
        $labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven'];
        for ($index = 0; $index < 5; ++$index) {
            $date = $weekStart->modify(sprintf('+%d days', $index));
            $days[] = [
                'date' => $date,
                'label' => $labels[$index],
                'shift' => $indexedShifts[$date->format('Y-m-d')] ?? null,
            ];
        }

        return $days;
    }

    /**
     * @return list<DailyTaskAssignment>
     */
    private function findTodayTasks(EntityManagerInterface $entityManager, Season $season, Animator $animator, \DateTimeImmutable $today): array
    {
        return $entityManager->getRepository(DailyTaskAssignment::class)
            ->createQueryBuilder('assignment')
            ->innerJoin('assignment.animators', 'animator')
            ->andWhere('assignment.season = :season')
            ->andWhere('assignment.taskDate = :today')
            ->andWhere('animator = :animator')
            ->setParameter('season', $season)
            ->setParameter('today', $today)
            ->setParameter('animator', $animator)
            ->orderBy('assignment.taskType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Outing>
     */
    private function findUpcomingOutings(EntityManagerInterface $entityManager, Season $season, Animator $animator, \DateTimeImmutable $today): array
    {
        return $entityManager->getRepository(Outing::class)
            ->createQueryBuilder('outing')
            ->select('DISTINCT outing')
            ->leftJoin('outing.animators', 'assignedAnimator')
            ->addSelect('assignedAnimator')
            ->leftJoin('outing.children', 'child')
            ->addSelect('child')
            ->andWhere('outing.season = :season')
            ->andWhere('outing.departureAt >= :today')
            ->andWhere('outing.createdBy = :animator OR assignedAnimator = :animator')
            ->setParameter('season', $season)
            ->setParameter('today', $today)
            ->setParameter('animator', $animator)
            ->orderBy('outing.departureAt', 'ASC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();
    }

    private function outingCount(EntityManagerInterface $entityManager, Season $season, Animator $animator, ?string $status = null, ?\DateTimeImmutable $from = null): int
    {
        $queryBuilder = $entityManager->getRepository(Outing::class)
            ->createQueryBuilder('outing')
            ->select('COUNT(DISTINCT outing.id)')
            ->leftJoin('outing.animators', 'assignedAnimator')
            ->andWhere('outing.season = :season')
            ->andWhere('outing.createdBy = :animator OR assignedAnimator = :animator')
            ->setParameter('season', $season)
            ->setParameter('animator', $animator);

        if ($status !== null) {
            $queryBuilder
                ->andWhere('outing.status = :status')
                ->setParameter('status', $status);
        }

        if ($from instanceof \DateTimeImmutable) {
            $queryBuilder
                ->andWhere('outing.departureAt >= :from')
                ->setParameter('from', $from);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function dateInsideSeason(\DateTimeImmutable $date, Season $season): \DateTimeImmutable
    {
        if ($date < $season->getStartsAt()) {
            return $season->getStartsAt();
        }

        if ($date > $season->getEndsAt()) {
            return $season->getEndsAt();
        }

        return $date;
    }

    private function weekStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify('monday this week')->setTime(0, 0);
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
