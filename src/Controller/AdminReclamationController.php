<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reclamation')]
class AdminReclamationController extends AbstractController
{
    #[Route('', name: 'admin_reclamation_index', methods: ['GET'])]
    public function index(Request $request, ReclamationRepository $reclamationRepository): Response
    {
        $statut = $request->query->get('statut');
        $priorite = $request->query->get('priorite');
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

        $reclamations = $qb->getQuery()->getResult();

        $allReclamations = $reclamationRepository->findAll();
        $total = 0;
        $en_attente = 0;
        $en_cours = 0;
        $traitees = 0;
        $fermees = 0;
        $urgentes = 0;

        foreach ($allReclamations as $r) {
            $total++;
            switch ($r->getStatut()) {
                case 'En attente': $en_attente++; break;
                case 'En cours': $en_cours++; break;
                case 'Traitée': $traitees++; break;
                case 'Fermée': $fermees++; break;
            }
            if ($r->getPriorite() === 'Urgente' && $r->getStatut() !== 'Fermée') {
                $urgentes++;
            }
        }

        $stats = [
            'total' => $total,
            'en_attente' => $en_attente,
            'en_cours' => $en_cours,
            'traitees' => $traitees,
            'fermees' => $fermees,
            'urgentes' => $urgentes
        ];

        return $this->render('admin/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
            'stats' => $stats,
            'currentStatut' => $statut,
            'currentPriorite' => $priorite,
            'currentRecherche' => $recherche
        ]);
    }

    #[Route('/{id}', name: 'admin_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('admin/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'avis' => $reclamation->getAvis()
        ]);
    }

    #[Route('/{id}/statut', name: 'admin_reclamation_statut', methods: ['POST'])]
    public function changerStatut(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        $statut = $request->request->get('statut');
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
        $priorite = $request->request->get('priorite');
        $validPriorites = ['Basse', 'Moyenne', 'Haute', 'Urgente'];
        if (in_array($priorite, $validPriorites)) {
            $reclamation->setPriorite($priorite);
            $entityManager->flush();
            $this->addFlash('success', 'Priorité mise à jour.');
        }

        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('admin_reclamation_index');
    }

    #[Route('/{id}/repondre', name: 'admin_reclamation_repondre', methods: ['POST'])]
    public function repondre(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        $reponse = $request->request->get('reponse');
        $reclamation->setReponse($reponse);
        $entityManager->flush();
        $this->addFlash('success', 'Réponse enregistrée avec succès.');
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
