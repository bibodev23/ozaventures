<?php

namespace App\Service;

use App\Entity\Animator;
use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ApiTokenManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{plainToken: string, apiToken: ApiToken}
     */
    public function createForAnimator(Animator $animator): array
    {
        $user = $animator->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Cet animateur n’a pas encore de compte utilisateur lié.');
        }

        return $this->createForUser($user, $animator);
    }

    /**
     * @return array{plainToken: string, apiToken: ApiToken}
     */
    public function createForUser(User $user, ?Animator $animator = null): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $apiToken = (new ApiToken())
            ->setUser($user)
            ->setAnimator($animator ?? $user->getAnimator())
            ->setTokenHash($this->hash($plainToken))
            ->setExpiresAt(new \DateTimeImmutable('+90 days'));

        $this->entityManager->persist($apiToken);

        return [
            'plainToken' => $plainToken,
            'apiToken' => $apiToken,
        ];
    }

    public function findValidToken(string $plainToken): ?ApiToken
    {
        $apiToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy([
            'tokenHash' => $this->hash($plainToken),
        ]);

        if (!$apiToken instanceof ApiToken || $apiToken->isExpired()) {
            return null;
        }

        $user = $apiToken->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return null;
        }

        return $apiToken;
    }

    public function revokePlainToken(string $plainToken): void
    {
        $apiToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy([
            'tokenHash' => $this->hash($plainToken),
        ]);

        if ($apiToken instanceof ApiToken) {
            $this->entityManager->remove($apiToken);
        }
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
