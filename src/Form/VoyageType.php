<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\Voyage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VoyageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date_depart', DateType::class, [
                'widget' => 'single_text',
                'label'  => 'Date de départ',
            ])
            ->add('date_arrivee', DateType::class, [
                'widget' => 'single_text',
                'label'  => "Date d'arrivée",
            ])
            ->add('point_depart', null, [
                'label' => 'Point de départ',
                'attr'  => ['placeholder' => 'Ex: Tunis'],
            ])
            ->add('point_arrivee', null, [
                'label' => "Point d'arrivée",
                'attr'  => ['placeholder' => 'Ex: Paris'],
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (€)',
                'attr'  => ['placeholder' => 'Ex: 850'],
            ])
            ->add('destination', EntityType::class, [
                'class'        => Destination::class,
                'choice_label' => 'nom',
                'placeholder'  => '-- Choisir une destination --',
                'required'     => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voyage::class,
        ]);
    }
}