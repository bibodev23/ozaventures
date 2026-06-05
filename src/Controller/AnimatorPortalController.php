<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\AnimatorWorkShift;
use App\Entity\Child;
use App\Entity\DailyTaskAssignment;
use App\Entity\Outing;
use App\Entity\Season;
use App\Entity\User;
use App\Enum\AgeGroup;
use App\Enum\OutingStatus;
use App\Service\ActiveSeasonProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-espace')]
#[IsGranted('ROLE_ANIMATOR')]
class AnimatorPortalController extends AbstractController
{
    private const OPENING_START_MONTH = 7;
    private const OPENING_START_DAY = 6;
    private const OPENING_END_MONTH = 8;
    private const OPENING_END_DAY = 28;

    #[Route('', name: 'app_animator_portal')]
    public function index(ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        [$user, $animator] = $this->currentAnimator();
        $season = $seasonProvider->getActiveSeason();
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);
        $weekStart = $this->weekStart($this->dateInsideSeason($today, $season));
        $weekEnd = $weekStart->modify('+4 days');
        $shifts = $this->findWeekShifts($entityManager, $season, $animator, $weekStart, $weekEnd);
        $weekDays = $this->buildWeekDays($weekStart, $shifts);
        $weeklyTotalMinutes = $this->sumShiftMinutes($shifts);

