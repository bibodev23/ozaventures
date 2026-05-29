<?php

namespace App\Form;

use App\Entity\Animator;
use App\Entity\Child;
use App\Entity\Outing;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OutingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $season = $options['season'];

        $builder
            ->add('number', TextType::class, [
                'label' => 'Numéro de sortie',
            ])
            ->add('destination', TextType::class, [
                'label' => 'Lieu de destination',
            ])
            ->add('departureAt', DateTimeType::class, [
                'label' => 'Départ',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('returnAt', DateTimeType::class, [
                'label' => 'Retour',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('transportMode', ChoiceType::class, [
                'label' => 'Mode de transport',
                'choices' => [
                    'À pied' => 'À pied',
                    'Bus' => 'Bus',
                    'Minibus' => 'Minibus',
                    'Transport en commun' => 'Transport en commun',
                    'Autre' => 'Autre',
                ],
            ])
            ->add('picnicRequired', CheckboxType::class, [
                'label' => 'Pique-nique prévu',
                'required' => false,
            ])
            ->add('routeDurationMinutes', IntegerType::class, [
                'label' => 'Temps de trajet estimé (minutes)',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'max' => 600,
                    'placeholder' => 'Ex : 75',
                ],
                'help' => 'À partir de 60 minutes, le suivi localisation est recommandé.',
            ])
            ->add('locationTrackingEnabled', CheckboxType::class, [
                'label' => 'Activer le suivi localisation pour cette sortie',
                'required' => false,
                'help' => 'Les animateurs affectés devront lancer le partage depuis l’app mobile.',
            ])
            ->add('children', EntityType::class, [
                'label' => 'Enfants participants',
                'class' => Child::class,
                'choice_label' => 'directoryLabel',
                'multiple' => true,
                'autocomplete' => true,
                'placeholder' => 'Rechercher les enfants...',
                'loading_more_text' => 'Chargement des enfants...',
                'no_results_found_text' => 'Aucun enfant trouvé',
                'no_more_results_text' => 'Tous les enfants sont affichés',
                'tom_select_options' => [
                    'plugins' => ['remove_button'],
                    'closeAfterSelect' => false,
                    'dropdownParent' => 'body',
                    'hideSelected' => true,
                    'placeholder' => 'Rechercher puis sélectionner les enfants',
                ],
                'query_builder' => fn (EntityRepository $repository) => $repository->createQueryBuilder('child')
                    ->andWhere('child.season = :season')
                    ->setParameter('season', $season)
                    ->orderBy('child.lastName', 'ASC')
                    ->addOrderBy('child.firstName', 'ASC'),
            ])
            ->add('animators', EntityType::class, [
                'label' => 'Animateurs',
                'class' => Animator::class,
                'choice_label' => 'planningLabel',
                'multiple' => true,
                'autocomplete' => true,
                'placeholder' => 'Rechercher les animateurs...',
                'loading_more_text' => 'Chargement des animateurs...',
                'no_results_found_text' => 'Aucun animateur trouvé',
                'no_more_results_text' => 'Tous les animateurs sont affichés',
                'tom_select_options' => [
                    'plugins' => ['remove_button'],
                    'closeAfterSelect' => false,
                    'dropdownParent' => 'body',
                    'hideSelected' => true,
                    'placeholder' => 'Rechercher puis sélectionner les animateurs',
                ],
                'query_builder' => fn (EntityRepository $repository) => $repository->createQueryBuilder('animator')
                    ->andWhere('animator.active = true')
                    ->orderBy('animator.lastName', 'ASC')
                    ->addOrderBy('animator.firstName', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Outing::class,
            'season' => null,
        ]);
        $resolver->setRequired('season');
    }
}
