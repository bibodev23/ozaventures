<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/apres-connexion', name: 'app_after_login')]
    public function afterLogin(): Response
    {
        if ($this->isGranted('ROLE_DIRECTION')) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($this->isGranted('ROLE_ANIMATOR')) {
            return $this->redirectToRoute('app_account_password');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This route is intercepted by Symfony Security.');
    }
}
