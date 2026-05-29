<?php

namespace App\Controller;

use App\Entity\Animator;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\AnimatorType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/animateurs')]
#[IsGranted('ROLE_DIRECTOR')]
class AnimatorController extends AbstractController
{
    #[Route('', name: 'app_animators')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('animators/index.html.twig', [
            'animators' => $entityManager->getRepository(Animator::class)->findBy([], ['active' => 'DESC', 'lastName' => 'ASC', 'firstName' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_animator_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $animator = new Animator();
        $form = $this->createForm(AnimatorType::class, $animator);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->syncUserFromAnimator($animator);
            $this->applyPasswordFromForm($animator, $user, $form->get('plainPassword')->getData(), $passwordHasher);
            $entityManager->persist($user);
            $entityManager->persist($animator);
            $entityManager->flush();

            $this->addFlash('success', 'Animateur ajouté.');

            return $this->redirectToRoute('app_animators');
        }

        return $this->render('animators/form.html.twig', [
            'animator' => $animator,
            'form' => $form,
            'title' => 'Ajouter un animateur',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_animator_edit', requirements: ['id' => '\d+'])]
    public function edit(Animator $animator, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(AnimatorType::class, $animator, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->syncUserFromAnimator($animator);
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword !== null && $plainPassword !== '') {
                $this->applyPasswordFromForm($animator, $user, $plainPassword, $passwordHasher);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Animateur mis à jour.');

            return $this->redirectToRoute('app_animators');
        }

        return $this->render('animators/form.html.twig', [
            'animator' => $animator,
            'form' => $form,
            'title' => 'Modifier un animateur',
        ]);
    }

    private function applyPasswordFromForm(
        Animator $animator,
        User $user,
        string $plainPassword,
        UserPasswordHasherInterface $passwordHasher,
    ): void {
        $user
            ->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword))
            ->setMustChangePassword(true);

        $animator
            ->setPasswordHash($user->getPassword() ?? '')
            ->setMustChangePassword(true);
    }

    private function syncUserFromAnimator(Animator $animator): User
    {
        $user = $animator->getUser() ?? new User();

        $user
            ->setUsername($animator->getUsername())
            ->setFirstName($animator->getFirstName())
            ->setLastName($animator->getLastName())
            ->setActive($animator->isActive())
            ->setRole(UserRole::Animator)
            ->setMustChangePassword($animator->mustChangePassword());

        $animator->setUser($user);

        return $user;
    }
}
