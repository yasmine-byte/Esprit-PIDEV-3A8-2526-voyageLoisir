<?php
namespace App\Controller;

use App\Entity\Blog;
use App\Repository\BlogRatingRepository;
use App\Repository\BlogRepository;
use App\Repository\BlogViewsRepository;
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
        $sort = (string) $request->query->get('sort', 'recent');
        $statusFilter = (string) $request->query->get('status', 'all');
        if (!in_array($statusFilter, ['all', 'draft', 'published', 'pending'], true)) {
            $statusFilter = 'all';
        }
        if (!in_array($sort, ['recent', 'oldest', 'title', 'status', 'published'], true)) {
            $sort = 'recent';
        }

        $blogs = $blogRepository->createAdminListingQueryBuilder($search, $sort, $statusFilter)
            ->getQuery()
            ->getResult();
        $allBlogs = $blogRepository->fetchAdminSummaryData();
        $blogMetrics = $blogRecommendationService->buildMetrics($blogs);

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
            'sort'             => $sort,
            'status_filter'    => $statusFilter,
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
        BlogViewsRepository $blogViewsRepository
    ): Response {
        $comments = $commentaireRepository->findBy(
            ['blog' => $blog],
            ['dateCreation' => 'DESC', 'id' => 'DESC']
        );

        return $this->render('admin/blog_show.html.twig', [
            'blog'        => $blog,
            'comments'    => $comments,
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
            'q'      => $request->request->get('q') ?: null,
            'sort'   => $request->request->get('sort') ?: null,
            'status' => $request->request->get('status') ?: null,
        ]));
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
