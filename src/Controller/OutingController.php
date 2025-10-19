<?php

namespace App\Controller;

use App\Entity\Outing;
use App\Entity\User;
use App\Form\OutingType;
use App\Repository\OutingRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\Expr\Cast\Void_;
use Spatie\Browsershot\Browsershot;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/outing')]
final class OutingController extends AbstractController
{
    #[Route(name: 'app_outing_index', methods: ['GET'])]
    public function index(OutingRepository $outingRepository): Response
    {
        return $this->render('outing/index.html.twig', [
            'outings' => $outingRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_outing_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $outing = new Outing();
        $form = $this->createForm(OutingType::class, $outing);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $outing->setCreatedAt(new \DateTimeImmutable());
            $outing->setCreatedBy($user);
            $entityManager->persist($outing);
            $entityManager->flush();

            return $this->redirectToRoute('app_outing_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('outing/new.html.twig', [
            'outing' => $outing,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_outing_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Outing $outing, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(OutingType::class, $outing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $outing->setUpdatedAt(new \DateTime());
            $outing->setUpdatedBy($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_outing_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('outing/edit.html.twig', [
            'outing' => $outing,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_outing_show', methods: ['GET'])]
    public function show(Outing $outing): Response
    {
        return $this->render('outing/show.html.twig', [
            'outing' => $outing,
        ]);
    }

    #[Route('/{id}', name: 'app_outing_delete', methods: ['POST'])]
    public function delete(Request $request, Outing $outing, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$outing->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($outing);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_outing_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/download', name:'app_outing_download', methods: ['GET'])]
    public function download(Outing $outing): Response
    {
        $thmlContent = $this->renderView('outing/pdf.html.twig', [
            'outing' => $outing,
        ]);

        $pdf = Browsershot::html($thmlContent)
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->showBackground()
            ->emulateMedia('screen')
            ->orientation('portrait')
            ->format('A3')
            ->showBackground()
            ->setOption('waintUntil', 'networkidle0')
            ->timeout(60)
            ->pdf();

        $filename = sprintf('sortie-%s.pdf', $outing->getId());
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="outing.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
