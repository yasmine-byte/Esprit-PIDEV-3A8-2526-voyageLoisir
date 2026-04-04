<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Form\BlogType;
use App\Repository\BlogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    #[Route(name: 'app_blog_index', methods: ['GET', 'POST'])]
    public function index(Request $request, BlogRepository $blogRepository, EntityManagerInterface $entityManager): Response
    {
        $blog = new Blog();
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked());
            $entityManager->persist($blog);
            $entityManager->flush();

            $this->addFlash(
                'success',
                $blog->getStatus() === 'publie'
                    ? 'Your blog has been published.'
                    : 'Your blog has been saved as a draft.'
            );

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/index.html.twig', [
            'blogs' => $blogRepository->findAll(),
            'blog_form' => $form->createView(),
            'show_blog_modal' => $form->isSubmitted() && !$form->isValid(),
        ]);
    }

    #[Route('/new', name: 'app_blog_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $blog = new Blog();
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked());
            $entityManager->persist($blog);
            $entityManager->flush();

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/new.html.twig', [
            'blog' => $blog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_blog_show', methods: ['GET'])]
    public function show(Blog $blog): Response
    {
        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_blog_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked(), false);
            $entityManager->flush();

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/edit.html.twig', [
            'blog' => $blog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_blog_delete', methods: ['POST'])]
    public function delete(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$blog->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($blog);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
    }

    private function applyBlogWorkflow(Blog $blog, bool $publish, bool $isNew = true): void
    {
        $now = new \DateTime();
        $slugger = new AsciiSlugger();

        if ($isNew || null === $blog->getDateCreation()) {
            $blog->setDateCreation($now);
        }

        if (!$blog->getSlug() && $blog->getTitre()) {
            $blog->setSlug(strtolower($slugger->slug($blog->getTitre())->toString()));
        }

        if ($publish) {
            $blog->setStatus('publie');
            $blog->setDatePublication($now);

            return;
        }

        $blog->setStatus('brouillon');

        if ($isNew) {
            $blog->setDatePublication(null);
        }
    }
}
