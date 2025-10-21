<?php

namespace App\Form;

use App\Entity\Schedule;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label'=> 'Date',
            ])
            ->add('am_start', TimeType::class, [
                'label'=> 'Arrivée matin',
            ])
            ->add('am_end', TimeType::class, [
                'label'=> 'Départ matin',
            ])
            ->add('pm_start', TimeType::class, [
                'label'=> 'Arrivée aprés-midi',
            ])
            ->add('pm_end', TimeType::class, [
                'label'=> 'Départ aprés-midi',
            ])
            ->add('notes')
            ->add('user', EntityType::class, [
                'class' => User::class,
                'label' => 'Animateur',
                'choice_label' => 'username',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Schedule::class,
        ]);
    }
}
