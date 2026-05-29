<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'toggle' => true,
                'visible_label' => 'Afficher',
                'hidden_label' => 'Masquer',
                'constraints' => [
                    new Assert\NotBlank(message: 'Renseigne ton mot de passe actuel.'),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Choisis un nouveau mot de passe.'),
                    new Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                ],
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'toggle' => true,
                    'visible_label' => 'Afficher',
                    'hidden_label' => 'Masquer',
                ],
                'second_options' => [
                    'label' => 'Confirmation du nouveau mot de passe',
                    'toggle' => true,
                    'visible_label' => 'Afficher',
                    'hidden_label' => 'Masquer',
                ],
            ]);
    }
}
