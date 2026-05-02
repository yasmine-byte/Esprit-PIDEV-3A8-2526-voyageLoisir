<?php

namespace App\Form;

use App\Entity\Avis;
use App\Entity\TypeAvis;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EntityType::class, [
                'class' => TypeAvis::class,
                'choice_label' => 'nom',
                'label' => 'Type d\'avis'
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Votre avis'
            ])
            ->add('nbEtoiles', ChoiceType::class, [
                'choices' => [
                    '1 étoile' => 1,
                    '2 étoiles' => 2,
                    '3 étoiles' => 3,
                    '4 étoiles' => 4,
                    '5 étoiles' => 5,
                ],
                'label' => 'Note',
                'expanded' => false,
                'multiple' => false,
            ])
            ->add('reservation', EntityType::class, [
                'class' => \App\Entity\Reservation::class,
                'choice_label' => function (\App\Entity\Reservation $res) {
                    return sprintf("#%d - %s (%s)", $res->getId(), $res->getHebergement()?->getDescription() ?? 'Hébergement', $res->getDateDebut()?->format('d/m/Y') ?? '—');
                },
                'label' => 'Sélectionner la réservation concernée',
                'required' => false,
                'placeholder' => 'Choisissez une réservation',
                'query_builder' => function (\App\Repository\ReservationRepository $repo) use ($options) {
                    $qb = $repo->createQueryBuilder('r');
                    if ($options['user_email']) {
                        $qb->where('r.clientEmail = :email')
                           ->setParameter('email', $options['user_email']);
                    }
                    return $qb;
                }
            ])
            ->add('reservationActivite', EntityType::class, [
                'class' => \App\Entity\ReservationActivite::class,
                'choice_label' => function (\App\Entity\ReservationActivite $res) {
                    return sprintf("#%d - %s (%s)", $res->getId(), $res->getActivite()?->getNom() ?? 'Activité', $res->getDateReservation()?->format('d/m/Y') ?? '—');
                },
                'label' => 'Sélectionner l\'activité concernée',
                'required' => false,
                'placeholder' => 'Choisissez une activité',
                'query_builder' => function (\App\Repository\ReservationActiviteRepository $repo) use ($options) {
                    $qb = $repo->createQueryBuilder('ra');
                    if ($options['user_id']) {
                        $qb->where('ra.user = :userId')
                           ->setParameter('userId', $options['user_id']);
                    }
                    return $qb;
                }
            ])
            ->add('statut', HiddenType::class, [
                'data' => 'En attente',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Soumettre mon avis',
                'attr' => ['class' => 'btn btn-primary w-100 mt-3']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Avis::class,
            'user_id' => null,
            'user_email' => null,
        ]);
    }
}
