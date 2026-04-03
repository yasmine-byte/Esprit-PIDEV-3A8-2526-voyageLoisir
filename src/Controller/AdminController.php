<?php
namespace App\Controller;

use App\Repository\HebergementRepository;
use App\Repository\ChambreRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(
        HebergementRepository $hebergementRepo,
        ChambreRepository $chambreRepo,
        ReservationRepository $reservationRepo
    ): Response
    {
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
}