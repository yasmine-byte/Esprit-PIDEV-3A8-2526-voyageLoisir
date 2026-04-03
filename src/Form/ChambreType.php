<?php
namespace App\Form;

use App\Entity\Chambre;
use App\Entity\Hebergement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
            ])
            ->add('numero')
            ->add('typeChambre', ChoiceType::class, [
                'choices' => [
                    'Simple' => 'simple',
                    'Double' => 'double',
                    'Suite' => 'suite',
                    'Familiale' => 'familiale',
                ],
                'placeholder' => '-- Type de chambre --',
                'label' => 'Type de chambre',
            ])
            ->add('prixNuit', null, ['label' => 'Prix / nuit (TND)'])
            ->add('capacite', null, ['label' => 'Capacité (personnes)'])
            ->add('equipements', null, ['label' => 'Équipements'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Chambre::class,
        ]);
    }
}