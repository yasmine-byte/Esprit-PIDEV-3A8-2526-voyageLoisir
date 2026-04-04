<?php

namespace App\Form;

use App\Entity\Reclamation;
use App\Entity\TypeAvis;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EntityType::class, [
                'class' => TypeAvis::class,
                'choice_label' => 'nom',
                'label' => 'Type de réclamation'
            ])
            ->add('titre', TextType::class, [
                'label' => 'Titre'
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Détails de la réclamation'
            ])
            ->add('priorite', ChoiceType::class, [
                'choices'  => [
                    'Basse' => 'Basse',
                    'Moyenne' => 'Moyenne',
                    'Haute' => 'Haute',
                    'Urgente' => 'Urgente',
                ],
                'label' => 'Priorité'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer la réclamation',
                'attr' => ['class' => 'btn btn-primary']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}
