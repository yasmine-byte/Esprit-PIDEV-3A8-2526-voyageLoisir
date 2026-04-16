<?php

namespace App\Form;

use App\Entity\Avis;
use App\Entity\TypeAvis;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EntityType::class, [
                'class' => TypeAvis::class,
                'choice_label' => 'nom',
                'label' => 'Type d\'avis'
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Votre avis'
            ])
            ->add('nbEtoiles', ChoiceType::class, [
                'choices' => [
                    '1 étoile' => 1,
                    '2 étoiles' => 2,
                    '3 étoiles' => 3,
                    '4 étoiles' => 4,
                    '5 étoiles' => 5,
                ],
                'label' => 'Note',
                'expanded' => false,
                'multiple' => false,
            ])
            ->add('statut', HiddenType::class, [
                'data' => 'En attente',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre mon avis',
                'attr' => ['class' => 'btn btn-primary']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avis::class,
        ]);
    }
}
