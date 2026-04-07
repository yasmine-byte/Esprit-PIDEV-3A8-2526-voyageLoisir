<?php
namespace App\Form;

use App\Entity\Chambre;
use App\Entity\Hebergement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Regex;

class ChambreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('hebergement', EntityType::class, [
                'class' => Hebergement::class,
                'choice_label' => 'description',
                'placeholder' => '-- Choisir un hébergement --',
                'label' => 'Hébergement',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotNull(message: 'L\'hébergement est obligatoire.'),
                ],
            ])
            ->add('numero', TextType::class, [
                'label' => 'Numéro de chambre',
                'attr' => [
                    'placeholder' => 'Ex: 101',
                    'class' => 'form-input',
                    'maxlength' => 10,
                ],
                'constraints' => [
                    new NotBlank(message: 'Le numéro de chambre est obligatoire.'),
                    new Length(
                        max: 10,
                        maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Regex(
                        pattern: '/^[a-zA-Z0-9]+$/',
                        message: 'Le numéro ne doit contenir que des lettres et des chiffres.'
                    ),
                ],
            ])
            ->add('typeChambre', ChoiceType::class, [
                'choices' => [
                    'Simple' => 'simple',
                    'Double' => 'double',
                    'Suite' => 'suite',
                    'Familiale' => 'familiale',
                ],
                'placeholder' => '-- Type de chambre --',
                'label' => 'Type de chambre',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(message: 'Le type de chambre est obligatoire.'),
                ],
            ])
            ->add('prixNuit', NumberType::class, [
                'label' => 'Prix / nuit (TND)',
                'attr' => [
                    'placeholder' => 'Ex: 80.00',
                    'min' => 0,
                    'step' => '0.01',
                    'class' => 'form-input',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le prix par nuit est obligatoire.'),
                    new Positive(message: 'Le prix doit être un nombre positif.'),
                ],
            ])
            ->add('capacite', IntegerType::class, [
                'label' => 'Capacité (personnes)',
                'attr' => [
                    'placeholder' => 'Ex: 2',
                    'min' => 1,
                    'max' => 20,
                    'class' => 'form-input',
                ],
                'constraints' => [
                    new NotBlank(message: 'La capacité est obligatoire.'),
                    new Positive(message: 'La capacité doit être un nombre positif.'),
                    new LessThanOrEqual(
                        value: 20,
                        message: 'La capacité ne peut pas dépasser {{ compared_value }} personnes.'
                    ),
                ],
            ])
            ->add('equipements', TextareaType::class, [
                'label' => 'Équipements',
                'attr' => [
                    'placeholder' => 'Ex: TV, Climatisation, WiFi, Minibar...',
                    'rows' => 3,
                    'class' => 'form-input',
                ],
                'constraints' => [
                    new NotBlank(message: 'Les équipements sont obligatoires.'),
                    new Length(
                        min: 3,
                        minMessage: 'Les équipements doivent contenir au moins {{ limit }} caractères.'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Chambre::class,
        ]);
    }
}