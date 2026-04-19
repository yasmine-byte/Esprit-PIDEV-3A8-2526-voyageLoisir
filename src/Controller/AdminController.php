<?php
namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\HebergementRepository;
use App\Repository\ChambreRepository;
use App\Repository\ReservationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    #[Route('/admin/index.html', name: 'app_admin_fallback')]
    public function index(
        HebergementRepository $hebergementRepo,
        ChambreRepository $chambreRepo,
        ReservationRepository $reservationRepo
    ): Response {
        $reservations = $reservationRepo->findBy([], ['id' => 'DESC']);
        $enAttente = $reservationRepo->findBy(['statut' => 'en_attente']);

        return $this->render('admin/index.html.twig', [
            'reservations' => $reservations,
            'stats' => [
                'hebergements' => count($hebergementRepo->findAll()),
                'chambres'     => count($chambreRepo->findAll()),
                'reservations' => count($reservations),
                'enAttente'    => count($enAttente),
            ]
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

}