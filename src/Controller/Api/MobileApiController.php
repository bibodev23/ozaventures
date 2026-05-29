<?php

namespace App\Controller\Api;

use App\Entity\Animator;
use App\Entity\AnimatorWorkShift;
use App\Entity\Child;
use App\Entity\DailyTaskAssignment;
use App\Entity\MobileDeviceToken;
use App\Entity\Outing;
use App\Entity\Season;
use App\Entity\User;
use App\Enum\OutingStatus;
use App\Service\ActiveSeasonProvider;
use App\Service\ApiTokenManager;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class MobileApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveSeasonProvider $seasonProvider,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $passwordHasher, ApiTokenManager $apiTokenManager): JsonResponse
    {
        $payload = $this->jsonPayload($request);
        $username = strtolower(trim((string) ($payload['username'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->apiError('invalid_credentials', 'Identifiant et mot de passe obligatoires.', Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user instanceof User || !$user->isActive() || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->apiError('invalid_credentials', 'Identifiants incorrects.', Response::HTTP_UNAUTHORIZED);
        }

        $tokenData = $apiTokenManager->createForUser($user);
        $this->entityManager->flush();

        return $this->json([
            'token' => $tokenData['plainToken'],
            'tokenType' => 'Bearer',
            'expiresAt' => $tokenData['apiToken']->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'user' => $this->serializeUser($user),
            'animator' => $user->getAnimator() instanceof Animator ? $this->serializeAnimator($user->getAnimator()) : null,
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function logout(Request $request, ApiTokenManager $apiTokenManager): JsonResponse
    {
        $token = $this->bearerToken($request);
        if ($token !== null) {
            $apiTokenManager->revokePlainToken($token);
            $this->entityManager->flush();
        }

        return $this->json(['message' => 'Déconnexion API effectuée.']);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        $user = $this->currentUser();

        return $this->json([
            'user' => $this->serializeUser($user),
            'animator' => $user->getAnimator() instanceof Animator ? $this->serializeAnimator($user->getAnimator()) : null,
        ]);
    }

    #[Route('/device-tokens', name: 'api_device_token_register', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $animator = $user->getAnimator();
        $payload = $this->jsonPayload($request);
        $token = trim((string) ($payload['token'] ?? ''));
        $platform = strtolower(trim((string) ($payload['platform'] ?? 'android')));
        $deviceName = trim((string) ($payload['deviceName'] ?? ''));

        if ($token === '') {
            return $this->apiError('missing_device_token', 'Token de notification obligatoire.', Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($platform, ['android', 'ios'], true)) {
            return $this->apiError('invalid_platform', 'Plateforme mobile invalide.', Response::HTTP_BAD_REQUEST);
        }

        $deviceToken = $this->entityManager->getRepository(MobileDeviceToken::class)->findOneBy(['token' => $token]);
        if (!$deviceToken instanceof MobileDeviceToken) {
            $deviceToken = (new MobileDeviceToken())->setToken($token);
            $this->entityManager->persist($deviceToken);
        }

        $deviceToken
            ->setUser($user)
            ->setAnimator($animator instanceof Animator ? $animator : null)
            ->setPlatform($platform)
            ->setDeviceName($deviceName)
            ->setEnabled(true)
            ->markSeen();

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Téléphone enregistré pour les notifications.',
            'deviceToken' => [
                'id' => $deviceToken->getId(),
                'platform' => $deviceToken->getPlatform(),
                'enabled' => $deviceToken->isEnabled(),
                'lastSeenAt' => $deviceToken->getLastSeenAt()?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    #[Route('/change-password', name: 'api_change_password', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->currentUser();
        $payload = $this->jsonPayload($request);
        $currentPassword = (string) ($payload['currentPassword'] ?? '');
        $newPassword = (string) ($payload['newPassword'] ?? '');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->apiError('invalid_current_password', 'Mot de passe actuel incorrect.', Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($newPassword) < 8) {
            return $this->apiError('weak_password', 'Le nouveau mot de passe doit contenir au moins 8 caractères.', Response::HTTP_BAD_REQUEST);
        }

        $user
            ->setPasswordHash($passwordHasher->hashPassword($user, $newPassword))
            ->setMustChangePassword(false);

        $animator = $user->getAnimator();
        if ($animator instanceof Animator) {
            $animator
                ->setPasswordHash($user->getPassword() ?? '')
                ->setMustChangePassword(false);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Mot de passe modifié.',
            'user' => $this->serializeUser($user),
            'animator' => $animator instanceof Animator ? $this->serializeAnimator($animator) : null,
        ]);
    }

    #[Route('/children', name: 'api_children', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function children(): JsonResponse
    {
        $season = $this->seasonProvider->getActiveSeason();
        $children = $this->entityManager->getRepository(Child::class)->createQueryBuilder('child')
            ->leftJoin('child.outings', 'outing')
            ->addSelect('outing')
            ->andWhere('child.season = :season')
            ->setParameter('season', $season)
            ->orderBy('child.lastName', 'ASC')
            ->addOrderBy('child.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'season' => $this->serializeSeason($season),
            'children' => array_map(fn (Child $child): array => $this->serializeChild($child, true), $children),
        ]);
    }

    #[Route('/animators', name: 'api_animators', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function animators(): JsonResponse
    {
        $animators = $this->entityManager->getRepository(Animator::class)->findBy(
            ['active' => true],
            ['lastName' => 'ASC', 'firstName' => 'ASC'],
        );

        return $this->json([
            'animators' => array_map(fn (Animator $animator): array => $this->serializeAnimator($animator), $animators),
        ]);
    }

    #[Route('/outings', name: 'api_outings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function outings(): JsonResponse
    {
        $user = $this->currentUser();
        $animator = $user->getAnimator();
        $season = $this->seasonProvider->getActiveSeason();
        $queryBuilder = $this->entityManager->getRepository(Outing::class)->createQueryBuilder('outing')
            ->leftJoin('outing.animators', 'animator')
            ->addSelect('animator')
            ->leftJoin('outing.children', 'child')
            ->addSelect('child')
            ->andWhere('outing.season = :season')
            ->setParameter('season', $season)
            ->orderBy('outing.departureAt', 'ASC');

        if (!$user->isDirector()) {
            if (!$animator instanceof Animator) {
                throw $this->createAccessDeniedException();
            }

            $queryBuilder
                ->andWhere('outing.createdBy = :animator OR animator = :animator')
                ->setParameter('animator', $animator);
        }

        $outings = $queryBuilder
            ->getQuery()
            ->getResult();

        return $this->json([
            'season' => $this->serializeSeason($season),
            'outings' => array_map(fn (Outing $outing): array => $this->serializeOuting($outing), $outings),
        ]);
    }

    #[Route('/outings', name: 'api_outing_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createOuting(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $animator = $user->getAnimator();
        $season = $this->seasonProvider->getActiveSeason();
        $payload = $this->jsonPayload($request);

        $outing = (new Outing())
            ->setSeason($season)
            ->setCreatedBy($animator instanceof Animator ? $animator : null)
            ->setStatus(OutingStatus::Pending->value)
            ->setNumber(trim((string) ($payload['number'] ?? $this->nextOutingNumber($season))));

        $error = $this->applyOutingPayload($outing, $payload, $season, $animator);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $violations = $this->validator->validate($outing);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $this->entityManager->persist($outing->touch());
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Sortie créée.',
            'outing' => $this->serializeOuting($outing),
        ], Response::HTTP_CREATED);
    }

    #[Route('/outings/{id}', name: 'api_outing_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function outing(Outing $outing): JsonResponse
    {
        if (!$this->currentUser()->isDirector() && !$this->animatorCanSeeOuting($this->currentAnimator(), $outing)) {
            return $this->apiError('forbidden', 'Sortie inaccessible.', Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'outing' => $this->serializeOuting($outing),
        ]);
    }

    #[Route('/outings/{id}', name: 'api_outing_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateOuting(Outing $outing, Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $animator = $user->getAnimator();
        if (!$user->isDirector() && (!$animator instanceof Animator || $outing->getCreatedBy() !== $animator)) {
            return $this->apiError('forbidden', 'Seul l’animateur qui a créé la sortie peut la modifier depuis l’app mobile.', Response::HTTP_FORBIDDEN);
        }

        if ($outing->getStatus() !== OutingStatus::Pending->value) {
            return $this->apiError('outing_locked', 'Une sortie validée ou refusée ne peut plus être modifiée depuis l’app mobile.', Response::HTTP_CONFLICT);
        }

        $error = $this->applyOutingPayload($outing, $this->jsonPayload($request), $outing->getSeason(), $animator);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $violations = $this->validator->validate($outing);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Sortie mise à jour.',
            'outing' => $this->serializeOuting($outing),
        ]);
    }

    #[Route('/daily-planning', name: 'api_daily_planning', methods: ['GET'])]
    #[IsGranted('ROLE_ANIMATOR')]
    public function dailyPlanning(Request $request): JsonResponse
    {
        $animator = $this->currentAnimator();
        $season = $this->seasonProvider->getActiveSeason();
        $date = $this->dateFromQuery($request, 'date') ?? new \DateTimeImmutable('today');

        $assignments = $this->entityManager->getRepository(DailyTaskAssignment::class)
            ->createQueryBuilder('assignment')
            ->innerJoin('assignment.animators', 'animator')
            ->andWhere('assignment.season = :season')
            ->andWhere('assignment.taskDate = :date')
            ->andWhere('animator = :animator')
            ->setParameter('season', $season)
            ->setParameter('date', $date->setTime(0, 0))
            ->setParameter('animator', $animator)
            ->getQuery()
            ->getResult();

        return $this->json([
            'date' => $date->format('Y-m-d'),
            'tasks' => array_map(function (DailyTaskAssignment $assignment): array {
                $task = $assignment->getTask();

                return [
                    'type' => $task->value,
                    'label' => $task->label(),
                    'timeLabel' => $task->timeLabel(),
                    'description' => $task->description(),
                    'groupLabel' => $task->groupLabel(),
                ];
            }, $assignments),
        ]);
    }

    #[Route('/work-schedule', name: 'api_work_schedule', methods: ['GET'])]
    #[IsGranted('ROLE_ANIMATOR')]
    public function workSchedule(Request $request): JsonResponse
    {
        $animator = $this->currentAnimator();
        $season = $this->seasonProvider->getActiveSeason();
        $weekDate = $this->dateFromQuery($request, 'week') ?? $this->defaultWorkScheduleWeek($season);
        $weekStart = $this->weekStart($weekDate);

        $shifts = $this->entityManager->getRepository(AnimatorWorkShift::class)
            ->createQueryBuilder('shift')
            ->andWhere('shift.season = :season')
            ->andWhere('shift.animator = :animator')
            ->andWhere('shift.workDate BETWEEN :start AND :end')
            ->setParameter('season', $season)
            ->setParameter('animator', $animator)
            ->setParameter('start', $weekStart)
            ->setParameter('end', $weekStart->modify('+4 days'))
            ->orderBy('shift.workDate', 'ASC')
            ->getQuery()
            ->getResult();

        $totalMinutes = array_reduce(
            $shifts,
            fn (int $total, AnimatorWorkShift $shift): int => $total + $shift->getWorkedMinutes(),
            0,
        );

        return $this->json([
            'weekStart' => $weekStart->format('Y-m-d'),
            'totalMinutes' => $totalMinutes,
            'totalLabel' => $this->formatMinutes($totalMinutes),
            'shifts' => array_map(fn (AnimatorWorkShift $shift): array => $this->serializeWorkShift($shift), $shifts),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyOutingPayload(Outing $outing, array $payload, ?Season $season, ?Animator $currentAnimator): ?JsonResponse
    {
        if (!$season instanceof Season) {
            return $this->apiError('missing_season', 'Saison active introuvable.', Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('number', $payload)) {
            $outing->setNumber((string) $payload['number']);
        }

        foreach (['destination', 'departureAt', 'returnAt', 'transportMode'] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $this->apiError('missing_field', sprintf('Champ obligatoire manquant : %s.', $field), Response::HTTP_BAD_REQUEST);
            }
        }

        $departureAt = $this->dateTimeFromPayload($payload['departureAt']);
        $returnAt = $this->dateTimeFromPayload($payload['returnAt']);
        if (!$departureAt instanceof \DateTimeImmutable || !$returnAt instanceof \DateTimeImmutable) {
            return $this->apiError('invalid_datetime', 'Les dates doivent être au format ISO 8601.', Response::HTTP_BAD_REQUEST);
        }

        $outing
            ->setDestination((string) $payload['destination'])
            ->setDepartureAt($departureAt)
            ->setReturnAt($returnAt)
            ->setTransportMode((string) $payload['transportMode'])
            ->setPicnicRequired((bool) ($payload['picnicRequired'] ?? false))
            ->touch();

        $childIds = $payload['childIds'] ?? [];
        $animatorIds = $payload['animatorIds'] ?? ($currentAnimator instanceof Animator ? [$currentAnimator->getId()] : []);

        if (!is_array($childIds) || !is_array($animatorIds)) {
            return $this->apiError('invalid_selection', 'childIds et animatorIds doivent être des tableaux.', Response::HTTP_BAD_REQUEST);
        }

        $this->replaceChildren($outing, $season, array_map('intval', $childIds));
        $animatorIds = array_map('intval', $animatorIds);
        if ($currentAnimator instanceof Animator) {
            $animatorIds[] = (int) $currentAnimator->getId();
        }

        $this->replaceAnimators($outing, array_unique($animatorIds));

        return null;
    }

    /**
     * @param list<int> $childIds
     */
    private function replaceChildren(Outing $outing, Season $season, array $childIds): void
    {
        foreach ($outing->getChildren()->toArray() as $child) {
            $outing->removeChild($child);
        }

        if ($childIds === []) {
            return;
        }

        $children = $this->entityManager->getRepository(Child::class)->createQueryBuilder('child')
            ->andWhere('child.season = :season')
            ->andWhere('child.id IN (:ids)')
            ->setParameter('season', $season)
            ->setParameter('ids', $childIds)
            ->getQuery()
            ->getResult();

        foreach ($children as $child) {
            $outing->addChild($child);
        }
    }

    /**
     * @param list<int> $animatorIds
     */
    private function replaceAnimators(Outing $outing, array $animatorIds): void
    {
        foreach ($outing->getAnimators()->toArray() as $animator) {
            $outing->removeAnimator($animator);
        }

        if ($animatorIds === []) {
            return;
        }

        $animators = $this->entityManager->getRepository(Animator::class)->createQueryBuilder('animator')
            ->andWhere('animator.active = true')
            ->andWhere('animator.id IN (:ids)')
            ->setParameter('ids', $animatorIds)
            ->getQuery()
            ->getResult();

        foreach ($animators as $animator) {
            $outing->addAnimator($animator);
        }
    }

    private function animatorCanSeeOuting(Animator $animator, Outing $outing): bool
    {
        return $outing->getCreatedBy() === $animator || $outing->getAnimators()->contains($animator);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return [];
        }

        return $payload;
    }

    private function validationError(iterable $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->json([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'Certaines données sont invalides.',
                'details' => $errors,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function apiError(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function currentAnimator(): Animator
    {
        $animator = $this->currentUser()->getAnimator();
        if (!$animator instanceof Animator) {
            throw $this->createAccessDeniedException();
        }

        return $animator;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function bearerToken(Request $request): ?string
    {
        $authorization = (string) $request->headers->get('Authorization');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authorization, 7));

        return $token !== '' ? $token : null;
    }

    private function dateTimeFromPayload(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateFromQuery(Request $request, string $key): ?\DateTimeImmutable
    {
        $value = (string) $request->query->get($key, '');
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    private function weekStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify(sprintf('-%d days', ((int) $date->format('N')) - 1))->setTime(0, 0);
    }

    private function defaultWorkScheduleWeek(Season $season): \DateTimeImmutable
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        if ($today >= $season->getStartsAt() && $today <= $season->getEndsAt()) {
            return $today;
        }

        return $season->getStartsAt();
    }

    private function nextOutingNumber(Season $season): string
    {
        $count = $this->entityManager->getRepository(Outing::class)->count(['season' => $season]);

        return (string) ($count + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSeason(Season $season): array
    {
        return [
            'id' => $season->getId(),
            'name' => $season->getName(),
            'startsAt' => $season->getStartsAt()->format('Y-m-d'),
            'endsAt' => $season->getEndsAt()->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'displayName' => $user->getDisplayName(),
            'role' => $user->getRole()->value,
            'roleLabel' => $user->getRole()->label(),
            'mustChangePassword' => $user->mustChangePassword(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAnimator(Animator $animator): array
    {
        return [
            'id' => $animator->getId(),
            'firstName' => $animator->getFirstName(),
            'lastName' => $animator->getLastName(),
            'fullName' => $animator->getFullName(),
            'username' => $animator->getUsername(),
            'phone' => $animator->getPhone(),
            'ageGroup' => $animator->getAgeGroup(),
            'ageGroupLabel' => $animator->getAgeGroupLabel(),
            'mustChangePassword' => $animator->mustChangePassword(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeChild(Child $child, bool $includeDetails = false): array
    {
        $data = [
            'id' => $child->getId(),
            'firstName' => $child->getFirstName(),
            'lastName' => $child->getLastName(),
            'fullName' => $child->getFullName(),
            'age' => $child->getAge(),
            'ageGroup' => $child->getAgeGroup(),
            'ageGroupLabel' => $child->getAgeGroupLabel(),
        ];

        if (!$includeDetails) {
            return $data;
        }

        return $data + [
            'legalGuardians' => $child->getLegalGuardians(),
            'legalGuardianPhones' => $child->getLegalGuardianPhones(),
            'allergies' => $child->getAllergies(),
            'hasAllergies' => $child->hasAllergies(),
            'photoPermission' => $child->hasPhotoPermission(),
            'importantNotes' => $child->getImportantNotes(),
            'outings' => $this->serializeChildOutings($child),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeChildOutings(Child $child): array
    {
        $outings = $child->getOutings()->toArray();
        usort(
            $outings,
            fn (Outing $left, Outing $right): int => $right->getDepartureAt() <=> $left->getDepartureAt(),
        );

        return array_map(
            fn (Outing $outing): array => [
                'id' => $outing->getId(),
                'number' => $outing->getNumber(),
                'destination' => $outing->getDestination(),
                'departureAt' => $outing->getDepartureAt()->format(\DateTimeInterface::ATOM),
                'returnAt' => $outing->getReturnAt()->format(\DateTimeInterface::ATOM),
                'transportMode' => $outing->getTransportMode(),
                'picnicRequired' => $outing->isPicnicRequired(),
                'status' => $outing->getStatus(),
                'statusLabel' => $outing->getStatusLabel(),
                'validationComment' => $outing->getValidationComment(),
            ],
            $outings,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOuting(Outing $outing): array
    {
        return [
            'id' => $outing->getId(),
            'number' => $outing->getNumber(),
            'destination' => $outing->getDestination(),
            'departureAt' => $outing->getDepartureAt()->format(\DateTimeInterface::ATOM),
            'returnAt' => $outing->getReturnAt()->format(\DateTimeInterface::ATOM),
            'transportMode' => $outing->getTransportMode(),
            'picnicRequired' => $outing->isPicnicRequired(),
            'status' => $outing->getStatus(),
            'statusLabel' => $outing->getStatusLabel(),
            'validationComment' => $outing->getValidationComment(),
            'validatedAt' => $outing->getValidatedAt()?->format(\DateTimeInterface::ATOM),
            'createdBy' => $outing->getCreatedBy() instanceof Animator ? $this->serializeAnimator($outing->getCreatedBy()) : null,
            'children' => $this->serializeCollection($outing->getChildren(), fn (Child $child): array => $this->serializeChild($child)),
            'animators' => $this->serializeCollection($outing->getAnimators(), fn (Animator $animator): array => $this->serializeAnimator($animator)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWorkShift(AnimatorWorkShift $shift): array
    {
        return [
            'date' => $shift->getWorkDate()->format('Y-m-d'),
            'startTime' => $shift->getStartTime()->format('H:i'),
            'lunchStartTime' => $shift->getLunchStartTime()->format('H:i'),
            'lunchEndTime' => $shift->getLunchEndTime()->format('H:i'),
            'endTime' => $shift->getEndTime()->format('H:i'),
            'workedMinutes' => $shift->getWorkedMinutes(),
            'workedHoursLabel' => $shift->getWorkedHoursLabel(),
        ];
    }

    /**
     * @template T
     *
     * @param Collection<int, T> $collection
     * @param callable(T): array<string, mixed> $serializer
     *
     * @return list<array<string, mixed>>
     */
    private function serializeCollection(Collection $collection, callable $serializer): array
    {
        return array_map($serializer, $collection->toArray());
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
