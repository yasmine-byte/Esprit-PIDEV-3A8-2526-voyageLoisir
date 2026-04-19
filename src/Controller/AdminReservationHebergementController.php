<?php
namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reservation/hebergement')]
class AdminReservationHebergementController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    #[Route('/', name: 'admin_reservation_hebergement_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository): Response
    {
        $statut = $request->query->get('statut');
        $search = $request->query->get('search');

        $qb = $reservationRepository->createQueryBuilder('r')
            ->leftJoin('r.hebergement', 'h')
            ->addSelect('h');

        if ($statut) {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $statut);
        }
        if ($search) {
            $qb->andWhere('r.clientNom LIKE :search OR r.clientEmail LIKE :search OR r.clientTel LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('r.createdAt', 'DESC');
        $reservations = $qb->getQuery()->getResult();

        return $this->render('admin/reservation_hebergement/index.html.twig', [
            'reservations' => $reservations,
            'statut' => $statut,
            'search' => $search,
        ]);
    }

    #[Route('/{id}/statut', name: 'admin_reservation_hebergement_statut', methods: ['POST'])]
    public function updateStatut(int $id, Request $request, ReservationRepository $reservationRepository, EntityManagerInterface $em): Response
    {
        $reservation = $reservationRepository->find($id);
        if ($reservation) {
            $ancienStatut = $reservation->getStatut();
            $newStatut = $request->request->get('statut');

            if (in_array($newStatut, ['en_attente', 'confirmee', 'annulee'], true)) {
                $reservation->setStatut($newStatut);
                $em->flush();

                if ($newStatut !== $ancienStatut && $reservation->getFcmToken()) {
                    $clientNom = $reservation->getClientNom() ?: 'Client';
                    $hebergementAdresse = $reservation->getHebergement()?->getAdresse() ?? 'votre hÃ©bergement';

                    if ($newStatut === 'confirmee') {
                        $this->notificationService->notifyReservationConfirmee(
                            $reservation->getFcmToken(),
                            $clientNom,
                            $hebergementAdresse
                        );
                    }

                    if ($newStatut === 'annulee') {
                        $this->notificationService->notifyReservationAnnulee(
                            $reservation->getFcmToken(),
                            $clientNom,
                            $hebergementAdresse
                        );
                    }
                }

                $this->addFlash('success', 'Statut mis Ã  jour avec succÃ¨s !');
            }
        }
        return $this->redirectToRoute('admin_reservation_hebergement_index');
    }

    #[Route('/{id}/delete', name: 'admin_reservation_hebergement_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, ReservationRepository $reservationRepository, EntityManagerInterface $em): Response
    {
        $reservation = $reservationRepository->find($id);
        if ($reservation && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'RÃ©servation supprimÃ©e avec succÃ¨s !');
        }
        return $this->redirectToRoute('admin_reservation_hebergement_index');
    }
}
