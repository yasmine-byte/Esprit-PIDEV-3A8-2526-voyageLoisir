<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Repository\BlogRatingRepository;
use App\Repository\BlogRepository;
use App\Repository\BlogViewsRepository;
use App\Repository\CommentaireRepository;
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
    public function blogs(
        Request $request,
        BlogRepository $blogRepository,
        BlogViewsRepository $blogViewsRepository,
        CommentaireRepository $commentaireRepository,
        BlogRatingRepository $blogRatingRepository
    ): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        $statusFilter = (string) $request->query->get('status', 'all');

        $queryBuilder = $blogRepository->createQueryBuilder('b');

        if ('' !== $search) {
            $queryBuilder
                ->andWhere('b.titre LIKE :search OR b.slug LIKE :search OR b.extrait LIKE :search OR b.contenu LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ('draft' === $statusFilter) {
            $queryBuilder->andWhere('b.status = :draft OR b.status IS NULL')->setParameter('draft', false);
        } elseif ('published' === $statusFilter) {
            $queryBuilder->andWhere('b.status = :published')->setParameter('published', true);
        } elseif ('pending' === $statusFilter) {
            $queryBuilder->andWhere('b.publicationRequested = :pending')->setParameter('pending', true);
        } else {
            $statusFilter = 'all';
        }

        switch ($sort) {
            case 'oldest':
                $queryBuilder->orderBy('b.dateCreation', 'ASC')->addOrderBy('b.id', 'ASC');
                break;
            case 'title':
                $queryBuilder->orderBy('b.titre', 'ASC')->addOrderBy('b.id', 'DESC');
                break;
            case 'status':
                $queryBuilder->orderBy('b.status', 'ASC')->addOrderBy('b.publicationRequested', 'DESC')->addOrderBy('b.dateCreation', 'DESC');
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
        $allViews = $blogViewsRepository->findAll();
        $allComments = $commentaireRepository->findAll();
        $allRatings = $blogRatingRepository->findAll();

        $publishedCount = count(array_filter($allBlogs, static fn (Blog $blog) => true === $blog->getStatus()));
        $draftCount = count(array_filter($allBlogs, static fn (Blog $blog) => true !== $blog->getStatus()));
        $pendingCount = count(array_filter($allBlogs, static fn (Blog $blog) => true === $blog->isPublicationRequested()));
        $publishedReadTimes = array_map(
            static fn (Blog $blog) => max(1, (int) ceil(strlen((string) $blog->getContenu()) / 700)),
            $allBlogs
        );
        $avgReadTime = [] !== $publishedReadTimes ? (int) ceil(array_sum($publishedReadTimes) / count($publishedReadTimes)) : 0;
        $highQualityCount = count(array_filter($allBlogs, fn (Blog $blog) => $this->calculateCompleteness($blog) >= 80));

        $viewCounts = [];
        foreach ($allViews as $view) {
            $blogId = $view->getBlog()?->getId();
            if (null === $blogId) {
                continue;
            }

            $viewCounts[$blogId] = ($viewCounts[$blogId] ?? 0) + 1;
        }

        $commentCounts = [];
        $commentsByBlog = [];
        foreach ($allComments as $comment) {
            $blogId = $comment->getBlog()?->getId();
            if (null === $blogId) {
                continue;
            }

            $commentCounts[$blogId] = ($commentCounts[$blogId] ?? 0) + 1;
            $commentsByBlog[$blogId][] = $comment;
        }

        $ratingCounts = [];
        foreach ($allRatings as $rating) {
            $blogId = $rating->getBlog()?->getId();
            if (null === $blogId) {
                continue;
            }

            $ratingCounts[$blogId] = ($ratingCounts[$blogId] ?? 0) + 1;
        }

        $blogMetrics = [];
        foreach ($blogs as $blog) {
            $blogId = $blog->getId();
            $completeness = $this->calculateCompleteness($blog);

            $blogMetrics[$blogId] = [
                'views' => $viewCounts[$blogId] ?? 0,
                'comments' => $commentCounts[$blogId] ?? 0,
                'ratings' => $ratingCounts[$blogId] ?? 0,
                'read_time' => max(1, (int) ceil(strlen((string) $blog->getContenu()) / 700)),
                'completeness' => $completeness,
                'quality_label' => $completeness >= 80 ? 'Ready' : ($completeness >= 55 ? 'Needs review' : 'Incomplete'),
            ];
        }

        return $this->render('admin/blogs.html.twig', [
            'blogs' => $blogs,
            'search' => $search,
            'sort' => $sort,
            'status_filter' => $statusFilter,
            'blog_metrics' => $blogMetrics,
            'comments_by_blog' => $commentsByBlog,
            'stats' => [
                'total' => count($allBlogs),
                'published' => $publishedCount,
                'drafts' => $draftCount,
                'pending' => $pendingCount,
                'avg_read_time' => $avgReadTime,
                'ready' => $highQualityCount,
                'comments_total' => count($allComments),
            ],
        ]);
    }

    #[Route('/admin/blogs/{id}', name: 'admin_blog_show', methods: ['GET'])]
    public function showBlog(
        Blog $blog,
        CommentaireRepository $commentaireRepository,
        BlogViewsRepository $blogViewsRepository
    ): Response
    {
        $comments = $commentaireRepository->findBy(
            ['blog' => $blog],
            ['dateCreation' => 'DESC', 'id' => 'DESC']
        );

        return $this->render('admin/blog_show.html.twig', [
            'blog' => $blog,
            'comments' => $comments,
            'views_count' => $blogViewsRepository->count(['blog' => $blog]),
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

    #[Route('/admin/blogs/{id}/workflow', name: 'admin_blog_workflow', methods: ['POST'])]
    public function workflowBlog(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_workflow_blog_' . $blog->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_blogs');
        }

        $action = (string) $request->request->get('action');

        if ('publish' === $action) {
            $blog->setStatus(true);
            $blog->setPublicationRequested(false);
            $blog->setDatePublication(new \DateTime());
            $this->addFlash('success', 'Blog published successfully.');
        } elseif ('draft' === $action) {
            $blog->setStatus(false);
            $blog->setPublicationRequested(false);
            $blog->setDatePublication(null);
            $this->addFlash('success', 'Blog moved back to draft.');
        } elseif ('request' === $action) {
            $blog->setStatus(false);
            $blog->setPublicationRequested(true);
            $blog->setDatePublication(null);
            $this->addFlash('success', 'Publication request marked as pending.');
        }

        $entityManager->flush();

        $redirectRoute = $request->request->get('redirect_route', 'admin_blogs');
        if ('admin_blog_show' === $redirectRoute) {
            return $this->redirectToRoute('admin_blog_show', ['id' => $blog->getId()]);
        }

        return $this->redirectToRoute('admin_blogs', array_filter([
            'q' => $request->request->get('q') ?: null,
            'sort' => $request->request->get('sort') ?: null,
            'status' => $request->request->get('status') ?: null,
        ]));
    }

    private function calculateCompleteness(Blog $blog): int
    {
        $score = 0;

        if ($blog->getTitre()) {
            $score += 20;
        }
        if ($blog->getSlug()) {
            $score += 15;
        }
        if ($blog->getImageCouverture()) {
            $score += 15;
        }
        if ($blog->getExtrait() && strlen(trim($blog->getExtrait())) >= 40) {
            $score += 20;
        }
        if ($blog->getContenu() && strlen(trim($blog->getContenu())) >= 300) {
            $score += 30;
        }

        return $score;
    }

    
}
