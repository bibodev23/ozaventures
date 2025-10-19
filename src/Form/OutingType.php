<?php

namespace App\Form;

use App\Entity\Kid;
use App\Entity\Outing;
use App\Entity\User;
use App\Repository\KidRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OutingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
            ])
            ->add('date', DateType::class)
            ->add('timeGo', TimeType::class, [
                'label' => 'Heure de départ',
            ])
            ->add('timeBack', TimeType::class, [
                'label' => 'Heure de retour',
            ])
            ->add('animators', EntityType::class, [
                'class' => User::class,
                'label' => 'Animateurs',
                'choice_label' => 'username',
                'multiple' => true,
                'expanded' => true
            ])
            ->add('kids', EntityType::class, [
                'class' => Kid::class,
                'label' => 'Enfants',
                'choice_label' => 'firstname',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'choice_attr' => function (Kid $kid) {
                    if ($kid->getAge() >= 3 && $kid->getAge() <= 5) {
                        return ['data-group' => '3-5'];
                    } elseif ($kid->getAge() >= 6 && $kid->getAge() <= 12) {
                        return ['data-group' => '6-12'];
                    }
                    return [];
                },
            ])

            ->add('notes')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Outing::class,
        ]);
    }
}
