<?php

namespace App\Form;

use App\Entity\Users;
use App\Entity\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;

class AddUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom *',
                'attr' => [
                    'placeholder' => 'Ex : Dupont',
                    'maxlength' => 100
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom *',
                'attr' => [
                    'placeholder' => 'Ex : Jean',
                    'maxlength' => 100
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email *',
                'attr' => [
                    'placeholder' => 'exemple@email.com'
                ]
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone *',
                'required' => true,
                'attr' => [
                    'placeholder' => '8 chiffres',
                    'maxlength' => 8,
                    'pattern' => '[0-9]{8}'
                ],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[0-9]{8}$/',
                        'message' => 'Le téléphone doit contenir exactement 8 chiffres.'
                    ])
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Mot de passe *',
                    'attr' => [
                        'placeholder' => 'Minimum 6 caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe *',
                    'attr' => [
                        'placeholder' => 'Répétez le mot de passe'
                    ]
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_#\-])[A-Za-z\d@$!%*?&_#\-]{6,}$/',
                        'message' => 'Le mot de passe doit contenir au moins 6 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.'
                    ])
                ]
            ])
            ->add('roles', EntityType::class, [
                'label' => 'Rôles *',
                'class' => Role::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'required' => true
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
                'data' => true
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Users::class,
            'validation_groups' => ['registration'],
        ]);
    }
}
