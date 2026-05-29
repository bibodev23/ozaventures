<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DirectorUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];
        $passwordConstraints = [
            new Assert\Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
        ];

        if (!$isEdit) {
            $passwordConstraints[] = new Assert\NotBlank(message: 'Choisis un mot de passe initial.');
        }

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('username', TextType::class, [
                'label' => 'Identifiant de connexion',
                'help' => 'Exemple : directrice, adjoint, direction2',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => !$isEdit,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints' => $passwordConstraints,
                'first_options' => [
                    'label' => $isEdit ? 'Nouveau mot de passe' : 'Mot de passe initial',
                    'help' => $isEdit ? 'Laisse vide pour conserver le mot de passe actuel.' : 'Le compte direction pourra se connecter à la webapp et à l’app mobile.',
                ],
                'second_options' => [
                    'label' => 'Confirmation du mot de passe',
                ],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
