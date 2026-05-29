<?php

namespace App\Service;

use App\Entity\Animator;
use App\Entity\MobileDeviceToken;
use App\Entity\Outing;
use Doctrine\ORM\EntityManagerInterface;

class MobileNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FirebaseCloudMessagingClient $firebase,
    ) {
    }

    /**
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function notifyOutingAssigned(Outing $outing, ?Animator $exceptAnimator = null): array
    {
        return $this->sendToAnimators(
            $outing->getAnimators(),
            'Nouvelle sortie',
            sprintf('%s - départ %s', $outing->getDestination(), $outing->getDepartureAt()->format('H\hi')),
            [
                'type' => 'outing_assigned',
                'outingId' => (string) $outing->getId(),
                'outingNumber' => $outing->getNumber(),
            ],
            $exceptAnimator,
        );
    }

    /**
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function notifyOutingUpdated(Outing $outing): array
    {
        return $this->sendToAnimators(
            $outing->getAnimators(),
            'Sortie modifiée',
            sprintf('%s a été mise à jour.', $outing->getDestination()),
            [
                'type' => 'outing_updated',
                'outingId' => (string) $outing->getId(),
                'outingNumber' => $outing->getNumber(),
            ],
        );
    }

    /**
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function notifyOutingStatusUpdated(Outing $outing): array
    {
        return $this->sendToAnimators(
            $outing->getAnimators(),
            'Statut de sortie mis à jour',
            sprintf('%s : %s.', $outing->getDestination(), $outing->getStatusLabel()),
            [
                'type' => 'outing_status_updated',
                'outingId' => (string) $outing->getId(),
                'outingNumber' => $outing->getNumber(),
                'status' => $outing->getStatus(),
            ],
        );
    }

    /**
     * @param iterable<Animator> $animators
     *
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function notifyWorkScheduleUpdated(iterable $animators, \DateTimeImmutable $weekStart): array
    {
        return $this->sendToAnimators(
            $animators,
            'Horaires mis à jour',
            sprintf('Tes horaires de la semaine du %s ont été mis à jour.', $weekStart->format('d/m')),
            [
                'type' => 'work_schedule_updated',
                'weekStart' => $weekStart->format('Y-m-d'),
            ],
        );
    }

    /**
     * @param iterable<Animator> $animators
     *
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function notifyDailyPlanningUpdated(iterable $animators, \DateTimeImmutable $date): array
    {
        return $this->sendToAnimators(
            $animators,
            'Planning du jour mis à jour',
            sprintf('Tes tâches du %s ont été mises à jour.', $date->format('d/m')),
            [
                'type' => 'daily_planning_updated',
                'date' => $date->format('Y-m-d'),
            ],
        );
    }

    /**
     * @param iterable<Animator> $animators
     * @param array<string, string> $data
     *
     * @return array{sent:int, failed:int, skipped:int}
     */
    private function sendToAnimators(iterable $animators, string $title, string $body, array $data, ?Animator $exceptAnimator = null): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $shouldFlush = false;

        foreach ($this->uniqueAnimators($animators) as $animator) {
            if ($exceptAnimator instanceof Animator && $animator->getId() === $exceptAnimator->getId()) {
                ++$skipped;
                continue;
            }

            $tokens = $this->entityManager->getRepository(MobileDeviceToken::class)->findBy([
                'animator' => $animator,
                'enabled' => true,
            ]);

            if ($tokens === []) {
                ++$skipped;
                continue;
            }

            foreach ($tokens as $token) {
                try {
                    $this->firebase->sendToToken($token->getToken(), $title, $body, $data);
                    ++$sent;
                } catch (\Throwable $exception) {
                    if (str_contains($exception->getMessage(), 'UNREGISTERED')) {
                        $token->setEnabled(false)->touch();
                        $shouldFlush = true;
                    }

                    ++$failed;
                }
            }
        }

        if ($shouldFlush) {
            $this->entityManager->flush();
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param iterable<Animator> $animators
     *
     * @return list<Animator>
     */
    private function uniqueAnimators(iterable $animators): array
    {
        $unique = [];

        foreach ($animators as $animator) {
            if (!$animator instanceof Animator || $animator->getId() === null) {
                continue;
            }

            $unique[$animator->getId()] = $animator;
        }

        return array_values($unique);
    }
}
