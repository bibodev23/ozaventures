<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\AnimatorWorkShift;
use App\Entity\Season;
use App\Enum\AgeGroup;
use App\Service\ActiveSeasonProvider;
use App\Service\MobileNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/horaires')]
#[IsGranted('ROLE_DIRECTOR')]
class WorkScheduleController extends AbstractController
{
    private const CENTER_OPEN_MINUTES = 7 * 60;
    private const CENTER_CLOSE_MINUTES = 18 * 60;
    private const TIME_STEP_MINUTES = 15;
    private const WEEKLY_MAX_MINUTES = 35 * 60;

    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'start' => 'Début',
        'lunch_start' => 'Pause midi',
        'lunch_end' => 'Retour midi',
        'end' => 'Départ',
    ];

    #[Route('', name: 'app_work_schedule')]
    public function index(Request $request, ActiveSeasonProvider $seasonProvider): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $week = (string) $request->query->get('week', '');
        $selectedAgeGroup = $this->selectedAgeGroup($request);

        if ($week === '') {
            $weekStart = $this->defaultWeekStart($season);
        } else {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $week);
            if (!$date instanceof \DateTimeImmutable) {
                $this->addFlash('error', 'Date de semaine invalide.');
                $weekStart = $this->defaultWeekStart($season);
            } else {
                $weekStart = $this->weekStart($date);
            }
        }

        return $this->redirectToRoute('app_work_schedule_week', array_merge(
            ['week' => $weekStart->format('Y-m-d')],
            $this->scheduleQuery($selectedAgeGroup),
        ));
    }

    #[Route('/{week}', name: 'app_work_schedule_week', requirements: ['week' => '\d{4}-\d{2}-\d{2}'])]
    public function week(string $week, Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager, MobileNotificationService $notificationService): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $week);

        if (!$date instanceof \DateTimeImmutable) {
            $this->addFlash('error', 'Date de semaine invalide.');

            return $this->redirectToRoute('app_work_schedule');
        }

        $selectedAgeGroup = $this->selectedAgeGroup($request);
        $scheduleQuery = $this->scheduleQuery($selectedAgeGroup);

        $weekStart = $this->weekStart($date);
        if ($weekStart->format('Y-m-d') !== $week) {
            return $this->redirectToRoute('app_work_schedule_week', array_merge(
                ['week' => $weekStart->format('Y-m-d')],
                $scheduleQuery,
            ));
        }

        $weekDays = $this->weekDays($weekStart);
        $animators = $this->findActiveAnimators($entityManager, $selectedAgeGroup);
        $existingShifts = $this->getExistingShifts($entityManager, $season, $weekStart);

        $submittedValues = null;
        $errors = [];
        $dayMinutes = [];
        $weeklyTotals = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('work_schedule_' . $weekStart->format('Y-m-d'), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $submittedValues = $this->extractSubmittedValues($request, $animators, $weekDays);
            $validation = $this->validateSubmittedValues($submittedValues, $animators, $weekDays);
            $errors = $validation['errors'];
            $dayMinutes = $validation['day_minutes'];
            $weeklyTotals = $validation['weekly_totals'];

            if ($errors === []) {
                $changedAnimators = $this->saveSchedule($submittedValues, $validation['parsed'], $animators, $weekDays, $existingShifts, $season, $entityManager);
                $entityManager->flush();

                $this->addFlash('success', 'Horaires de la semaine enregistrés.');
                if ($request->request->getBoolean('notify_animators')) {
                    if ($changedAnimators === []) {
                        $this->addFlash('warning', 'Aucun horaire réellement modifié : aucune notification mobile envoyée.');
                    } else {
                        $notificationResult = $notificationService->notifyWorkScheduleUpdated($changedAnimators, $weekStart);
                        if ($notificationResult['sent'] > 0) {
                            $this->addFlash('success', sprintf('%d notification(s) horaires envoyée(s).', $notificationResult['sent']));
                        }

                        if ($notificationResult['failed'] > 0) {
                            $this->addFlash('warning', sprintf('%d notification(s) n’ont pas pu être envoyée(s). Vérifie Firebase côté serveur.', $notificationResult['failed']));
                        }

                        if ($notificationResult['sent'] === 0 && $notificationResult['failed'] === 0 && $notificationResult['skipped'] > 0) {
                            $this->addFlash('warning', 'Aucune notification envoyée : l’animateur concerné n’a pas encore de téléphone enregistré ou les notifications sont désactivées.');
                        } elseif ($notificationResult['skipped'] > 0) {
                            $this->addFlash('warning', sprintf('%d animateur(s) n’ont pas de téléphone enregistré pour les notifications.', $notificationResult['skipped']));
                        }
                    }
                }

                $overLimitNames = $this->overLimitNames($animators, $weeklyTotals);
                if ($overLimitNames !== []) {
                    $this->addFlash('warning', 'Attention : ' . implode(', ', $overLimitNames) . ' dépasse(nt) 35h cette semaine.');
                }

                return $this->redirectToRoute('app_work_schedule_week', array_merge(
                    ['week' => $weekStart->format('Y-m-d')],
                    $scheduleQuery,
                ));
            }

            $this->addFlash('error', 'Certains horaires sont à corriger avant enregistrement.');
        }

        return $this->render('schedules/week.html.twig', [
            'season' => $season,
            'week_start' => $weekStart,
            'previous_week' => $weekStart->modify('-7 days'),
            'next_week' => $weekStart->modify('+7 days'),
            'week_days' => $weekDays,
            'animators' => $animators,
            'active_animators_count' => $this->countActiveAnimators($entityManager),
            'selected_age_group' => $selectedAgeGroup,
            'age_group_filters' => $this->ageGroupFilters($entityManager, $selectedAgeGroup),
            'schedule_query' => $scheduleQuery,
            'field_labels' => self::FIELD_LABELS,
            'rows' => $this->buildRows($animators, $weekDays, $existingShifts, $submittedValues, $errors, $dayMinutes, $weeklyTotals),
            'weekly_max_label' => $this->formatMinutes(self::WEEKLY_MAX_MINUTES),
            'center_hours_label' => sprintf('%s - %s', $this->formatClock(self::CENTER_OPEN_MINUTES), $this->formatClock(self::CENTER_CLOSE_MINUTES)),
        ]);
    }

    private function selectedAgeGroup(Request $request): ?string
    {
        $value = trim((string) $request->query->get('group', ''));
        $allowedGroups = [AgeGroup::Little->value, AgeGroup::Big->value];

        return in_array($value, $allowedGroups, true) ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    private function scheduleQuery(?string $selectedAgeGroup): array
    {
        return $selectedAgeGroup !== null ? ['group' => $selectedAgeGroup] : [];
    }

    /**
     * @return list<Animator>
     */
    private function findActiveAnimators(EntityManagerInterface $entityManager, ?string $selectedAgeGroup): array
    {
        $queryBuilder = $entityManager->getRepository(Animator::class)
            ->createQueryBuilder('animator')
            ->andWhere('animator.active = true')
            ->orderBy('animator.lastName', 'ASC')
            ->addOrderBy('animator.firstName', 'ASC');

        if ($selectedAgeGroup !== null) {
            $queryBuilder
                ->andWhere('animator.ageGroup = :ageGroup')
                ->setParameter('ageGroup', $selectedAgeGroup);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function countActiveAnimators(EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->getRepository(Animator::class)
            ->createQueryBuilder('animator')
            ->select('COUNT(animator.id)')
            ->andWhere('animator.active = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{value: string|null, label: string, count: int, active: bool, query: array<string, string>}>
     */
    private function ageGroupFilters(EntityManagerInterface $entityManager, ?string $selectedAgeGroup): array
    {
        $counts = [
            'all' => 0,
            AgeGroup::Little->value => 0,
            AgeGroup::Big->value => 0,
        ];

        $results = $entityManager->getRepository(Animator::class)
            ->createQueryBuilder('animator')
            ->select('animator.ageGroup AS ageGroup, COUNT(animator.id) AS total')
            ->andWhere('animator.active = true')
            ->groupBy('animator.ageGroup')
            ->getQuery()
            ->getArrayResult();

        foreach ($results as $result) {
            $total = (int) $result['total'];
            $ageGroup = $result['ageGroup'];
            $counts['all'] += $total;

            if (is_string($ageGroup) && array_key_exists($ageGroup, $counts)) {
                $counts[$ageGroup] = $total;
            }
        }

        return [
            [
                'value' => null,
                'label' => 'Les deux groupes',
                'count' => $counts['all'],
                'active' => $selectedAgeGroup === null,
                'query' => [],
            ],
            [
                'value' => AgeGroup::Little->value,
                'label' => AgeGroup::Little->label(),
                'count' => $counts[AgeGroup::Little->value],
                'active' => $selectedAgeGroup === AgeGroup::Little->value,
                'query' => ['group' => AgeGroup::Little->value],
            ],
            [
                'value' => AgeGroup::Big->value,
                'label' => AgeGroup::Big->label(),
                'count' => $counts[AgeGroup::Big->value],
                'active' => $selectedAgeGroup === AgeGroup::Big->value,
                'query' => ['group' => AgeGroup::Big->value],
            ],
        ];
    }

    private function defaultWeekStart(Season $season): \DateTimeImmutable
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        if ($today >= $season->getStartsAt() && $today <= $season->getEndsAt()) {
            return $this->weekStart($today);
        }

        return $this->weekStart($season->getStartsAt());
    }

    private function weekStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify(sprintf('-%d days', ((int) $date->format('N')) - 1))->setTime(0, 0);
    }

    /**
     * @return list<array{date: \DateTimeImmutable, key: string, label: string, short_label: string}>
     */
    private function weekDays(\DateTimeImmutable $weekStart): array
    {
        $labels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
        $days = [];

        for ($index = 0; $index < 5; ++$index) {
            $date = $weekStart->modify(sprintf('+%d days', $index));
            $days[] = [
                'date' => $date,
                'key' => $date->format('Y-m-d'),
                'label' => $labels[$index],
                'short_label' => $labels[$index] . ' ' . $date->format('d/m'),
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array<string, AnimatorWorkShift>>
     */
    private function getExistingShifts(EntityManagerInterface $entityManager, Season $season, \DateTimeImmutable $weekStart): array
    {
        $shifts = $entityManager->getRepository(AnimatorWorkShift::class)
            ->createQueryBuilder('shift')
            ->leftJoin('shift.animator', 'animator')
            ->addSelect('animator')
            ->andWhere('shift.season = :season')
            ->andWhere('shift.workDate BETWEEN :start AND :end')
            ->setParameter('season', $season)
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekStart->modify('+4 days'))
            ->getQuery()
            ->getResult();

        $indexedShifts = [];
        foreach ($shifts as $shift) {
            if (!$shift instanceof AnimatorWorkShift || !$shift->getAnimator() instanceof Animator) {
                continue;
            }

            $indexedShifts[(int) $shift->getAnimator()->getId()][$shift->getWorkDate()->format('Y-m-d')] = $shift;
        }

        return $indexedShifts;
    }

    /**
     * @param list<Animator> $animators
     * @param list<array{key: string}> $weekDays
     *
     * @return array<int, array<string, array<string, string>>>
     */
    private function extractSubmittedValues(Request $request, array $animators, array $weekDays): array
    {
        $rawShifts = $request->request->all('shifts');
        $values = [];

        foreach ($animators as $animator) {
            $animatorId = (int) $animator->getId();
            foreach ($weekDays as $day) {
                foreach (array_keys(self::FIELD_LABELS) as $field) {
                    $values[$animatorId][$day['key']][$field] = trim((string) ($rawShifts[$animatorId][$day['key']][$field] ?? ''));
                }
            }
        }

        return $values;
    }

    /**
     * @param array<int, array<string, array<string, string>>> $submittedValues
     * @param list<Animator> $animators
     * @param list<array{key: string}> $weekDays
     *
     * @return array{
     *     errors: array<int, array<string, list<string>>>,
     *     parsed: array<int, array<string, array<string, int>|null>>,
     *     day_minutes: array<int, array<string, int>>,
     *     weekly_totals: array<int, int>
     * }
     */
    private function validateSubmittedValues(array $submittedValues, array $animators, array $weekDays): array
    {
        $errors = [];
        $parsed = [];
        $dayMinutes = [];
        $weeklyTotals = [];

        foreach ($animators as $animator) {
            $animatorId = (int) $animator->getId();
            $weeklyTotals[$animatorId] = 0;

            foreach ($weekDays as $day) {
                $dayKey = $day['key'];
                $values = $submittedValues[$animatorId][$dayKey] ?? [];
                $filledValues = array_filter($values, fn (string $value): bool => $value !== '');

                if ($filledValues === []) {
                    $parsed[$animatorId][$dayKey] = null;
                    continue;
                }

                if (count($filledValues) !== count(self::FIELD_LABELS)) {
                    $errors[$animatorId][$dayKey][] = 'Renseigne les 4 horaires ou laisse la journée vide.';
                    continue;
                }

                $times = [];
                foreach (array_keys(self::FIELD_LABELS) as $field) {
                    $time = $this->parseClock($values[$field] ?? '');
                    if ($time === null) {
                        $errors[$animatorId][$dayKey][] = 'Format attendu : HH:MM.';
                        continue 2;
                    }

                    if ($time % self::TIME_STEP_MINUTES !== 0) {
                        $errors[$animatorId][$dayKey][] = 'Les minutes doivent être par tranche de 15 minutes.';
                        continue 2;
                    }

                    $times[$field] = $time;
                }

                $dayErrors = $this->validateDayTimes($times);
                if ($dayErrors !== []) {
                    $errors[$animatorId][$dayKey] = array_merge($errors[$animatorId][$dayKey] ?? [], $dayErrors);
                    continue;
                }

                $minutes = ($times['lunch_start'] - $times['start']) + ($times['end'] - $times['lunch_end']);
                $parsed[$animatorId][$dayKey] = $times;
                $dayMinutes[$animatorId][$dayKey] = $minutes;
                $weeklyTotals[$animatorId] += $minutes;
            }
        }

        return [
            'errors' => $errors,
            'parsed' => $parsed,
            'day_minutes' => $dayMinutes,
            'weekly_totals' => $weeklyTotals,
        ];
    }

    /**
     * @param array<string, int> $times
     *
     * @return list<string>
     */
    private function validateDayTimes(array $times): array
    {
        $errors = [];

        if ($times['start'] < self::CENTER_OPEN_MINUTES) {
            $errors[] = 'Le début ne peut pas être avant 7h.';
        }

        if ($times['end'] > self::CENTER_CLOSE_MINUTES) {
            $errors[] = 'Le départ ne peut pas être après 18h.';
        }

        if ($times['lunch_start'] <= $times['start']) {
            $errors[] = 'La pause midi doit commencer après le début.';
        }

        if ($times['lunch_end'] <= $times['lunch_start']) {
            $errors[] = 'Le retour midi doit être après la pause midi.';
        }

        if ($times['end'] <= $times['lunch_end']) {
            $errors[] = 'Le départ du soir doit être après le retour midi.';
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, array<string, string>>> $submittedValues
     * @param array<int, array<string, array<string, int>|null>> $parsed
     * @param list<Animator> $animators
     * @param list<array{date: \DateTimeImmutable, key: string}> $weekDays
     * @param array<int, array<string, AnimatorWorkShift>> $existingShifts
     *
     * @return list<Animator>
     */
    private function saveSchedule(
        array $submittedValues,
        array $parsed,
        array $animators,
        array $weekDays,
        array $existingShifts,
        Season $season,
        EntityManagerInterface $entityManager,
    ): array {
        $changedAnimators = [];

        foreach ($animators as $animator) {
            $animatorId = (int) $animator->getId();

            foreach ($weekDays as $day) {
                $dayKey = $day['key'];
                $values = $submittedValues[$animatorId][$dayKey] ?? [];
                $hasValues = array_filter($values, fn (string $value): bool => $value !== '') !== [];
                $shift = $existingShifts[$animatorId][$dayKey] ?? null;

                if (!$hasValues) {
                    if ($shift instanceof AnimatorWorkShift) {
                        $entityManager->remove($shift);
                        $changedAnimators[$animatorId] = $animator;
                    }

                    continue;
                }

                $times = $parsed[$animatorId][$dayKey] ?? null;
                if ($times === null) {
                    continue;
                }

                if (!$shift instanceof AnimatorWorkShift) {
                    $shift = (new AnimatorWorkShift())
                        ->setSeason($season)
                        ->setAnimator($animator)
                        ->setWorkDate($day['date']);
                    $entityManager->persist($shift);
                    $changedAnimators[$animatorId] = $animator;
                } elseif (!$this->shiftMatchesTimes($shift, $times)) {
                    $changedAnimators[$animatorId] = $animator;
                }

                $shift
                    ->setStartTime($this->clockFromMinutes($times['start']))
                    ->setLunchStartTime($this->clockFromMinutes($times['lunch_start']))
                    ->setLunchEndTime($this->clockFromMinutes($times['lunch_end']))
                    ->setEndTime($this->clockFromMinutes($times['end']))
                    ->touch();
            }
        }

        return array_values($changedAnimators);
    }

    /**
     * @param list<Animator> $animators
     * @param list<array{date: \DateTimeImmutable, key: string, label: string, short_label: string}> $weekDays
     * @param array<int, array<string, AnimatorWorkShift>> $existingShifts
     * @param array<int, array<string, array<string, string>>>|null $submittedValues
     * @param array<int, array<string, list<string>>> $errors
     * @param array<int, array<string, int>> $dayMinutes
     * @param array<int, int> $weeklyTotals
     *
     * @return list<array<string, mixed>>
     */
    private function buildRows(array $animators, array $weekDays, array $existingShifts, ?array $submittedValues, array $errors, array $dayMinutes, array $weeklyTotals): array
    {
        $rows = [];

        foreach ($animators as $animator) {
            $animatorId = (int) $animator->getId();
            $days = [];
            $totalMinutes = $weeklyTotals[$animatorId] ?? 0;

            foreach ($weekDays as $day) {
                $dayKey = $day['key'];
                $shift = $existingShifts[$animatorId][$dayKey] ?? null;
                $values = $submittedValues[$animatorId][$dayKey] ?? null;
                $minutes = $dayMinutes[$animatorId][$dayKey] ?? null;

                if ($values === null) {
                    $values = $shift instanceof AnimatorWorkShift ? $this->valuesFromShift($shift) : $this->emptyValues();
                    $minutes = $shift instanceof AnimatorWorkShift ? $shift->getWorkedMinutes() : null;
                    $totalMinutes += $minutes ?? 0;
                }

                $days[] = [
                    'date' => $day['date'],
                    'key' => $dayKey,
                    'label' => $day['label'],
                    'short_label' => $day['short_label'],
                    'values' => $values,
                    'minutes' => $minutes,
                    'minutes_label' => $minutes !== null ? $this->formatMinutes($minutes) : null,
                    'errors' => $errors[$animatorId][$dayKey] ?? [],
                ];
            }

            $rows[] = [
                'animator' => $animator,
                'days' => $days,
                'total_minutes' => $totalMinutes,
                'total_label' => $this->formatMinutes($totalMinutes),
                'is_over_limit' => $totalMinutes > self::WEEKLY_MAX_MINUTES,
                'over_limit_label' => $totalMinutes > self::WEEKLY_MAX_MINUTES
                    ? sprintf('Dépassement de %s', $this->formatMinutes($totalMinutes - self::WEEKLY_MAX_MINUTES))
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<Animator> $animators
     * @param array<int, int> $weeklyTotals
     *
     * @return list<string>
     */
    private function overLimitNames(array $animators, array $weeklyTotals): array
    {
        $names = [];

        foreach ($animators as $animator) {
            $animatorId = (int) $animator->getId();
            if (($weeklyTotals[$animatorId] ?? 0) > self::WEEKLY_MAX_MINUTES) {
                $names[] = sprintf('%s (%s)', $animator->getFullName(), $this->formatMinutes($weeklyTotals[$animatorId]));
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    private function valuesFromShift(AnimatorWorkShift $shift): array
    {
        return [
            'start' => $shift->getStartTime()->format('H:i'),
            'lunch_start' => $shift->getLunchStartTime()->format('H:i'),
            'lunch_end' => $shift->getLunchEndTime()->format('H:i'),
            'end' => $shift->getEndTime()->format('H:i'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyValues(): array
    {
        return array_fill_keys(array_keys(self::FIELD_LABELS), '');
    }

    private function parseClock(string $value): ?int
    {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    private function clockFromMinutes(int $minutes): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('00:00'))->setTime(intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * @param array<string, int> $times
     */
    private function shiftMatchesTimes(AnimatorWorkShift $shift, array $times): bool
    {
        return $this->minutesFromClock($shift->getStartTime()) === $times['start']
            && $this->minutesFromClock($shift->getLunchStartTime()) === $times['lunch_start']
            && $this->minutesFromClock($shift->getLunchEndTime()) === $times['lunch_end']
            && $this->minutesFromClock($shift->getEndTime()) === $times['end'];
    }

    private function minutesFromClock(\DateTimeInterface $clock): int
    {
        return ((int) $clock->format('H') * 60) + (int) $clock->format('i');
    }

    private function formatClock(int $minutes): string
    {
        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
