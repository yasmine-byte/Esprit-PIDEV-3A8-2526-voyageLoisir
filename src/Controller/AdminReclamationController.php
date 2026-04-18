<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Contrôleur d'administration des Réclamations.
 * Remplacement : EmailService + SmsService → MailerService (propre, fonctionnel).
 */
#[Route('/admin/reclamation')]
class AdminReclamationController extends AbstractController
{
    #[Route('', name: 'admin_reclamation_index', methods: ['GET'])]
    public function index(Request $request, ReclamationRepository $reclamationRepository, PaginatorInterface $paginator): Response
    {
        $statut    = $request->query->get('statut');
        $priorite  = $request->query->get('priorite');
        $recherche = $request->query->get('recherche');

        $qb = $reclamationRepository->createQueryBuilder('r')->orderBy('r.dateCreation', 'DESC');

        if ($statut) {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $statut);
        }
        if ($priorite) {
            $qb->andWhere('r.priorite = :priorite')->setParameter('priorite', $priorite);
        }
        if ($recherche) {
            $qb->andWhere('r.titre LIKE :recherche OR r.contenu LIKE :recherche')
               ->setParameter('recherche', '%' . $recherche . '%');
        }

        $reclamations = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            10
        );

        $allReclamations = $reclamationRepository->findAll();
        $total = 0; $en_attente = 0; $en_cours = 0; $traitees = 0; $fermees = 0; $urgentes = 0;

        foreach ($allReclamations as $r) {
            $total++;
            switch ($r->getStatut()) {
                case 'En attente': $en_attente++; break;
                case 'En cours':   $en_cours++;   break;
                case 'Traitée':    $traitees++;   break;
                case 'Fermée':     $fermees++;    break;
            }
            if ($r->getPriorite() === 'Urgente' && $r->getStatut() !== 'Fermée') {
                $urgentes++;
            }
        }

        $stats = compact('total', 'en_attente', 'en_cours', 'traitees', 'fermees', 'urgentes');

        return $this->render('admin/reclamation/index.html.twig', [
            'reclamations'     => $reclamations,
            'stats'            => $stats,
            'currentStatut'    => $statut,
            'currentPriorite'  => $priorite,
            'currentRecherche' => $recherche,
        ]);
    }

    #[Route('/{id}', name: 'admin_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('admin/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'avis'        => $reclamation->getAvis(),
        ]);
    }

    #[Route('/{id}/pdf', name: 'admin_reclamation_pdf', methods: ['GET'])]
    public function exportPdf(
        Reclamation $reclamation
    ): Response {
        $html = $this->renderView('reclamation/pdf.html.twig', [
            'reclamation' => $reclamation,
            'avis'        => $reclamation->getAvis(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'reclamation_' . $reclamation->getId() . '_' . date('Ymd') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    #[Route('/{id}/statut', name: 'admin_reclamation_statut', methods: ['POST'])]
    public function changerStatut(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        $statut       = $request->request->get('statut');
        $validStatuts = ['En attente', 'En cours', 'Traitée', 'Fermée'];
        if (in_array($statut, $validStatuts)) {
            $reclamation->setStatut($statut);
            $entityManager->flush();
            $this->addFlash('success', 'Statut mis à jour.');
        }
        return $this->redirectToRoute('admin_reclamation_index');
    }

    #[Route('/{id}/priorite', name: 'admin_reclamation_priorite', methods: ['POST'])]
    public function changerPriorite(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        $priorite       = $request->request->get('priorite');
        $validPriorites = ['Basse', 'Moyenne', 'Haute', 'Urgente'];
        if (in_array($priorite, $validPriorites)) {
            $reclamation->setPriorite($priorite);
            $entityManager->flush();
            $this->addFlash('success', 'Priorité mise à jour.');
        }
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('admin_reclamation_index');
    }

    /**
     * Enregistre la réponse de l'admin à une réclamation,
     * passe le statut à "Traitée" et notifie le client par email.
     *
     * Remplace l'ancien code qui utilisait EmailService + SmsService (cassés).
     */
    #[Route('/{id}/repondre', name: 'admin_reclamation_repondre', methods: ['POST'])]
    public function repondre(
        Request                $request,
        Reclamation            $reclamation,
        EntityManagerInterface $entityManager,
        MailerService          $mailerService
    ): Response {
        $reponse = $request->request->get('reponse');
        $reclamation->setReponse($reponse);
        $reclamation->setStatut('Traitée');
        $entityManager->flush();

        // ── Email de notification au client ─────────────────────
        $mailerService->sendReponseReclamation($reclamation);

        $this->addFlash('success', 'Réponse enregistrée et notifiée au client.');
        return $this->redirectToRoute('admin_reclamation_index');
    }

    #[Route('/{id}/delete', name: 'admin_reclamation_delete', methods: ['POST'])]
    public function delete(Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($reclamation);
        $entityManager->flush();
        $this->addFlash('success', 'Réclamation supprimée.');
        return $this->redirectToRoute('admin_reclamation_index');
    }
}
