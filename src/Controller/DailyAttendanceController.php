<?php

namespace App\Controller;

use App\Entity\DailyAttendance;
use App\Form\DailyAttendanceType;
use App\Repository\DailyAttendanceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/daily/attendance')]
final class DailyAttendanceController extends AbstractController
{
    #[Route(name: 'app_daily_attendance_index', methods: ['GET'])]
    public function index(DailyAttendanceRepository $dailyAttendanceRepository): Response
    {
        
        return $this->render('daily_attendance/index.html.twig', [
            'daily_attendances' => $dailyAttendanceRepository->findAllByDate(),
        ]);
    }

    #[Route('/new', name: 'app_daily_attendance_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dailyAttendance = new DailyAttendance();
        $dailyAttendance->setDate(new DateTimeImmutable());
        $form = $this->createForm(DailyAttendanceType::class, $dailyAttendance);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($dailyAttendance);
            $entityManager->flush();

            return $this->redirectToRoute('app_daily_attendance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('daily_attendance/new.html.twig', [
            'daily_attendance' => $dailyAttendance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_daily_attendance_show', methods: ['GET'])]
    public function show(DailyAttendance $dailyAttendance): Response
    {
        return $this->render('daily_attendance/show.html.twig', [
            'daily_attendance' => $dailyAttendance,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_daily_attendance_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DailyAttendance $dailyAttendance, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DailyAttendanceType::class, $dailyAttendance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_daily_attendance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('daily_attendance/edit.html.twig', [
            'daily_attendance' => $dailyAttendance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_daily_attendance_delete', methods: ['POST'])]
    public function delete(Request $request, DailyAttendance $dailyAttendance, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$dailyAttendance->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($dailyAttendance);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_daily_attendance_index', [], Response::HTTP_SEE_OTHER);
    }
}
