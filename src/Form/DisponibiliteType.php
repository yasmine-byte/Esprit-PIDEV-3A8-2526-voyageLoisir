<?php
namespace App\Form;

use App\Entity\Disponibilite;
use App\Entity\Hebergement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class DisponibiliteType extends AbstractType
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
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'constraints' => [
                    new NotBlank(message: 'La date de début est obligatoire.'),
                    new GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date de début doit être aujourd\'hui ou dans le futur.'
                    ),
                ],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'constraints' => [
                    new NotBlank(message: 'La date de fin est obligatoire.'),
                ],
            ])
            ->add('disponible', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
            ])
        ;

        // Validation que dateFin > dateDebut
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $dateDebut = $form->get('dateDebut')->getData();
            $dateFin = $form->get('dateFin')->getData();

            if ($dateDebut && $dateFin && $dateFin <= $dateDebut) {
                $form->get('dateFin')->addError(
                    new FormError('La date de fin doit être après la date de début.')
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Disponibilite::class,
        ]);
    }
}