        return $this->render('animator_portal/index.html.twig', [
            'animator' => $animator,
            'user' => $user,
            'season' => $season,
            'today' => $today,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_days' => $weekDays,
            'weekly_total_label' => $this->formatMinutes($weeklyTotalMinutes),
            'today_tasks' => $this->findDayTasks($entityManager, $season, $animator, $today),
            'upcoming_outings' => $this->findOutings($entityManager, $season, $animator, $today, 4),
            'upcoming_outings_count' => $this->outingCount($entityManager, $season, $animator, null, $today),
            'pending_outings_count' => $this->outingCount($entityManager, $season, $animator, OutingStatus::Pending->value),
            'validated_outings_count' => $this->outingCount($entityManager, $season, $animator, OutingStatus::Validated->value),
        ]);
    }

    #[Route('/horaires', name: 'app_animator_portal_schedules')]
    public function schedules(Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        [, $animator] = $this->currentAnimator();
        $season = $seasonProvider->getActiveSeason();
        $weekStart = $this->selectedWeekStart($request, $season);
        $weekEnd = $weekStart->modify('+4 days');
        $shifts = $this->findWeekShifts($entityManager, $season, $animator, $weekStart, $weekEnd);
        $weekDays = $this->buildWeekDays($weekStart, $shifts);
        $weekTasks = $this->findWeekTasks($entityManager, $season, $animator, $weekStart, $weekEnd);

        return $this->render('animator_portal/schedules.html.twig', [
            'animator' => $animator,
            'season' => $season,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_days' => $weekDays,
            'planning_days' => $this->buildPlanningDays($weekStart, $weekTasks),
            'weekly_total_label' => $this->formatMinutes($this->sumShiftMinutes($shifts)),
            'previous_week' => $this->previousWeekStart($weekStart, $season),
            'next_week' => $this->nextWeekStart($weekStart, $season),
        ]);
    }

    #[Route('/planning', name: 'app_animator_portal_planning')]
    public function planning(Request $request): Response
    {
        $week = trim((string) $request->query->get('week', ''));

        return $this->redirectToRoute(
            'app_animator_portal_schedules',
            $week !== '' ? ['week' => $week] : [],
            Response::HTTP_SEE_OTHER,
        );
    }

    #[Route('/sorties', name: 'app_animator_portal_outings')]
    public function outings(Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        [, $animator] = $this->currentAnimator();
        $season = $seasonProvider->getActiveSeason();
        $selectedStatus = $this->selectedStatus($request);

        return $this->render('animator_portal/outings.html.twig', [
            'animator' => $animator,
            'season' => $season,
            'outings' => $this->findOutings($entityManager, $season, $animator, null, null, $selectedStatus),
            'selected_status' => $selectedStatus,
            'pending_count' => $this->outingCount($entityManager, $season, $animator, OutingStatus::Pending->value),
            'validated_count' => $this->outingCount($entityManager, $season, $animator, OutingStatus::Validated->value),
            'refused_count' => $this->outingCount($entityManager, $season, $animator, OutingStatus::Refused->value),
        ]);
    }

    #[Route('/sorties/{id}', name: 'app_animator_portal_outing_show', requirements: ['id' => '\\d+'])]
    public function outingShow(Outing $outing): Response
    {
        [, $animator] = $this->currentAnimator();
        if (!$this->animatorCanViewOuting($animator, $outing)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('animator_portal/outing_show.html.twig', [
            'animator' => $animator,
            'outing' => $outing,
        ]);
    }

    #[Route('/enfants', name: 'app_animator_portal_children')]
    public function children(ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $children = $entityManager->getRepository(Child::class)->findBy(
            ['season' => $season],
            ['ageGroup' => 'ASC', 'lastName' => 'ASC', 'firstName' => 'ASC'],
        );

        return $this->render('animator_portal/children.html.twig', [
            'season' => $season,
            'children' => $children,
            'groups' => AgeGroup::cases(),
        ]);
    }

    #[Route('/enfants/{id}', name: 'app_animator_portal_child_show', requirements: ['id' => '\\d+'])]
    public function childShow(Child $child, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        $season = $seasonProvider->getActiveSeason();
        if ($child->getSeason()?->getId() !== $season->getId()) {
            throw $this->createNotFoundException('Enfant introuvable sur la saison active.');
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

        return $this->render('animator_portal/child_show.html.twig', [
            'child' => $child,
            'season' => $season,
            'outings' => $outings,
            'stats' => $this->childStats($outings),
        ]);
    }

    /**
     * @return array{0: User, 1: Animator}
     */
    private function currentAnimator(): array
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getAnimator() instanceof Animator) {
            throw $this->createAccessDeniedException();
        }

        return [$user, $user->getAnimator()];
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
    private function findDayTasks(EntityManagerInterface $entityManager, Season $season, Animator $animator, \DateTimeImmutable $day): array
    {
        return $entityManager->getRepository(DailyTaskAssignment::class)
            ->createQueryBuilder('assignment')
            ->innerJoin('assignment.animators', 'animator')
            ->andWhere('assignment.season = :season')
            ->andWhere('assignment.taskDate = :day')
            ->andWhere('animator = :animator')
            ->setParameter('season', $season)
            ->setParameter('day', $day)
            ->setParameter('animator', $animator)
            ->orderBy('assignment.taskType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<DailyTaskAssignment>
     */
    private function findWeekTasks(EntityManagerInterface $entityManager, Season $season, Animator $animator, \DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd): array
    {
        return $entityManager->getRepository(DailyTaskAssignment::class)
            ->createQueryBuilder('assignment')
            ->innerJoin('assignment.animators', 'animator')
            ->andWhere('assignment.season = :season')
            ->andWhere('assignment.taskDate BETWEEN :start AND :end')
            ->andWhere('animator = :animator')
            ->setParameter('season', $season)
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekEnd)
            ->setParameter('animator', $animator)
            ->orderBy('assignment.taskDate', 'ASC')
            ->addOrderBy('assignment.taskType', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<DailyTaskAssignment> $tasks
     *
     * @return list<array{date: \DateTimeImmutable, label: string, tasks: list<DailyTaskAssignment>}>
     */
    private function buildPlanningDays(\DateTimeImmutable $weekStart, array $tasks): array
    {
        $indexedTasks = [];
        foreach ($tasks as $task) {
            $indexedTasks[$task->getTaskDate()->format('Y-m-d')][] = $task;
        }

        $days = [];
        $labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven'];
        for ($index = 0; $index < 5; ++$index) {
            $date = $weekStart->modify(sprintf('+%d days', $index));
            $days[] = [
                'date' => $date,
                'label' => $labels[$index],
                'tasks' => $indexedTasks[$date->format('Y-m-d')] ?? [],
            ];
        }

        return $days;
    }

    /**
     * @return list<Outing>
     */
    private function findOutings(EntityManagerInterface $entityManager, Season $season, Animator $animator, ?\DateTimeImmutable $from = null, ?int $limit = null, ?string $status = null): array
    {
        $queryBuilder = $entityManager->getRepository(Outing::class)
            ->createQueryBuilder('outing')
            ->select('DISTINCT outing')
            ->leftJoin('outing.animators', 'assignedAnimator')
            ->addSelect('assignedAnimator')
            ->leftJoin('outing.children', 'child')
            ->addSelect('child')
            ->andWhere('outing.season = :season')
            ->andWhere('outing.createdBy = :animator OR assignedAnimator = :animator')
            ->setParameter('season', $season)
            ->setParameter('animator', $animator);

        if ($from instanceof \DateTimeImmutable) {
            $queryBuilder
                ->andWhere('outing.departureAt >= :from')
                ->setParameter('from', $from)
                ->orderBy('outing.departureAt', 'ASC');
        } else {
            $queryBuilder->orderBy('outing.departureAt', 'DESC');
        }

        if ($status !== null) {
            $queryBuilder
                ->andWhere('outing.status = :status')
                ->setParameter('status', $status);
        }

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult();
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

    private function animatorCanViewOuting(Animator $animator, Outing $outing): bool
    {
        if ($outing->getCreatedBy()?->getId() === $animator->getId()) {
            return true;
        }

        foreach ($outing->getAnimators() as $assignedAnimator) {
            if ($assignedAnimator->getId() === $animator->getId()) {
                return true;
            }
        }

        return false;
    }

    private function selectedStatus(Request $request): ?string
    {
        $status = trim((string) $request->query->get('status', ''));
        $allowed = array_map(static fn (OutingStatus $case): string => $case->value, OutingStatus::cases());

        return in_array($status, $allowed, true) ? $status : null;
    }

    private function selectedWeekStart(Request $request, Season $season): \DateTimeImmutable
    {
        $week = trim((string) $request->query->get('week', ''));
        if ($week === '') {
            return $this->weekStart($this->dateInsideSeason(new \DateTimeImmutable('today'), $season));
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $week);
        if (!$date instanceof \DateTimeImmutable) {
            $this->addFlash('error', 'Semaine invalide.');

            return $this->weekStart($this->dateInsideSeason(new \DateTimeImmutable('today'), $season));
        }

        return $this->weekStart($this->dateInsideSeason($date, $season));
    }

    private function previousWeekStart(\DateTimeImmutable $weekStart, Season $season): ?\DateTimeImmutable
    {
        $previous = $weekStart->modify('-7 days');

        return $previous >= $this->weekStart($this->openingStart($season)) ? $previous : null;
    }

    private function nextWeekStart(\DateTimeImmutable $weekStart, Season $season): ?\DateTimeImmutable
    {
        $next = $weekStart->modify('+7 days');

        return $next <= $this->weekStart($this->openingEnd($season)) ? $next : null;
    }

    private function dateInsideSeason(\DateTimeImmutable $date, Season $season): \DateTimeImmutable
    {
        $date = $date->setTime(0, 0);
        $openingStart = $this->openingStart($season);
        $openingEnd = $this->openingEnd($season);

        if ($date < $openingStart) {
            return $openingStart;
        }

        if ($date > $openingEnd) {
            return $openingEnd;
        }

        return $date;
    }

    private function openingStart(Season $season): \DateTimeImmutable
    {
        $date = $season->getStartsAt()->setDate(
            (int) $season->getStartsAt()->format('Y'),
            self::OPENING_START_MONTH,
            self::OPENING_START_DAY,
        )->setTime(0, 0);

        return $date < $season->getStartsAt() ? $season->getStartsAt() : $date;
    }

    private function openingEnd(Season $season): \DateTimeImmutable
    {
        $date = $season->getStartsAt()->setDate(
            (int) $season->getStartsAt()->format('Y'),
            self::OPENING_END_MONTH,
            self::OPENING_END_DAY,
        )->setTime(0, 0);

        return $date > $season->getEndsAt() ? $season->getEndsAt() : $date;
    }

    private function weekStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify('monday this week')->setTime(0, 0);
    }

    /**
     * @param list<AnimatorWorkShift> $shifts
     */
    private function sumShiftMinutes(array $shifts): int
    {
        return array_reduce(
            $shifts,
            static fn (int $total, AnimatorWorkShift $shift): int => $total + $shift->getWorkedMinutes(),
            0,
        );
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
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
