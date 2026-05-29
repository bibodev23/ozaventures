<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\DirectorUserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateurs/direction')]
#[IsGranted('ROLE_DIRECTOR')]
class DirectorUserController extends AbstractController
{
    #[Route('', name: 'app_directors')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $directors = $entityManager->getRepository(User::class)->findBy(
            ['role' => UserRole::Director->value],
            ['active' => 'DESC', 'lastName' => 'ASC', 'firstName' => 'ASC'],
        );

        return $this->render('directors/index.html.twig', [
            'directors' => $directors,
        ]);
    }

    #[Route('/nouveau', name: 'app_director_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $director = (new User())
            ->setRole(UserRole::Director)
            ->setActive(true)
            ->setMustChangePassword(false);

        $form = $this->createForm(DirectorUserType::class, $director);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyPasswordFromForm($director, (string) $form->get('plainPassword')->getData(), $passwordHasher);
            $director->setRole(UserRole::Director);

            $entityManager->persist($director);
            $entityManager->flush();

            $this->addFlash('success', 'Compte direction ajouté.');

            return $this->redirectToRoute('app_directors');
        }

        return $this->render('directors/form.html.twig', [
            'director' => $director,
            'form' => $form,
            'title' => 'Ajouter un compte direction',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_director_edit', requirements: ['id' => '\\d+'])]
    public function edit(User $director, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        if (!$director->isDirector()) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(DirectorUserType::class, $director, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($director === $this->getUser() && !$director->isActive()) {
                $form->get('active')->addError(new FormError('Tu ne peux pas désactiver ton propre compte.'));
            } else {
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword !== null && $plainPassword !== '') {
                    $this->applyPasswordFromForm($director, $plainPassword, $passwordHasher);
                }

                $director
                    ->setRole(UserRole::Director)
                    ->setMustChangePassword(false);

                $entityManager->flush();

                $this->addFlash('success', 'Compte direction mis à jour.');

                return $this->redirectToRoute('app_directors');
            }
        }

        return $this->render('directors/form.html.twig', [
            'director' => $director,
            'form' => $form,
            'title' => 'Modifier un compte direction',
        ]);
    }

    private function applyPasswordFromForm(User $director, string $plainPassword, UserPasswordHasherInterface $passwordHasher): void
    {
        $director->setPasswordHash($passwordHasher->hashPassword($director, $plainPassword));
    }
}
