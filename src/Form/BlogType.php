<?php

namespace App\Form;

use App\Entity\Blog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
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
            ->add('blogCoverImage', FileType::class, [
                'label' => 'Image de couverture',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'accept' => 'image/*',
                ],
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'rows' => 10,
                    'placeholder' => 'Tell the full story here...',
                ],
            ])
            ->add('extrait', TextareaType::class, [
                'label' => 'Extrait',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Generate or refine a short summary for the blog card...',
                    'data-blog-excerpt-target' => 'output',
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
