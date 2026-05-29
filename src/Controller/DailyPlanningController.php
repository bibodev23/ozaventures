<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\DailyTaskAssignment;
use App\Entity\Season;
use App\Enum\AgeGroup;
use App\Enum\DailyTaskType;
use App\Service\ActiveSeasonProvider;
use App\Service\MobileNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/planning')]
#[IsGranted('ROLE_DIRECTOR')]
class DailyPlanningController extends AbstractController
{
    #[Route('', name: 'app_daily_planning')]
    public function index(Request $request, ActiveSeasonProvider $seasonProvider): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $date = (string) $request->query->get('date', '');

        if ($date === '') {
            $date = $this->defaultPlanningDate($season)->format('Y-m-d');
        } else {
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsedDate instanceof \DateTimeImmutable) {
                $this->addFlash('error', 'Date de planning invalide.');
                $date = $this->defaultPlanningDate($season)->format('Y-m-d');
            }
        }

        return $this->redirectToRoute('app_daily_planning_day', ['date' => $date]);
    }

    #[Route('/{date}', name: 'app_daily_planning_day', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function day(string $date, Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager, MobileNotificationService $notificationService): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $planningDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (!$planningDate instanceof \DateTimeImmutable) {
            $this->addFlash('error', 'Date de planning invalide.');

            return $this->redirectToRoute('app_daily_planning');
        }

        $animators = $entityManager->getRepository(Animator::class)->findBy(
            ['active' => true],
            ['lastName' => 'ASC', 'firstName' => 'ASC'],
        );
        $assignments = $this->getAssignmentsForDay($entityManager, $season, $planningDate);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('daily_planning_' . $planningDate->format('Y-m-d'), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $changedAnimators = $this->saveAssignments($request, $entityManager, $season, $planningDate, $animators, $assignments);
            $entityManager->flush();

            $this->addFlash('success', 'Planning du jour enregistré.');
            if ($request->request->getBoolean('notify_animators')) {
                $notificationResult = $notificationService->notifyDailyPlanningUpdated($changedAnimators, $planningDate);
                if ($notificationResult['sent'] > 0) {
                    $this->addFlash('success', sprintf('%d notification(s) planning envoyée(s).', $notificationResult['sent']));
                }
            }

            return $this->redirectToRoute('app_daily_planning_day', ['date' => $planningDate->format('Y-m-d')]);
        }

        return $this->render('planning/day.html.twig', [
            'season' => $season,
            'date' => $planningDate,
            'previous_date' => $planningDate->modify('-1 day'),
            'next_date' => $planningDate->modify('+1 day'),
            'animators' => $animators,
            'task_rows' => $this->buildTaskRows($assignments, $animators),
        ]);
    }

    private function defaultPlanningDate(Season $season): \DateTimeImmutable
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        if ($today >= $season->getStartsAt() && $today <= $season->getEndsAt()) {
            return $today;
        }

        return $season->getStartsAt();
    }

    /**
     * @return array<string, DailyTaskAssignment>
     */
    private function getAssignmentsForDay(EntityManagerInterface $entityManager, Season $season, \DateTimeImmutable $date): array
    {
        $assignments = $entityManager->getRepository(DailyTaskAssignment::class)
            ->createQueryBuilder('assignment')
            ->leftJoin('assignment.animators', 'animator')
            ->addSelect('animator')
            ->andWhere('assignment.season = :season')
            ->andWhere('assignment.taskDate = :date')
            ->setParameter('season', $season)
            ->setParameter('date', $date->setTime(0, 0))
            ->getQuery()
            ->getResult();

        $indexedAssignments = [];
        foreach ($assignments as $assignment) {
            $indexedAssignments[$assignment->getTaskType()] = $assignment;
        }

        return $indexedAssignments;
    }

    /**
     * @param list<Animator> $animators
     * @param array<string, DailyTaskAssignment> $assignments
     *
     * @return list<Animator>
     */
    private function saveAssignments(
        Request $request,
        EntityManagerInterface $entityManager,
        Season $season,
        \DateTimeImmutable $date,
        array $animators,
        array $assignments,
    ): array {
        $submittedAssignments = $request->request->all('assignments');
        $animatorsById = [];
        $changedAnimators = [];

        foreach ($animators as $animator) {
            $animatorsById[(int) $animator->getId()] = $animator;
        }

        foreach (DailyTaskType::cases() as $task) {
            $selectedIds = array_map('intval', $submittedAssignments[$task->value] ?? []);
            $selectedAnimators = [];
            foreach ($selectedIds as $selectedId) {
                $animator = $animatorsById[$selectedId] ?? null;
                if ($animator instanceof Animator && $this->animatorCanTakeTask($animator, $task)) {
                    $selectedAnimators[] = $animator;
                }
            }

            $assignment = $assignments[$task->value] ?? null;
            $previousIds = $assignment instanceof DailyTaskAssignment ? $this->animatorIds($assignment->getAnimators()->toArray()) : [];
            $nextIds = $this->animatorIds($selectedAnimators);

            if ($assignment === null && $selectedAnimators === []) {
                continue;
            }

            if ($previousIds !== $nextIds) {
                foreach (array_unique([...$previousIds, ...$nextIds]) as $animatorId) {
                    if (isset($animatorsById[$animatorId])) {
                        $changedAnimators[$animatorId] = $animatorsById[$animatorId];
                    }
                }
            }

            if ($assignment === null) {
                $assignment = (new DailyTaskAssignment())
                    ->setSeason($season)
                    ->setTaskDate($date)
                    ->setTaskType($task);
                $entityManager->persist($assignment);
            }

            if ($selectedAnimators === []) {
                $entityManager->remove($assignment);
                continue;
            }

            $assignment->clearAnimators();
            foreach ($selectedAnimators as $animator) {
                $assignment->addAnimator($animator);
            }

            $assignment->touch();
        }

        return array_values($changedAnimators);
    }

    /**
     * @param array<string, DailyTaskAssignment> $assignments
     * @param list<Animator> $animators
     *
     * @return list<array{task: DailyTaskType, selected_ids: list<int>, eligible_animators: list<Animator>}>
     */
    private function buildTaskRows(array $assignments, array $animators): array
    {
        $rows = [];

        foreach (DailyTaskType::cases() as $task) {
            $selectedIds = [];
            $assignment = $assignments[$task->value] ?? null;

            if ($assignment instanceof DailyTaskAssignment) {
                foreach ($assignment->getAnimators() as $animator) {
                    $selectedIds[] = (int) $animator->getId();
                }
            }

            $eligibleAnimators = array_values(array_filter(
                $animators,
                fn (Animator $animator): bool => $this->animatorCanTakeTask($animator, $task),
            ));
            $eligibleAnimatorIds = array_map(
                fn (Animator $animator): int => (int) $animator->getId(),
                $eligibleAnimators,
            );

            $rows[] = [
                'task' => $task,
                'selected_ids' => array_values(array_intersect($selectedIds, $eligibleAnimatorIds)),
                'eligible_animators' => $eligibleAnimators,
            ];
        }

        return $rows;
    }

    private function animatorCanTakeTask(Animator $animator, DailyTaskType $task): bool
    {
        return match ($task) {
            DailyTaskType::CanteenSmall => $animator->getAgeGroup() === AgeGroup::Little->value,
            DailyTaskType::CanteenBig => $animator->getAgeGroup() === AgeGroup::Big->value,
            default => true,
        };
    }

    /**
     * @param list<Animator> $animators
     *
     * @return list<int>
     */
    private function animatorIds(array $animators): array
    {
        $ids = [];

        foreach ($animators as $animator) {
            if ($animator instanceof Animator && $animator->getId() !== null) {
                $ids[] = (int) $animator->getId();
            }
        }

        sort($ids);

        return $ids;
    }
}
