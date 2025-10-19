<?php

namespace App\Form;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DailyAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class)
            ->add('listingmatin', EntityType::class, [
                'class' => User::class,
                'label' => 'Listing matin',
                'choice_label' => 'username',
                'required' => false,
                'mapped' => false,
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false
            ])
            ->add('cantine', EntityType::class, [
                'class' => User::class,
                'label'=> 'Cantine',
                'choice_label' => 'username',
                'required' => false,
                'mapped'=> false,
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false
            ])
            ->add('listingmidi', EntityType::class, [
                'class' => User::class,
                'label'=> 'Listing 13h30 - 14h30',
                'choice_label' => 'username',
                'required' => false,
                'mapped'=> false,
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false
            ])
            ->add('gouter', EntityType::class, [
                'class' => User::class,
                'label'=> 'Gouter',
                'choice_label' => 'username',
                'required' => false,
                'mapped'=> false,
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false
            ])

            ->add('vaisselle', EntityType::class, [
                'class' => User::class,
                'label'=> 'Vaisselle',
                'choice_label' => 'username',
                'required' => false,
                'mapped'=> false,
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false
            ])
            ->add('listingsoir', EntityType::class, [
                'class' => User::class,
                'label'=> 'Listing 17h30 - 18h',
                'choice_label' => 'username',
                'required' => false,
                'mapped'=> false,
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
