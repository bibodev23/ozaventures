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
            ->add('title')
            ->add('location', TextType::class, [
                'label'=> 'Lieu',
            ])
            ->add('date', DateType::class)
            ->add('timeGo', TimeType::class, [
                'label'=> 'Heure de départ',
            ])
            ->add('timeBack', TimeType::class, [
                'label'=> 'Heure de retour',
            ])
            ->add('animators', EntityType::class, [
                'class'=> User::class,
                'label' => 'Animateurs',
                'choice_label' => 'username',
                'multiple' => true,
                'expanded' => true
            ])
            ->add('babies', EntityType::class, [
                'class' => Kid::class,
                'label' => 'Enfants 3-5 ans',
                'choice_label' => 'firstname',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'mapped' => false, // on les traite manuellement si besoin
                'query_builder' => function (KidRepository $er) {
                    return $er->createQueryBuilder('k')
                        ->where('k.age >=3 AND k.age <= 5')
                        ->orderBy('k.firstname', 'ASC');
                }
            ])
            ->add('children', EntityType::class, [
                'class' => Kid::class,
                'label' => 'Enfants 6-12 ans',
                'choice_label' => 'firstname',
                'multiple' => true,
                'expanded' => true,
                'required' => true,
                'mapped'=> false,
                'query_builder' => function (KidRepository $er) {
                    return $er->createQueryBuilder('k')
                        ->where('k.age >=6 AND k.age <= 12')
                        ->orderBy('k.firstname', 'ASC');
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
