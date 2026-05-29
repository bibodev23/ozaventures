<?php

namespace App\Controller;

use App\Entity\Outing;
use App\Entity\Season;
use App\Enum\OutingStatus;
use App\Form\OutingType;
use App\Service\ActiveSeasonProvider;
use App\Service\MobileNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sorties')]
#[IsGranted('ROLE_DIRECTION')]
class OutingController extends AbstractController
{
    #[Route('', name: 'app_outings')]
    public function index(Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $selectedStatus = (string) $request->query->get('status', '');
        $allowedStatuses = array_map(
            fn (OutingStatus $status): string => $status->value,
            OutingStatus::cases(),
        );

        if (!in_array($selectedStatus, $allowedStatuses, true)) {
            $selectedStatus = '';
        }

        $outingsQuery = $entityManager->getRepository(Outing::class)->createQueryBuilder('outing')
            ->andWhere('outing.season = :season')
            ->setParameter('season', $season)
            ->orderBy('outing.departureAt', 'ASC');

        if ($selectedStatus !== '') {
            $outingsQuery
                ->andWhere('outing.status = :status')
                ->setParameter('status', $selectedStatus);
        }

        return $this->render('outings/index.html.twig', [
            'season' => $season,
            'outings' => $outingsQuery->getQuery()->getResult(),
            'selected_status' => $selectedStatus,
            'pending_count' => $entityManager->getRepository(Outing::class)->count(['season' => $season, 'status' => OutingStatus::Pending->value]),
            'validated_count' => $entityManager->getRepository(Outing::class)->count(['season' => $season, 'status' => OutingStatus::Validated->value]),
            'refused_count' => $entityManager->getRepository(Outing::class)->count(['season' => $season, 'status' => OutingStatus::Refused->value]),
        ]);
    }

    #[Route('/nouvelle', name: 'app_outing_new')]
    public function new(Request $request, ActiveSeasonProvider $seasonProvider, EntityManagerInterface $entityManager, MobileNotificationService $notificationService): Response
    {
        $season = $seasonProvider->getActiveSeason();
        $outing = (new Outing())
            ->setSeason($season)
            ->setNumber($this->nextOutingNumber($entityManager, $season));

        $form = $this->createForm(OutingType::class, $outing, ['season' => $season]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($outing->touch());
            $entityManager->flush();

            $this->addFlash('success', 'Sortie créée.');
            if ($request->request->getBoolean('notify_animators')) {
                $notificationResult = $notificationService->notifyOutingAssigned($outing);
                if ($notificationResult['sent'] > 0) {
                    $this->addFlash('success', sprintf('%d notification(s) envoyée(s) aux animateurs.', $notificationResult['sent']));
                }
            }

            return $this->redirectToRoute('app_outing_show', ['id' => $outing->getId()]);
        }

        return $this->render('outings/form.html.twig', [
            'outing' => $outing,
            'form' => $form,
            'title' => 'Créer une sortie',
        ]);
    }

    #[Route('/{id}', name: 'app_outing_show', requirements: ['id' => '\d+'])]
    public function show(Outing $outing): Response
    {
        return $this->render('outings/show.html.twig', [
            'outing' => $outing,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_outing_edit', requirements: ['id' => '\d+'])]
    public function edit(Outing $outing, Request $request, EntityManagerInterface $entityManager, MobileNotificationService $notificationService): Response
    {
        $form = $this->createForm(OutingType::class, $outing, ['season' => $outing->getSeason()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $outing->touch();
            $entityManager->flush();

            $this->addFlash('success', 'Sortie mise à jour.');
            if ($request->request->getBoolean('notify_animators')) {
                $notificationResult = $notificationService->notifyOutingUpdated($outing);
                if ($notificationResult['sent'] > 0) {
                    $this->addFlash('success', sprintf('%d notification(s) envoyée(s) aux animateurs.', $notificationResult['sent']));
                }
            }

            return $this->redirectToRoute('app_outing_show', ['id' => $outing->getId()]);
        }

        return $this->render('outings/form.html.twig', [
            'outing' => $outing,
            'form' => $form,
            'title' => 'Modifier la sortie',
        ]);
    }

    #[Route('/{id}/statut/{status}', name: 'app_outing_status', requirements: ['id' => '\d+', 'status' => 'pending|validated|refused'], methods: ['POST'])]
    public function status(Outing $outing, string $status, Request $request, EntityManagerInterface $entityManager, MobileNotificationService $notificationService): Response
    {
        if (!$this->isCsrfTokenValid('outing_status_' . $outing->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $outing
            ->setStatus($status)
            ->setValidatedAt($status === OutingStatus::Validated->value ? new \DateTimeImmutable() : null)
            ->touch();

        $entityManager->flush();

        $this->addFlash('success', 'Statut de la sortie mis à jour.');
        if ($request->request->getBoolean('notify_animators')) {
            $notificationResult = $notificationService->notifyOutingStatusUpdated($outing);
            if ($notificationResult['sent'] > 0) {
                $this->addFlash('success', sprintf('%d notification(s) envoyée(s) aux animateurs.', $notificationResult['sent']));
            }
        }

        return $this->redirectToRoute('app_outing_show', ['id' => $outing->getId()]);
    }

    #[Route('/{id}/affiche-a3', name: 'app_outing_print', requirements: ['id' => '\d+'])]
    public function print(Outing $outing): Response
    {
        return $this->render('outings/print.html.twig', [
            'outing' => $outing,
        ]);
    }

    private function nextOutingNumber(EntityManagerInterface $entityManager, Season $season): string
    {
        $count = $entityManager->getRepository(Outing::class)->count(['season' => $season]);

        return (string) ($count + 1);
    }
}
