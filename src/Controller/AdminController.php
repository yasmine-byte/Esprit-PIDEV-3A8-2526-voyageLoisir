<?php
namespace App\Controller;

use App\Entity\Blog;
use App\Entity\CommentReport;
use App\Entity\Commentaire;
use App\Repository\BlogRatingRepository;
use App\Repository\BlogRepository;
use App\Repository\BlogViewsRepository;
use App\Repository\CommentReactionRepository;
use App\Repository\CommentReportRepository;
use App\Repository\CommentaireRepository;
use App\Repository\ActiviteRepository;
use App\Repository\HebergementRepository;
use App\Repository\ChambreRepository;
use App\Repository\ReservationRepository;
use App\Repository\RoleRepository;
use App\Repository\UsersRepository;
use App\Service\BlogRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    #[Route('/admin/index.html', name: 'app_admin_fallback')]
    public function index(
        UsersRepository $usersRepository,
        RoleRepository $roleRepository
    ): Response {
        $latestUsers = $usersRepository->findLatest(5);
        $roleStats = [];

        foreach ($roleRepository->findAll() as $role) {
            $roleStats[$role->getName() ?? 'Sans role'] = $role->getNo()->count();
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $usersRepository->count([]),
            'activeUsers' => $usersRepository->countActive(),
            'inactiveUsers' => $usersRepository->countInactive(),
            'totalRoles' => $roleRepository->count([]),
            'latestUsers' => $latestUsers,
            'monthlyData' => $usersRepository->countByMonth(),
            'roleStats' => array_filter($roleStats, static fn (int $count): bool => $count > 0),
        ]);
    }

    #[Route('/admin/reservations/pdf', name: 'admin_reservations_pdf')]
    public function exportPdf(ReservationRepository $reservationRepo): Response
    {
        $reservations = $reservationRepo->findBy([], ['id' => 'DESC']);

        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
                h1 { color: #b87333; text-align: center; margin-bottom: 5px; }
                .subtitle { text-align: center; color: #888; margin-bottom: 20px; font-size: 11px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th { background-color: #b87333; color: white; padding: 8px; text-align: left; font-size: 11px; }
                td { padding: 7px 8px; border-bottom: 1px solid #eee; font-size: 11px; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .badge-attente { background:#f59e0b; color:#fff; padding:2px 8px; border-radius:10px; font-size:10px; }
                .badge-confirmee { background:#22c55e; color:#fff; padding:2px 8px; border-radius:10px; font-size:10px; }
                .badge-annulee { background:#ef4444; color:#fff; padding:2px 8px; border-radius:10px; font-size:10px; }
                .footer { text-align:center; margin-top:20px; color:#aaa; font-size:10px; border-top:1px solid #eee; padding-top:10px; }
                .total-row { font-weight:bold; background:#f0f0f0 !important; }
            </style>
        </head>
        <body>
            <h1>Vianova - Rapport des Reservations</h1>
            <p class="subtitle">Genere le ' . date('d/m/Y a H:i') . ' | Total : ' . count($reservations) . ' reservation(s)</p>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Telephone</th>
                        <th>Hebergement</th>
                        <th>Arrivee</th>
                        <th>Depart</th>
                        <th>Nuits</th>
                        <th>Total</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>';

        $totalGeneral = 0;
        foreach ($reservations as $reservation) {
            $statut = $reservation->getStatut();
            $badgeClass = match($statut) {
                'confirmee' => 'badge-confirmee',
                'annulee'   => 'badge-annulee',
                default     => 'badge-attente'
            };
            $statutLabel = match($statut) {
                'confirmee' => 'Confirmee',
                'annulee'   => 'Annulee',
                default     => 'En attente'
            };
            $totalGeneral += (float)$reservation->getTotal();

            $html .= '<tr>
                <td>' . $reservation->getId() . '</td>
                <td>' . htmlspecialchars($reservation->getClientNom() ?? '') . '</td>
                <td>' . htmlspecialchars($reservation->getClientEmail() ?? '') . '</td>
                <td>' . htmlspecialchars($reservation->getClientTel() ?? '') . '</td>
                <td>' . htmlspecialchars($reservation->getHebergement()?->getDescription() ?? 'N/A') . '</td>
                <td>' . ($reservation->getDateDebut() ? $reservation->getDateDebut()->format('d/m/Y') : '-') . '</td>
                <td>' . ($reservation->getDateFin() ? $reservation->getDateFin()->format('d/m/Y') : '-') . '</td>
                <td>' . $reservation->getNbNuits() . '</td>
                <td>' . number_format((float)$reservation->getTotal(), 2) . ' TND</td>
                <td><span class="' . $badgeClass . '">' . $statutLabel . '</span></td>
            </tr>';
        }

        $html .= '<tr class="total-row">
                <td colspan="8" style="text-align:right; padding-right:10px;">Total General :</td>
                <td>' . number_format($totalGeneral, 2) . ' TND</td>
                <td></td>
            </tr>';

        $html .= '</tbody></table>
            <p class="footer">Vianova &copy; ' . date('Y') . ' - Document genere automatiquement</p>
        </body></html>';

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'reservations_' . date('Y-m-d') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    #[Route('/admin/activite', name: 'admin_activite_index')]
    public function activite(ActiviteRepository $activiteRepository): Response
    {
        $activites = $activiteRepository->findAll();
        $totalActivites = count($activites);

        $totalPrix = 0;
        foreach ($activites as $activite) {
            $totalPrix += (float) ($activite->getPrix() ?? 0);
        }

        $prixMoyen = $totalActivites > 0 ? $totalPrix / $totalActivites : 0;

        return $this->render('admin/activite/index.html.twig', [
            'activites'      => $activites,
            'totalActivites' => $totalActivites,
            'prixMoyen'      => $prixMoyen,
        ]);
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

    // ========================
    // BLOG (branche blog)
    // ========================

    #[Route('/admin/blogs', name: 'admin_blogs')]
    public function blogs(
        Request $request,
        BlogRepository $blogRepository,
        CommentaireRepository $commentaireRepository,
        BlogRecommendationService $blogRecommendationService
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $sortFilter = (string) $request->query->get('sort', 'recent');
        if (!in_array($sortFilter, ['recent', 'oldest', 'most_viewed', 'alpha'], true)) {
            $sortFilter = 'recent';
        }

        $selectedStatuses = $this->normalizeArrayQuery($request->query->all('statuses'), ['all', 'published', 'draft', 'pending']);
        if ([] === $selectedStatuses) {
            $selectedStatuses = ['all'];
        }
        if (in_array('all', $selectedStatuses, true) && count($selectedStatuses) > 1) {
            $selectedStatuses = array_values(array_filter($selectedStatuses, static fn (string $status): bool => 'all' !== $status));
        }

        $selectedCategories = $this->normalizeArrayQuery($request->query->all('categories'));
        $periodFilter = (string) $request->query->get('period', 'all');
        if (!in_array($periodFilter, ['today', 'week', 'month', 'all'], true)) {
            $periodFilter = 'all';
        }

        $sortMap = [
            'recent' => 'recent',
            'oldest' => 'oldest',
            'most_viewed' => 'recent',
            'alpha' => 'title',
        ];

        $blogs = $blogRepository->createAdminListingQueryBuilder($search, $sortMap[$sortFilter], 'all')
            ->getQuery()
            ->getResult();
        $allBlogs = $blogRepository->fetchAdminSummaryData();
        $blogMetrics = $blogRecommendationService->buildMetrics($blogs);

        $availableCategoriesMap = [];
        foreach ($blogs as $blog) {
            $blogId = $blog->getId();
            $category = trim((string) ($blogMetrics[$blogId]['category'] ?? ''));
            if ('' !== $category) {
                $availableCategoriesMap[$category] = true;
            }
        }
        $availableCategories = array_keys($availableCategoriesMap);
        sort($availableCategories);

        if ([] !== $selectedCategories) {
            $selectedCategories = array_values(array_filter(
                $selectedCategories,
                static fn (string $category): bool => '' !== trim($category)
            ));
        }

        $blogs = array_values(array_filter($blogs, function (Blog $blog) use ($selectedStatuses, $selectedCategories, $periodFilter, $blogMetrics): bool {
            $blogId = $blog->getId();
            $isPublished = true === $blog->isStatus();
            $isPending = true === $blog->isPublicationRequested() && !$isPublished;

            if (!in_array('all', $selectedStatuses, true)) {
                $statusMatches = false;
                foreach ($selectedStatuses as $status) {
                    if ('published' === $status && $isPublished) {
                        $statusMatches = true;
                    } elseif ('draft' === $status && !$isPublished && !$isPending) {
                        $statusMatches = true;
                    } elseif ('pending' === $status && $isPending) {
                        $statusMatches = true;
                    }
                }
                if (!$statusMatches) {
                    return false;
                }
            }

            if ([] !== $selectedCategories) {
                $category = trim((string) ($blogMetrics[$blogId]['category'] ?? ''));
                if (!in_array($category, $selectedCategories, true)) {
                    return false;
                }
            }

            if ('all' !== $periodFilter) {
                $referenceDate = $blog->getDatePublication() ?? $blog->getDateCreation();
                if (!$referenceDate instanceof \DateTimeInterface) {
                    return false;
                }

                $now = new \DateTimeImmutable();
                $reference = \DateTimeImmutable::createFromMutable(
                    $referenceDate instanceof \DateTime ? $referenceDate : \DateTime::createFromInterface($referenceDate)
                );

                if ('today' === $periodFilter && $reference->format('Y-m-d') !== $now->format('Y-m-d')) {
                    return false;
                }
                if ('week' === $periodFilter && $reference < $now->modify('-7 days')) {
                    return false;
                }
                if ('month' === $periodFilter && $reference < $now->modify('-30 days')) {
                    return false;
                }
            }

            return true;
        }));

        usort($blogs, function (Blog $a, Blog $b) use ($sortFilter, $blogMetrics): int {
            if ('oldest' === $sortFilter) {
                return ($a->getDateCreation()?->getTimestamp() ?? 0) <=> ($b->getDateCreation()?->getTimestamp() ?? 0);
            }
            if ('alpha' === $sortFilter) {
                return strcmp(mb_strtolower((string) $a->getTitre()), mb_strtolower((string) $b->getTitre()));
            }
            if ('most_viewed' === $sortFilter) {
                $aViews = (int) ($blogMetrics[$a->getId()]['views'] ?? 0);
                $bViews = (int) ($blogMetrics[$b->getId()]['views'] ?? 0);
                return $bViews <=> $aViews;
            }
            return ($b->getDateCreation()?->getTimestamp() ?? 0) <=> ($a->getDateCreation()?->getTimestamp() ?? 0);
        });

        $publishedCount = 0;
        $draftCount = 0;
        $pendingCount = 0;
        $publishedReadTimes = [];
        $highQualityCount = 0;

        foreach ($allBlogs as $blogData) {
            $isPublished = true === ($blogData['status'] ?? null);
            $isPending = true === ($blogData['publicationRequested'] ?? null);

            if ($isPublished) {
                $publishedCount++;
            } else {
                $draftCount++;
            }

            if ($isPending) {
                $pendingCount++;
            }

            $publishedReadTimes[] = max(1, (int) ceil(mb_strlen((string) ($blogData['contenu'] ?? '')) / 700));

            if ($this->calculateCompletenessFromData($blogData) >= 80) {
                $highQualityCount++;
            }
        }

        $avgReadTime = [] !== $publishedReadTimes ? (int) ceil(array_sum($publishedReadTimes) / count($publishedReadTimes)) : 0;

        foreach ($blogs as $blog) {
            $blogId = $blog->getId();
            $completeness = $this->calculateCompleteness($blog);
            $blogMetrics[$blogId]['completeness'] = $completeness;
            $blogMetrics[$blogId]['quality_label'] = $completeness >= 80 ? 'Ready' : ($completeness >= 55 ? 'Needs review' : 'Incomplete');
            $blogMetrics[$blogId]['ratings'] = $blogMetrics[$blogId]['rating_count'] ?? 0;
        }

        return $this->render('admin/blogs.html.twig', [
            'blogs'            => $blogs,
            'search'           => $search,
            'sort'             => $sortFilter,
            'status_filter'    => 'all',
            'selected_statuses' => $selectedStatuses,
            'selected_categories' => $selectedCategories,
            'available_categories' => $availableCategories,
            'period_filter' => $periodFilter,
            'blog_metrics'     => $blogMetrics,
            'stats'            => [
                'total'          => count($allBlogs),
                'published'      => $publishedCount,
                'drafts'         => $draftCount,
                'pending'        => $pendingCount,
                'avg_read_time'  => $avgReadTime,
                'ready'          => $highQualityCount,
                'comments_total' => $commentaireRepository->count([]),
            ],
        ]);
    }

    #[Route('/admin/blogs/{id}', name: 'admin_blog_show', methods: ['GET'])]
    public function showBlog(
        Blog $blog,
        CommentaireRepository $commentaireRepository,
        CommentReactionRepository $commentReactionRepository,
        CommentReportRepository $commentReportRepository,
        BlogViewsRepository $blogViewsRepository
    ): Response {
        $comments = method_exists($blog, 'getCommentaires')
            ? $blog->getCommentaires()->toArray()
            : $commentaireRepository->findBy(
                ['blog' => $blog],
                ['dateCreation' => 'DESC', 'id' => 'DESC']
            );

        usort($comments, static function (Commentaire $a, Commentaire $b): int {
            $aDate = $a->getDateCreation()?->getTimestamp() ?? 0;
            $bDate = $b->getDateCreation()?->getTimestamp() ?? 0;
            if ($aDate === $bDate) {
                return ($b->getId() ?? 0) <=> ($a->getId() ?? 0);
            }
            return $bDate <=> $aDate;
        });

        $commentIds = array_values(array_filter(array_map(
            static fn (Commentaire $comment): int => (int) ($comment->getId() ?? 0),
            $comments
        )));

        $pendingCommentIds = [];
        $reactionCountsByComment = [];
        if ([] !== $commentIds) {
            $rows = $commentReportRepository->createQueryBuilder('cr')
                ->select('IDENTITY(cr.commentaire) AS comment_id')
                ->andWhere('cr.commentaire IN (:commentIds)')
                ->andWhere('cr.status = :pending')
                ->setParameter('commentIds', $commentIds)
                ->setParameter('pending', CommentReport::STATUS_PENDING)
                ->groupBy('cr.commentaire')
                ->getQuery()
                ->getArrayResult();

            $pendingCommentIds = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['comment_id'] ?? 0),
                $rows
            )));

            foreach ($comments as $comment) {
                $commentId = (int) ($comment->getId() ?? 0);
                if ($commentId <= 0) {
                    continue;
                }
                $reactionCountsByComment[$commentId] = $commentReactionRepository->aggregateCountsForComment($comment);
            }
        }

        return $this->render('admin/blog_show.html.twig', [
            'blog'        => $blog,
            'comments'    => $comments,
            'pending_report_comment_ids' => $pendingCommentIds,
            'comment_reaction_counts' => $reactionCountsByComment,
            'views_count' => $blogViewsRepository->count(['blog' => $blog]),
            'comment_hide_token_prefix' => 'admin_hide_comment_',
            'comment_delete_token_prefix' => 'admin_delete_comment_',
        ]);
    }

    #[Route('/admin/blogs/{blog}/comments/{comment}/delete', name: 'admin_blog_comment_delete', methods: ['POST'])]
    public function deleteBlogComment(Request $request, Blog $blog, Commentaire $comment, EntityManagerInterface $entityManager): Response
    {
        if ($comment->getBlog()?->getId() !== $blog->getId()) {
            return $this->redirectToRoute('admin_blog_show', ['id' => $blog->getId()]);
        }

        if ($this->isCsrfTokenValid('admin_delete_comment_' . $comment->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($comment);
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('admin_blog_show', ['id' => $blog->getId()]);
    }

    #[Route('/admin/blogs/{blog}/comments/{comment}/hide', name: 'admin_blog_comment_hide', methods: ['POST'])]
    public function hideBlogComment(Request $request, Blog $blog, Commentaire $comment, EntityManagerInterface $entityManager): Response
    {
        if ($comment->getBlog()?->getId() !== $blog->getId()) {
            return $this->redirectToRoute('admin_blog_show', ['id' => $blog->getId()]);
        }

        if ($this->isCsrfTokenValid('admin_hide_comment_' . $comment->getId(), (string) $request->request->get('_token'))) {
            $comment->setContenu('[Commentaire masqué par l’administration]');
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire masqué.');
        }

        return $this->redirectToRoute('admin_blog_show', ['id' => $blog->getId()]);
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

        $redirectParams = array_filter([
            'q'      => $request->request->get('q') ?: null,
            'sort'   => $request->request->get('sort') ?: null,
            'period' => $request->request->get('period') ?: null,
        ]);

        $statuses = $request->request->all('statuses');
        if ([] !== $statuses) {
            $redirectParams['statuses'] = $statuses;
        }
        $categories = $request->request->all('categories');
        if ([] !== $categories) {
            $redirectParams['categories'] = $categories;
        }

        return $this->redirectToRoute('admin_blogs', $redirectParams);
    }

    private function normalizeArrayQuery(array $values, array $allowed = []): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $values
        ), static fn (string $value): bool => '' !== $value)));

        if ([] === $allowed) {
            return $normalized;
        }

        return array_values(array_filter(
            $normalized,
            static fn (string $value): bool => in_array($value, $allowed, true)
        ));
    }

    private function calculateCompleteness(Blog $blog): int
    {
        $score = 0;
        if ($blog->getTitre()) $score += 20;
        if ($blog->getSlug()) $score += 15;
        if ($blog->getImageCouverture()) $score += 15;
        if ($blog->getExtrait() && strlen(trim($blog->getExtrait())) >= 40) $score += 20;
        if ($blog->getContenu() && strlen(trim($blog->getContenu())) >= 300) $score += 30;
        return $score;
    }

    private function calculateCompletenessFromData(array $blogData): int
    {
        $score = 0;
        if (!empty($blogData['titre'])) $score += 20;
        if (!empty($blogData['slug'])) $score += 15;
        if (!empty($blogData['imageCouverture'])) $score += 15;
        if (!empty($blogData['extrait']) && strlen(trim((string) $blogData['extrait'])) >= 40) $score += 20;
        if (!empty($blogData['contenu']) && strlen(trim((string) $blogData['contenu'])) >= 300) $score += 30;

        return $score;
    }
}
