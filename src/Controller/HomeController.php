<?php

namespace App\Controller;

use App\Repository\KidRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\ChartService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(KidRepository $kidRepo, UserRepository $userRepo, TaskRepository $taskRepo, ChartService $chartService, ChartBuilderInterface $chartBuilder): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_login');
        }
        
        $countKids3To5 = count($kidRepo->listKids3To5YearsOld());
        $countKids6To12 = count($kidRepo->listKids6To12YearsOld());
        $chart = $chartService->createChart($countKids3To5, $countKids6To12,  $chartBuilder);

        return $this->render('home/index.html.twig', [
            'kids' => $kidRepo->findAll(),
            'animators' => $userRepo->findAll(),
            'tasks' => $taskRepo->findAllByDate(),
            'chart' => $chart,
        ]);
    }
}
