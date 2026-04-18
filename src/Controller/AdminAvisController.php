<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Repository\AvisRepository;
use App\Repository\ReclamationRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur d'administration des Avis.
 * Ajout : envoi d'email via MailerService dans la méthode repondre().
 */
#[Route('/admin/avis')]
class AdminAvisController extends AbstractController
{
    #[Route('', name: 'admin_avis_index', methods: ['GET'])]
    public function index(Request $request, AvisRepository $avisRepository): Response
    {
        $statut    = $request->query->get('statut');
        $recherche = $request->query->get('recherche');

        $qb = $avisRepository->createQueryBuilder('a')->orderBy('a.dateAvis', 'DESC');

        if ($statut) {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', $statut);
        }
        if ($recherche) {
            $qb->andWhere('a.contenu LIKE :recherche OR a.userId LIKE :recherche')
               ->setParameter('recherche', '%' . $recherche . '%');
        }

        $avisList = $qb->getQuery()->getResult();

        $allAvisForStats = $avisRepository->findAll();
        $total = 0; $en_attente = 0; $valides = 0; $rejetes = 0; $sumEtoiles = 0;

        foreach ($allAvisForStats as $a) {
            $total++;
            $sumEtoiles += $a->getNbEtoiles() ?? 0;
            switch ($a->getStatut()) {
                case 'En attente': $en_attente++; break;
                case 'Validé':     $valides++;    break;
                case 'Rejeté':     $rejetes++;    break;
            }
        }

        $moy_etoiles = $total > 0 ? round($sumEtoiles / $total, 1) : 0;

        return $this->render('admin/avis/index.html.twig', [
            'avis_list'        => $avisList,
            'stats'            => compact('total', 'en_attente', 'valides', 'rejetes', 'moy_etoiles'),
            'currentStatut'    => $statut,
            'currentRecherche' => $recherche,
        ]);
    }

    #[Route('/stats', name: 'admin_avis_stats', methods: ['GET'])]
    public function stats(AvisRepository $avisRepository): Response
    {
        $allAvis = $avisRepository->findAll();

        $en_attente = 0; $valides = 0; $rejetes = 0;
        $etoilesDistrib = [0, 0, 0, 0, 0];
        $sumEtoiles = 0;
        $dailyCountsData   = [];
        $dailyCountsLabels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date                    = (new \DateTime("-$i days"))->format('Y-m-d');
            $dailyCountsLabels[]     = (new \DateTime("-$i days"))->format('d/m');
            $dailyCountsData[$date]  = 0;
        }

        foreach ($allAvis as $a) {
            switch ($a->getStatut()) {
                case 'En attente': $en_attente++; break;
                case 'Validé':     $valides++;    break;
                case 'Rejeté':     $rejetes++;    break;
            }
            $etoile = $a->getNbEtoiles();
            if ($etoile >= 1 && $etoile <= 5) {
                $etoilesDistrib[$etoile - 1]++;
                $sumEtoiles += $etoile;
            }
            if ($a->getDateAvis()) {
                $dateStr = $a->getDateAvis()->format('Y-m-d');
                if (isset($dailyCountsData[$dateStr])) {
                    $dailyCountsData[$dateStr]++;
                }
            }
        }

        $totalAvis = count($allAvis);
        $stats = [
            'total'      => $totalAvis,
            'en_attente' => $en_attente,
            'valides'    => $valides,
            'rejetes'    => $rejetes,
            'moy_etoiles'=> $totalAvis > 0 ? round($sumEtoiles / $totalAvis, 1) : 0,
        ];

        return $this->render('admin/avis/stats.html.twig', [
            'stats'          => $stats,
            'avisParJour'    => ['labels' => $dailyCountsLabels, 'data' => array_values($dailyCountsData)],
            'etoilesDistrib' => $etoilesDistrib,
        ]);
    }

    #[Route('/{id}/valider', name: 'admin_avis_valider', methods: ['POST'])]
    public function valider(Avis $avis, EntityManagerInterface $entityManager): Response
    {
        $avis->setStatut('Validé');
        $entityManager->flush();
        $this->addFlash('success', 'Avis validé avec succès.');
        return $this->redirectToRoute('admin_avis_index');
    }

    #[Route('/{id}/rejeter', name: 'admin_avis_rejeter', methods: ['POST'])]
    public function rejeter(Avis $avis, EntityManagerInterface $entityManager): Response
    {
        $avis->setStatut('Rejeté');
        $entityManager->flush();
        $this->addFlash('danger', 'Avis rejeté.');
        return $this->redirectToRoute('admin_avis_index');
    }

    /**
     * Enregistre la réponse de l'admin à un avis et notifie le client par email.
     */
    #[Route('/{id}/repondre', name: 'admin_avis_repondre', methods: ['POST'])]
    public function repondre(
        Request                $request,
        Avis                   $avis,
        EntityManagerInterface $entityManager,
        MailerService          $mailerService
    ): Response {
        $reponse = $request->request->get('reponse');
        $avis->setReponse($reponse);
        $entityManager->flush();

        // ── Email de notification au client ─────────────────────
        $mailerService->sendReponseAvis($avis);

        $this->addFlash('success', 'Réponse enregistrée et envoyée au client.');
        return $this->redirectToRoute('admin_avis_index');
    }

    #[Route('/{id}/delete', name: 'admin_avis_delete', methods: ['POST'])]
    public function delete(Avis $avis, EntityManagerInterface $entityManager, ReclamationRepository $reclamationRepository): Response
    {
        $reclamations = $reclamationRepository->findBy(['avis' => $avis]);
        foreach ($reclamations as $rec) {
            $rec->setAvis(null);
        }
        $entityManager->remove($avis);
        $entityManager->flush();
        $this->addFlash('success', 'Avis supprimé.');
        return $this->redirectToRoute('admin_avis_index');
    }
}
