<?php

namespace App\Controller;

use App\Repository\KidRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Date;
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(KidRepository $kidRepository, UserRepository $userRepository, TaskRepository $taskRepository): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->redirectToRoute('app_login');
        }
        
        return $this->redirectToRoute('app_outing_index');
    }
}
