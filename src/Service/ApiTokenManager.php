<?php

namespace App\Service;

use App\Entity\Animator;
use App\Entity\ApiToken;
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
        $plainToken = bin2hex(random_bytes(32));
        $apiToken = (new ApiToken())
            ->setAnimator($animator)
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

        $animator = $apiToken->getAnimator();
        if (!$animator instanceof Animator || !$animator->isActive()) {
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
