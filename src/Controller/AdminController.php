<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Repository\BlogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig'); // ← corrigé
    }

    #[Route('/admin/login', name: 'admin_login')]
    public function login(): Response
    {
        return $this->render('admin/login.html.twig');
    }

    #[Route('/admin/markets', name: 'admin_markets')]
    public function markets(): Response
    {
        return $this->render('admin/markets.html.twig');
    }

    #[Route('/admin/wallet', name: 'admin_wallet')]
    public function wallet(): Response
    {
        return $this->render('admin/wallet.html.twig');
    }

    #[Route('/admin/settings', name: 'admin_settings')]
    public function settings(): Response
    {
        return $this->render('admin/settings.html.twig');
    }

    #[Route('/admin/add-user', name: 'add_user')]
    public function addUser(): Response
    {
        return $this->render('admin/add-user.html.twig');
    }

    #[Route('/admin/blogs', name: 'admin_blogs')]
    public function blogs(Request $request, BlogRepository $blogRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'recent');

        $queryBuilder = $blogRepository->createQueryBuilder('b');

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('b.titre LIKE :search OR b.slug LIKE :search OR b.extrait LIKE :search OR b.contenu LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        switch ($sort) {
            case 'oldest':
                $queryBuilder->orderBy('b.dateCreation', 'ASC')->addOrderBy('b.id', 'ASC');
                break;
            case 'title':
                $queryBuilder->orderBy('b.titre', 'ASC')->addOrderBy('b.id', 'DESC');
                break;
            case 'status':
                $queryBuilder->orderBy('b.status', 'ASC')->addOrderBy('b.dateCreation', 'DESC');
                break;
            case 'published':
                $queryBuilder->orderBy('b.datePublication', 'DESC')->addOrderBy('b.id', 'DESC');
                break;
            default:
                $sort = 'recent';
                $queryBuilder->orderBy('b.dateCreation', 'DESC')->addOrderBy('b.id', 'DESC');
                break;
        }

        $blogs = $queryBuilder->getQuery()->getResult();
        $allBlogs = $blogRepository->findAll();

        $publishedCount = count(array_filter($allBlogs, static fn (Blog $blog) => 'publie' === $blog->getStatus()));
        $draftCount = count(array_filter($allBlogs, static fn (Blog $blog) => 'brouillon' === $blog->getStatus() || null === $blog->getStatus()));
        $publishedReadTimes = array_map(
            static fn (Blog $blog) => max(1, (int) ceil(strlen((string) $blog->getContenu()) / 700)),
            $allBlogs
        );
        $avgReadTime = [] !== $publishedReadTimes ? (int) ceil(array_sum($publishedReadTimes) / count($publishedReadTimes)) : 0;

        return $this->render('admin/blogs.html.twig', [
            'blogs' => $blogs,
            'search' => $search,
            'sort' => $sort,
            'stats' => [
                'total' => count($allBlogs),
                'published' => $publishedCount,
                'drafts' => $draftCount,
                'avg_read_time' => $avgReadTime,
            ],
        ]);
    }

    #[Route('/admin/blogs/{id}', name: 'admin_blog_show', methods: ['GET'])]
    public function showBlog(Blog $blog): Response
    {
        return $this->render('admin/blog_show.html.twig', [
            'blog' => $blog,
        ]);
    }

    #[Route('/admin/blogs/{id}/delete', name: 'admin_blog_delete', methods: ['POST'])]
    public function deleteBlog(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('admin_delete_blog_' . $blog->getId(), $request->request->get('_token'))) {
            $entityManager->remove($blog);
            $entityManager->flush();
            $this->addFlash('success', 'Blog deleted successfully.');
        }

        return $this->redirectToRoute('admin_blogs');
    }

    
}
