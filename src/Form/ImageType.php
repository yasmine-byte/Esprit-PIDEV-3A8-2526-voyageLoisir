<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\Image;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add("url_image", FileType::class, [
                "label"    => "Image (fichier)",
                "mapped"   => false,
                "required" => true,
                "attr"     => ["accept" => "image/*"],
                "constraints" => [
                    new Assert\NotNull(message: "L image est obligatoire."),
                    new Assert\File([
                        "maxSize"          => "5M",
                        "maxSizeMessage"   => "Max 5 Mo.",
                        "mimeTypes"        => ["image/jpeg","image/png","image/gif","image/webp"],
                        "mimeTypesMessage" => "Format non accepte (jpg, png, gif, webp).",
                    ]),
                ],
            ])
            ->add("destination", EntityType::class, [
                "class"        => Destination::class,
                "choice_label" => "nom",
                "placeholder"  => "-- Choisir une destination --",
                "required"     => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(["data_class" => Image::class]);
    }
}
