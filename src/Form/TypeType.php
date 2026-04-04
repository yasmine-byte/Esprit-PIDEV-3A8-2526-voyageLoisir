<?php
namespace App\Form;

use App\Entity\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class TypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du type',
                'attr' => [
                    'placeholder' => 'Ex: Hotel, Villa, Appartement...',
                    'class' => 'form-input',
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom du type est obligatoire.'),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Regex(
                        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/',
                        message: 'Le nom ne doit contenir que des lettres.'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Type::class,
        ]);
    }
}