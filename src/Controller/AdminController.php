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
        BlogViewsRepository $blogViewsRepository,
        CommentaireRepository $commentaireRepository,
        BlogRatingRepository $blogRatingRepository
    ): Response {
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
            if (null === $blogId) continue;
            $viewCounts[$blogId] = ($viewCounts[$blogId] ?? 0) + 1;
        }

        $commentCounts = [];
        $commentsByBlog = [];
        foreach ($allComments as $comment) {
            $blogId = $comment->getBlog()?->getId();
            if (null === $blogId) continue;
            $commentCounts[$blogId] = ($commentCounts[$blogId] ?? 0) + 1;
            $commentsByBlog[$blogId][] = $comment;
        }

        $ratingCounts = [];
        foreach ($allRatings as $rating) {
            $blogId = $rating->getBlog()?->getId();
            if (null === $blogId) continue;
            $ratingCounts[$blogId] = ($ratingCounts[$blogId] ?? 0) + 1;
        }

        $blogMetrics = [];
        foreach ($blogs as $blog) {
            $blogId = $blog->getId();
            $completeness = $this->calculateCompleteness($blog);
            $blogMetrics[$blogId] = [
                'views'         => $viewCounts[$blogId] ?? 0,
                'comments'      => $commentCounts[$blogId] ?? 0,
                'ratings'       => $ratingCounts[$blogId] ?? 0,
                'read_time'     => max(1, (int) ceil(strlen((string) $blog->getContenu()) / 700)),
                'completeness'  => $completeness,
                'quality_label' => $completeness >= 80 ? 'Ready' : ($completeness >= 55 ? 'Needs review' : 'Incomplete'),
            ];
        }

        return $this->render('admin/blogs.html.twig', [
            'blogs'            => $blogs,
            'search'           => $search,
            'sort'             => $sort,
            'status_filter'    => $statusFilter,
            'blog_metrics'     => $blogMetrics,
            'comments_by_blog' => $commentsByBlog,
            'stats'            => [
                'total'          => count($allBlogs),
                'published'      => $publishedCount,
                'drafts'         => $draftCount,
                'pending'        => $pendingCount,
                'avg_read_time'  => $avgReadTime,
                'ready'          => $highQualityCount,
                'comments_total' => count($allComments),
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
}
