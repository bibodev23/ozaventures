<?php

namespace App\Form;

use App\Entity\Child;
use App\Enum\AgeGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Âge',
                'required' => false,
                'help' => 'Entre 3 et 12 ans. Utilisé pour le graphique de répartition du dashboard.',
                'attr' => [
                    'min' => 3,
                    'max' => 12,
                    'inputmode' => 'numeric',
                ],
            ])
            ->add('ageGroup', ChoiceType::class, [
                'label' => 'Groupe',
                'choices' => AgeGroup::choices(),
                'expanded' => true,
            ])
            ->add('legalGuardians', TextareaType::class, [
                'label' => 'Responsables légaux',
                'required' => false,
                'help' => 'Exemple : Samira Dupont (mère), Karim Dupont (père).',
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('legalGuardianPhones', TextType::class, [
                'label' => 'Téléphones responsables',
                'required' => false,
                'help' => 'Numéros utiles pour joindre les responsables.',
            ])
            ->add('allergies', TextareaType::class, [
                'label' => 'Allergies / vigilance médicale',
                'required' => false,
                'help' => 'Laisser vide si aucune allergie connue.',
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('photoPermission', CheckboxType::class, [
                'label' => 'Autorisation photo accordée',
                'required' => false,
            ])
            ->add('importantNotes', TextareaType::class, [
                'label' => 'Notes importantes',
                'required' => false,
                'help' => 'Informations utiles pour la direction et les sorties.',
                'attr' => [
                    'rows' => 4,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Child::class,
        ]);
    }
}
