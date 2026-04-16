<?php

namespace App\Form;

use App\Entity\Transport;
use App\Entity\Voyage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add("type_transport", ChoiceType::class, [
                "label"       => "Type de Transport",
                "choices"     => [
                    "Avion"   => "Avion",
                    "Bus"     => "Bus",
                    "Voiture" => "Voiture",
                    "Train"   => "Train",
                ],
                "expanded"    => false,
                "multiple"    => false,
                "placeholder" => "-- Choisir un transport --",
                "required"    => true,
            ])
            ->add("voyage", EntityType::class, [
                "class"        => Voyage::class,
                "choice_label" => "id",
                "placeholder"  => "-- Choisir un voyage --",
                "required"     => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(["data_class" => Transport::class]);
    }
}
