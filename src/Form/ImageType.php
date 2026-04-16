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
                "label"    => "Images (plusieurs fichiers)",
                "mapped"   => false,
                "required" => false,
                "multiple" => true,
                "constraints" => [
                    new Assert\All([
                        "constraints" => [
                            new Assert\File([
                                "maxSize"          => "5M",
                                "maxSizeMessage"   => "Max 5 Mo par image.",
                                "mimeTypes"        => ["image/jpeg","image/png","image/gif","image/webp"],
                                "mimeTypesMessage" => "Format non accepte (jpg, png, gif, webp).",
                            ]),
                        ],
                    ]),
                ],
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
        $resolver->setDefaults(["data_class" => Image::class]);
    }
}
