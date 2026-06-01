<?php

namespace App\Service;

use App\Entity\Animator;
use App\Entity\Message;
use App\Entity\Outing;
use App\Entity\User;
use App\Enum\AgeGroup;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;

class InternalMessageService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param list<int> $recipientIds
     */
    public function createMessage(
        User $sender,
        string $audience,
        ?string $subject,
        string $body,
        array $recipientIds = [],
        ?Outing $outing = null,
    ): Message {
        $audience = trim($audience);
        $body = trim($body);

        if (!in_array($audience, Message::AUDIENCES, true)) {
            throw new \InvalidArgumentException('Type de destinataire invalide.');
        }

        if ($body === '') {
            throw new \InvalidArgumentException('Le message ne peut pas être vide.');
        }

        $recipients = $this->resolveRecipients($sender, $audience, $recipientIds, $outing);
        if ($recipients === []) {
            throw new \InvalidArgumentException('Aucun destinataire disponible pour ce message.');
        }

        $message = (new Message())
            ->setSender($sender)
            ->setAudience($audience)
            ->setSubject($subject)
            ->setBody($body)
            ->setOuting($audience === Message::AUDIENCE_OUTING ? $outing : null);

        foreach ($recipients as $recipient) {
            $message->addRecipient($recipient);
        }

        $this->entityManager->persist($message);

        return $message;
    }

    /**
     * @param list<int> $recipientIds
     *
     * @return list<User>
     */
    public function resolveRecipients(User $sender, string $audience, array $recipientIds = [], ?Outing $outing = null): array
    {
        return match ($audience) {
            Message::AUDIENCE_PRIVATE => $this->privateRecipients($sender, $recipientIds),
            Message::AUDIENCE_ALL_ANIMATORS => $this->usersByRole($sender, UserRole::Animator),
            Message::AUDIENCE_ALL_DIRECTORS => $this->usersByRole($sender, UserRole::Director),
            Message::AUDIENCE_EVERYONE => $this->allUsersExcept($sender),
            Message::AUDIENCE_GROUP_LITTLE => $this->usersByAgeGroup($sender, AgeGroup::Little),
            Message::AUDIENCE_GROUP_BIG => $this->usersByAgeGroup($sender, AgeGroup::Big),
            Message::AUDIENCE_OUTING => $this->outingRecipients($sender, $outing),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function audienceChoices(User $sender): array
    {
        if ($sender->isDirector()) {
            return [
                Message::AUDIENCE_PRIVATE => 'Message privé',
                Message::AUDIENCE_OUTING => 'Équipe d’une sortie',
                Message::AUDIENCE_ALL_ANIMATORS => 'Tous les animateurs',
                Message::AUDIENCE_GROUP_LITTLE => 'Animateurs 3-5 ans',
                Message::AUDIENCE_GROUP_BIG => 'Animateurs 6-12 ans',
                Message::AUDIENCE_ALL_DIRECTORS => 'Toute la direction',
                Message::AUDIENCE_EVERYONE => 'Toute l’équipe',
            ];
        }

        return [
            Message::AUDIENCE_PRIVATE => 'Message privé',
            Message::AUDIENCE_OUTING => 'Équipe d’une sortie',
            Message::AUDIENCE_ALL_DIRECTORS => 'Direction',
        ];
    }

    /**
     * @param list<int> $recipientIds
     *
     * @return list<User>
     */
    private function privateRecipients(User $sender, array $recipientIds): array
    {
        $recipientIds = array_values(array_unique(array_filter($recipientIds)));
        if ($recipientIds === []) {
            return [];
        }

        $users = $this->entityManager->getRepository(User::class)->createQueryBuilder('user')
            ->andWhere('user.active = true')
            ->andWhere('user.id IN (:ids)')
            ->setParameter('ids', $recipientIds)
            ->orderBy('user.firstName', 'ASC')
            ->addOrderBy('user.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->withoutSender($sender, $users);
    }

    /**
     * @return list<User>
     */
    private function usersByRole(User $sender, UserRole $role): array
    {
        $users = $this->entityManager->getRepository(User::class)->findBy(
            ['active' => true, 'role' => $role->value],
            ['firstName' => 'ASC', 'lastName' => 'ASC'],
        );

        return $this->withoutSender($sender, $users);
    }

    /**
     * @return list<User>
     */
    private function allUsersExcept(User $sender): array
    {
        $users = $this->entityManager->getRepository(User::class)->findBy(
            ['active' => true],
            ['role' => 'ASC', 'firstName' => 'ASC', 'lastName' => 'ASC'],
        );

        return $this->withoutSender($sender, $users);
    }

    /**
     * @return list<User>
     */
    private function usersByAgeGroup(User $sender, AgeGroup $ageGroup): array
    {
        $animators = $this->entityManager->getRepository(Animator::class)->findBy(
            ['active' => true, 'ageGroup' => $ageGroup->value],
            ['firstName' => 'ASC', 'lastName' => 'ASC'],
        );

        $users = [];
        foreach ($animators as $animator) {
            $user = $animator->getUser();
            if ($user instanceof User && $user->isActive()) {
                $users[] = $user;
            }
        }

        return $this->withoutSender($sender, $users);
    }

    /**
     * @return list<User>
     */
    private function outingRecipients(User $sender, ?Outing $outing): array
    {
        if (!$outing instanceof Outing) {
            return [];
        }

        $users = $this->usersByRole($sender, UserRole::Director);
        foreach ($outing->getAnimators() as $animator) {
            $user = $animator->getUser();
            if ($user instanceof User && $user->isActive()) {
                $users[] = $user;
            }
        }

        return $this->withoutSender($sender, $users);
    }

    /**
     * @param iterable<User> $users
     *
     * @return list<User>
     */
    private function withoutSender(User $sender, iterable $users): array
    {
        $unique = [];
        foreach ($users as $user) {
            if (!$user instanceof User || $user->getId() === null || $user->getId() === $sender->getId()) {
                continue;
            }

            $unique[$user->getId()] = $user;
        }

        return array_values($unique);
    }
}
