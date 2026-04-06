<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\Transport;
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
            ->add("destination", EntityType::class, [
                "class"        => Destination::class,
                "choice_label" => "id",
                "placeholder"  => "-- Choisir une destination --",
                "required"     => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(["data_class" => Transport::class]);
    }
}
