<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use App\Repository\RoleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(UsersRepository $usersRepository, RoleRepository $roleRepository): Response
    {
        $allUsers      = $usersRepository->findAll();
        $activeUsers   = array_filter($allUsers, fn($u) => $u->isActive() === true);
        $inactiveUsers = array_filter($allUsers, fn($u) => $u->isActive() !== true);
        $roles         = $roleRepository->findAll();
        $latestUsers   = $usersRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $monthlyData   = $usersRepository->countByMonth();

        $roleStats = [];
        foreach ($roles as $role) {
            $roleStats[$role->getName()] = count($role->getNo());
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers'    => count($allUsers),
            'activeUsers'   => count($activeUsers),
            'inactiveUsers' => count($inactiveUsers),
            'totalRoles'    => count($roles),
            'latestUsers'   => $latestUsers,
            'monthlyData'   => $monthlyData,
            'roleStats'     => $roleStats,
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
}
