<?php

namespace App\Controller;

use App\Repository\KidRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(KidRepository $kidRepository): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        if (!$user)  {
            return $this->redirectToRoute('app_login');
        }

        $kids = $kidRepository->findAll();
        $numberOfKids = count($kids);
        
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'kids' => $kidRepository->findAll(),
            'numberOfKids'=> $numberOfKids,
        ]);
    }
}
