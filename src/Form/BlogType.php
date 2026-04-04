<?php

namespace App\Form;

use App\Entity\Blog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Give your story a clear title',
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'attr' => [
                    'placeholder' => 'my-hidden-wonder',
                ],
            ])
            ->add('imageCouverture', TextType::class, [
                'label' => 'Image de couverture',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com/cover.jpg',
                ],
            ])
            ->add('extrait', TextareaType::class, [
                'label' => 'Extrait',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Write a short summary of the article',
                ],
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'rows' => 10,
                    'placeholder' => 'Tell the full story here...',
                ],
            ])
            ->add('saveDraft', SubmitType::class, [
                'label' => 'Save as draft',
            ])
            ->add('publish', SubmitType::class, [
                'label' => 'Publish blog',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Blog::class,
        ]);
    }
}
