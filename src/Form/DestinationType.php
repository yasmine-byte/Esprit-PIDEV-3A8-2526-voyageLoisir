<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\Voyage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DestinationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add("nom", TextType::class, [
                "label"      => "Nom",
                "required"   => true,
                "empty_data" => "",
            ])
            ->add("pays", TextType::class, [
                "label"      => "Pays",
                "required"   => true,
                "empty_data" => "",
            ])
            ->add("description", TextareaType::class, [
                "label"    => "Description",
                "required" => false,
            ])
            ->add("statut", ChoiceType::class, [
                "label"    => "Statut",
                "choices"  => ["Actif" => true, "Inactif" => false],
                "expanded" => false,
                "multiple" => false,
                "required" => false,
            ])
            ->add("meilleure_saison", ChoiceType::class, [
                "label"       => "Meilleure Saison",
                "choices"     => [
                    "Printemps" => "Printemps",
                    "Ete"       => "Ete",
                    "Automne"   => "Automne",
                    "Hiver"     => "Hiver",
                ],
                "placeholder" => "-- Choisir une saison --",
                "expanded"    => false,
                "multiple"    => false,
                "required"    => true,
            ])
            ->add("latitude", NumberType::class, [
                "label"      => "Latitude",
                "scale"      => 6,
                "required"   => true,
                "empty_data" => null,
            ])
            ->add("longitude", NumberType::class, [
                "label"      => "Longitude",
                "scale"      => 6,
                "required"   => true,
                "empty_data" => null,
            ])
            ->add("nb_visites", IntegerType::class, [
                "label"      => "Nb Visites",
                "required"   => true,
                "empty_data" => null,
            ])
            ->add("video_path", FileType::class, [
                "label"    => "Video",
                "mapped"   => false,
                "required" => false,
            ])
            ->add("voyage", EntityType::class, [
                "class"        => Voyage::class,
                "choice_label" => "id",
                "placeholder"  => "-- Choisir un voyage --",
                "required"     => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(["data_class" => Destination::class]);
    }
}
