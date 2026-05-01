<?php

namespace App\Controller\Admin;

use App\Entity\Blog;
use App\Repository\BlogRepository;
use App\Repository\BlogViewsRepository;
use App\Repository\CommentaireRepository;
use App\Service\BlogRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/blogs')]
#[IsGranted('ROLE_ADMIN')]
final class BlogController extends AbstractController
{
    #[Route('', name: 'admin_blogs', methods: ['GET'])]
    public function index(
        Request $request,
        BlogRepository $blogRepository,
        BlogViewsRepository $blogViewsRepository,
        BlogRecommendationService $blogRecommendationService
    ): Response {
        $search           = trim((string) $request->query->get('q', ''));
        $selectedStatuses = $request->query->all('statuses') ?: ['all'];
        $selectedCategories = $request->query->all('categories') ?: [];
        $periodFilter     = (string) $request->query->get('period', 'all');
        $sortFilter       = (string) $request->query->get('sort', 'recent');

        $qb = $blogRepository->createQueryBuilder('b');

        if ('' !== $search) {
            $qb->andWhere('b.titre LIKE :q OR b.extrait LIKE :q OR b.contenu LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if (!in_array('all', $selectedStatuses, true) && [] !== $selectedStatuses) {
            $orX = $qb->expr()->orX();
            foreach ($selectedStatuses as $s) {
                if ('published' === $s) {
                    $orX->add('b.status = true AND b.publicationRequested = false');
                } elseif ('draft' === $s) {
                    $orX->add('b.status = false AND b.publicationRequested = false');
                } elseif ('pending' === $s) {
                    $orX->add('b.publicationRequested = true');
                }
            }
            $qb->andWhere($orX);
        }

        $now = new \DateTimeImmutable();
        if ('today' === $periodFilter) {
            $qb->andWhere('b.dateCreation >= :from')->setParameter('from', $now->modify('today'));
        } elseif ('week' === $periodFilter) {
            $qb->andWhere('b.dateCreation >= :from')->setParameter('from', $now->modify('monday this week'));
        } elseif ('month' === $periodFilter) {
            $qb->andWhere('b.dateCreation >= :from')->setParameter('from', $now->modify('first day of this month'));
        }

        match ($sortFilter) {
            'oldest'      => $qb->orderBy('b.dateCreation', 'ASC'),
            'most_viewed' => $qb->orderBy('b.datePublication', 'DESC'),
            'alpha'       => $qb->orderBy('b.titre', 'ASC'),
            default       => $qb->orderBy('b.dateCreation', 'DESC'),
        };

        /** @var Blog[] $blogs */
        $blogs = $qb->getQuery()->getResult();

        $allBlogs    = $blogRepository->findAll();
        $blogMetrics = $blogRecommendationService->buildMetrics($allBlogs);

        if ('most_viewed' === $sortFilter) {
            usort($blogs, fn (Blog $a, Blog $b) =>
                ($blogMetrics[$b->getId()]['views'] ?? 0) <=> ($blogMetrics[$a->getId()]['views'] ?? 0)
            );
        }

        if ([] !== $selectedCategories) {
            $blogs = array_values(array_filter($blogs, function (Blog $blog) use ($blogMetrics, $selectedCategories): bool {
                $cat = $blogMetrics[$blog->getId()]['category'] ?? '';
                return in_array($cat, $selectedCategories, true);
            }));
        }

        $availableCategories = array_values(array_unique(array_filter(
            array_map(fn (array $m): string => (string) ($m['category'] ?? ''), $blogMetrics),
            fn (string $c): bool => '' !== $c
        )));

        $allCount       = count($allBlogs);
        $publishedCount = count(array_filter($allBlogs, fn (Blog $b): bool => (bool) $b->getStatus()));
        $draftCount     = count(array_filter($allBlogs, fn (Blog $b): bool => !$b->getStatus() && !$b->isPublicationRequested()));

        return $this->render('admin/blog/index.html.twig', [
            'blogs'                => $blogs,
            'blog_metrics'         => $blogMetrics,
            'search'               => $search,
            'selected_statuses'    => $selectedStatuses,
            'selected_categories'  => $selectedCategories,
            'period_filter'        => $periodFilter,
            'sort'                 => $sortFilter,
            'available_categories' => $availableCategories,
            'stats' => [
                'total'     => $allCount,
                'published' => $publishedCount,
                'drafts'    => $draftCount,
            ],
        ]);
    }

    #[Route('/{id}', name: 'admin_blog_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Blog $blog, CommentaireRepository $commentaireRepository, BlogViewsRepository $blogViewsRepository): Response
    {
        $comments = $commentaireRepository->findBy(
            ['blog' => $blog],
            ['dateCreation' => 'DESC']
        );

        // Calcul des réactions par commentaire
        $commentReactionCounts = [];
        foreach ($comments as $comment) {
            $commentReactionCounts[$comment->getId()] = $comment->getLikesCount() ?? 0;
        }

        // IDs des commentaires avec signalements en attente
        $pendingReportCommentIds = [];

        // Image de couverture
        $coverImage = $blog->getImageCouverture() ?? '';

        // Label et classe de statut
        $statusLabel = $blog->getStatus() ? 'Publié' : 'Brouillon';
        $statusClass = $blog->getStatus() ? 'status-badge--publie' : 'status-badge--brouillon';
        if ($blog->isPublicationRequested() && !$blog->getStatus()) {
            $statusLabel = 'En attente';
            $statusClass = 'status-badge--attente';
        }

        // Total réactions
        $totalReactions = array_sum($commentReactionCounts);

        // Nombre de vues
        $viewsCount = count($blogViewsRepository->findBy(['blog' => $blog]));

        return $this->render('admin/blog/show.html.twig', [
            'blog'                       => $blog,
            'comments'                   => $comments,
            'comment_reaction_counts'    => $commentReactionCounts,
            'pending_report_comment_ids' => $pendingReportCommentIds,
            'coverImage'                 => $coverImage,
            'statusLabel'                => $statusLabel,
            'statusClass'                => $statusClass,
            'totalReactions'             => $totalReactions,
            'views_count'                => $viewsCount,
        ]);
    }

    #[Route('/{id}/workflow', name: 'admin_blog_workflow', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function workflow(
        Request $request,
        Blog $blog,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_workflow_blog_' . $blog->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('admin_blogs');
        }

        $action = $request->request->get('action');

        if ('publish' === $action) {
            $blog->setStatus(true);
            $blog->setPublicationRequested(false);
            if (null === $blog->getDatePublication()) {
$blog->setDatePublication(new \DateTimeImmutable());            }
            $this->addFlash('success', 'Blog published successfully.');
        } elseif ('draft' === $action) {
            $blog->setStatus(false);
            $blog->setPublicationRequested(false);
            $this->addFlash('success', 'Blog moved back to draft.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('admin_blogs', array_filter([
            'q'          => $request->request->get('q'),
            'sort'       => $request->request->get('sort'),
            'period'     => $request->request->get('period'),
            'statuses'   => $request->request->all('statuses[]'),
            'categories' => $request->request->all('categories[]'),
        ]));
    }

    #[Route('/{id}/delete', name: 'admin_blog_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        Blog $blog,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_delete_blog_' . $blog->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('admin_blogs');
        }

        $entityManager->remove($blog);
        $entityManager->flush();
        $this->addFlash('success', 'Blog deleted successfully.');

        return $this->redirectToRoute('admin_blogs');
    }
}