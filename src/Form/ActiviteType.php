<?php

namespace App\Form;

use App\Entity\Activite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l’activité',
                'invalid_message' => 'Le nom est invalide.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Escalade en Montagne',
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'invalid_message' => 'La description est invalide.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Décrivez l’activité...',
                    'rows' => 5,
                ],
            ])

            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'placeholder' => 'Choisir un type',
                'choices' => [
                    'Sport' => 'Sport',
                    'Culture' => 'Culture',
                    'Aventure' => 'Aventure',
                    'Loisir' => 'Loisir',
                    'Nature' => 'Nature',
                    'Bien-être' => 'Bien-être',
                    'Famille' => 'Famille',
                ],
                'invalid_message' => 'Veuillez choisir un type valide.',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])

            ->add('prix', NumberType::class, [
                'label' => 'Prix',
                'invalid_message' => 'Le prix doit être un nombre valide.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 60',
                    'min' => 1,
                    'step' => '0.01',
                ],
            ])

            ->add('duree', IntegerType::class, [
                'label' => 'Durée (heures)',
                'invalid_message' => 'La durée doit être un nombre entier.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 4',
                    'min' => 1,
                ],
            ])

            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'invalid_message' => 'Le lieu est invalide.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Zaghouan',
                ],
            ])

            ->add('imageUrl', UrlType::class, [
                'label' => 'URL de l’image',
                'required' => false,
                'invalid_message' => 'Veuillez entrer une URL valide.',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://exemple.com/image.jpg',
                ],
            ])

            ->add('aiRating', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activite::class,
        ]);
    }
}