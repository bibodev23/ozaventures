<?php

namespace App\Form;

use App\Entity\DailyAttendance;
use App\Entity\Kid;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DailyAttendanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', null, [
                'widget' => 'single_text',
            ])
            ->add('morning', null, [
                'label'=> 'Matin',
            ])
            ->add('canteen', null, [
                'label'=> 'Cantine',
            ])
            ->add('afternoon', null, [
                'label'=> 'Aprés-midi',
            ])
            ->add('notes', null, [
                'label'=> 'Notes',
            ])
            ->add('kid', EntityType::class, [
                'class' => Kid::class,
                'label' => 'Enfant',
                'choice_label' => 'firstname',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DailyAttendance::class,
        ]);
    }
}
