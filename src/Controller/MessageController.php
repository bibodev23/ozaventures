<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\MessageRecipient;
use App\Entity\Outing;
use App\Entity\User;
use App\Service\ActiveSeasonProvider;
use App\Service\InternalMessageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages')]
#[IsGranted('ROLE_USER')]
class MessageController extends AbstractController
{
    #[Route('', name: 'app_messages')]
    public function inbox(EntityManagerInterface $entityManager): Response
    {
        $user = $this->currentUser();
        $recipients = $entityManager->getRepository(MessageRecipient::class)->createQueryBuilder('recipient')
            ->innerJoin('recipient.message', 'message')
            ->addSelect('message')
            ->innerJoin('message.sender', 'sender')
            ->addSelect('sender')
            ->andWhere('recipient.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('message.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('messages/index.html.twig', [
            'mode' => 'inbox',
            'recipient_rows' => $recipients,
            'messages' => [],
            'unread_count' => $this->unreadCount($entityManager, $user),
        ]);
    }

    #[Route('/envoyes', name: 'app_messages_sent')]
    public function sent(EntityManagerInterface $entityManager): Response
    {
        $user = $this->currentUser();
        $messages = $entityManager->getRepository(Message::class)->findBy(
            ['sender' => $user],
            ['createdAt' => 'DESC'],
        );

        return $this->render('messages/index.html.twig', [
            'mode' => 'sent',
            'recipient_rows' => [],
            'messages' => $messages,
            'unread_count' => $this->unreadCount($entityManager, $user),
        ]);
    }

    #[Route('/nouveau', name: 'app_message_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActiveSeasonProvider $seasonProvider,
        InternalMessageService $messageService,
    ): Response {
        $user = $this->currentUser();
        $error = null;

        if ($request->isMethod('POST')) {
            try {
                $audience = (string) $request->request->get('audience', Message::AUDIENCE_PRIVATE);
                $recipientIds = array_map('intval', (array) $request->request->all('recipientIds'));
                $outing = $this->outingFromRequest($request, $entityManager);

                if ($audience === Message::AUDIENCE_OUTING && !$this->canUseOuting($user, $outing)) {
                    throw new \InvalidArgumentException('Tu ne peux pas cibler cette sortie.');
                }

                $message = $messageService->createMessage(
                    $user,
                    $audience,
                    (string) $request->request->get('subject', ''),
                    (string) $request->request->get('body', ''),
                    $recipientIds,
                    $outing,
                );

                $entityManager->flush();

                $this->addFlash('success', 'Message envoyé.');

                return $this->redirectToRoute('app_message_show', ['id' => $message->getId()]);
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('messages/form.html.twig', [
            'audience_choices' => $messageService->audienceChoices($user),
            'users' => $this->availablePrivateRecipients($entityManager, $user),
            'outings' => $this->availableOutings($entityManager, $seasonProvider, $user),
            'error' => $error,
        ]);
    }

    #[Route('/{id}', name: 'app_message_show', requirements: ['id' => '\d+'])]
    public function show(Message $message, EntityManagerInterface $entityManager): Response
    {
        $user = $this->currentUser();
        $recipient = $this->recipientFor($message, $user);
        if ($message->getSender() !== $user && !$recipient instanceof MessageRecipient) {
            throw $this->createAccessDeniedException();
        }

        if ($recipient instanceof MessageRecipient && !$recipient->isRead()) {
            $recipient->markRead();
            $entityManager->flush();
        }

        return $this->render('messages/show.html.twig', [
            'message' => $message,
            'is_sender' => $message->getSender() === $user,
        ]);
    }

    /**
     * @return list<User>
     */
    private function availablePrivateRecipients(EntityManagerInterface $entityManager, User $currentUser): array
    {
        $users = $entityManager->getRepository(User::class)->createQueryBuilder('user')
            ->andWhere('user.active = true')
            ->andWhere('user.id != :currentUserId')
            ->setParameter('currentUserId', $currentUser->getId())
            ->orderBy('user.role', 'ASC')
            ->addOrderBy('user.firstName', 'ASC')
            ->addOrderBy('user.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($users, fn (User $user): bool => $user->getId() !== $currentUser->getId()));
    }

    /**
     * @return list<Outing>
     */
    private function availableOutings(EntityManagerInterface $entityManager, ActiveSeasonProvider $seasonProvider, User $currentUser): array
    {
        $queryBuilder = $entityManager->getRepository(Outing::class)->createQueryBuilder('outing')
            ->leftJoin('outing.animators', 'animator')
            ->addSelect('animator')
            ->andWhere('outing.season = :season')
            ->setParameter('season', $seasonProvider->getActiveSeason())
            ->orderBy('outing.departureAt', 'DESC')
            ->setMaxResults(30);

        if (!$currentUser->isDirector()) {
            $animator = $currentUser->getAnimator();
            if ($animator === null) {
                return [];
            }

            $queryBuilder
                ->andWhere('outing.createdBy = :animator OR animator = :animator')
                ->setParameter('animator', $animator);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function outingFromRequest(Request $request, EntityManagerInterface $entityManager): ?Outing
    {
        $outingId = (int) $request->request->get('outingId', 0);
        if ($outingId <= 0) {
            return null;
        }

        return $entityManager->getRepository(Outing::class)->find($outingId);
    }

    private function canUseOuting(User $user, ?Outing $outing): bool
    {
        if (!$outing instanceof Outing) {
            return false;
        }

        if ($user->isDirector()) {
            return true;
        }

        $animator = $user->getAnimator();

        return $animator !== null && ($outing->getCreatedBy() === $animator || $outing->getAnimators()->contains($animator));
    }

    private function recipientFor(Message $message, User $user): ?MessageRecipient
    {
        foreach ($message->getRecipients() as $recipient) {
            if ($recipient->getRecipient() === $user) {
                return $recipient;
            }
        }

        return null;
    }

    private function unreadCount(EntityManagerInterface $entityManager, User $user): int
    {
        return (int) $entityManager->getRepository(MessageRecipient::class)->createQueryBuilder('recipient')
            ->select('COUNT(recipient.id)')
            ->andWhere('recipient.recipient = :user')
            ->andWhere('recipient.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
