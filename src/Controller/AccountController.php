<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\User;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AccountController extends AbstractController
{
    #[Route('/mon-mot-de-passe', name: 'app_account_password')]
    #[IsGranted('ROLE_ANIMATOR')]
    public function password(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $animator = $user->getAnimator();
        if (!$animator instanceof Animator) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError('Mot de passe actuel incorrect.'));
            } else {
                $user
                    ->setPasswordHash($passwordHasher->hashPassword($user, $form->get('newPassword')->getData()))
                    ->setMustChangePassword(false);
                $animator
                    ->setPasswordHash($user->getPassword() ?? '')
                    ->setMustChangePassword(false);

                $entityManager->flush();
                $this->addFlash('success', 'Ton mot de passe a été modifié.');

                return $this->redirectToRoute('app_account_password');
            }
        }

        return $this->render('account/password.html.twig', [
            'animator' => $animator,
            'form' => $form,
        ]);
    }
}
