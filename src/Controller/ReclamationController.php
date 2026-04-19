<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Contrôleur front des Réclamations (client).
 *
 * Fonctionnalités avancées ajoutées :
 *  - Email de confirmation client via MailerService
 *  - Suggestion de priorité IA par analyse de mots-clés (POST /reclamation/suggest-priority)
 *  - Recherche AJAX multicritères (GET /reclamation/search)
 */
#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,

        /** Clé Hugging Face injectée depuis .env */
        #[Autowire('%env(HUGGINGFACE_API_KEY)%')]
        private readonly string $hfApiKey
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Liste des réclamations du client
    // ─────────────────────────────────────────────────────────────

    #[Route('/', name: 'reclamation_index', methods: ['GET'])]
    public function index(
        Request $request,
        ReclamationRepository $reclamationRepository,
        PaginatorInterface $paginator
    ): Response {
        $userId = 1;
        $statut   = $request->query->get('statut');
        $priorite = $request->query->get('priorite');
        $recherche = $request->query->get('recherche');

        $qb = $reclamationRepository->createQueryBuilder('r')
            ->where('r.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.dateCreation', 'DESC');

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
            6
        );

        return $this->render('reclamation/index.html.twig', [
            'reclamations'     => $reclamations,
            'currentStatut'    => $statut,
            'currentPriorite'  => $priorite,
            'currentRecherche' => $recherche,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Recherche AJAX multicritères — GET /reclamation/search
    // ─────────────────────────────────────────────────────────────

    /**
     * Retourne les réclamations filtrées en JSON pour la recherche AJAX.
     * Paramètres GET : q (texte), statut, priorite, sort
     */
    #[Route('/search', name: 'reclamation_search', methods: ['GET'])]
    public function search(Request $request, ReclamationRepository $reclamationRepository, \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $userId   = 1;
        $q        = $request->query->get('q', '');
        $statut   = $request->query->get('statut', '');
        $priorite = $request->query->get('priorite', '');
        $sort     = $request->query->get('sort', 'date-desc');

        $qb = $reclamationRepository->createQueryBuilder('r')
            ->where('r.userId = :userId')
            ->setParameter('userId', $userId);

        if ($q) {
            $qb->andWhere('r.titre LIKE :q OR r.contenu LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }
        if ($statut) {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $statut);
        }
        if ($priorite) {
            $qb->andWhere('r.priorite = :priorite')->setParameter('priorite', $priorite);
        }

        // Mapping des priorités pour le tri numérique
        $prioMap = ['Basse' => 1, 'Moyenne' => 2, 'Haute' => 3, 'Urgente' => 4];

        match ($sort) {
            'date-asc'     => $qb->orderBy('r.dateCreation', 'ASC'),
            'priorite-desc'=> $qb->orderBy('r.priorite', 'DESC'),
            'priorite-asc' => $qb->orderBy('r.priorite', 'ASC'),
            default        => $qb->orderBy('r.dateCreation', 'DESC'),
        };

        $results = $qb->getQuery()->getResult();

        $data = array_map(fn(Reclamation $r) => [
            'id'          => $r->getId(),
            'titre'       => $r->getTitre(),
            'statut'      => $r->getStatut(),
            'priorite'    => $r->getPriorite(),
            'prioriteVal' => $prioMap[$r->getPriorite()] ?? 2,
            'type'        => $r->getType() ? $r->getType()->getNom() : '—',
            'dateCreation'=> $r->getDateCreation() ? $r->getDateCreation()->format('d/m/Y') : '—',
            'reponse'     => $r->getReponse(),
            'avisId'      => $r->getAvis() ? $r->getAvis()->getId() : null,
            'showUrl'     => $this->generateUrl('reclamation_show', ['id' => $r->getId()]),
            'editUrl'     => $this->generateUrl('reclamation_edit', ['id' => $r->getId()]),
            'deleteUrl'   => $this->generateUrl('reclamation_delete', ['id' => $r->getId()]),
            'csrfToken'   => $csrfTokenManager->getToken('delete' . $r->getId())->getValue(),
            'avisShowUrl' => $r->getAvis()
                ? $this->generateUrl('avis_show', ['id' => $r->getAvis()->getId()])
                : null,
        ], $results);

        return $this->json(['results' => $data, 'total' => count($data)]);
    }

    // ─────────────────────────────────────────────────────────────
    // Suggestion de priorité IA — POST /reclamation/suggest-priority
    // ─────────────────────────────────────────────────────────────

    /**
     * Analyse le contenu d'une réclamation et suggère une priorité par mots-clés.
     * Retourne JSON : { "priorite": "Urgente", "raison": "Mots-clés détectés : urgent, panne" }
     */
    #[Route('/suggest-priority', name: 'reclamation_suggest_priority', methods: ['POST'])]
    public function suggestPriority(Request $request): JsonResponse
    {
        // Récupérer le texte à analyser (titre + contenu)
        $titre   = mb_strtolower(trim((string) $request->request->get('titre', '')));
        $contenu = mb_strtolower(trim((string) $request->request->get('contenu', '')));
        $texte   = $titre . ' ' . $contenu;

        if (empty(trim($texte))) {
            return $this->json([
                'priorite' => 'Moyenne',
                'raison'   => 'Aucun contenu fourni — priorité par défaut.',
            ]);
        }

        // ── Dictionnaire de mots-clés par niveau de priorité ──────
        $keywordsUrgente = [
            'urgent', 'urgente', 'danger', 'immédiat', 'immédiatement',
            'allergique', 'allergie', 'panne', 'remboursement', 'rembourse',
            'blessure', 'blessé', 'accident', 'urgence', 'critique',
        ];
        $keywordsHaute = [
            'important', 'problème', 'probleme', 'erreur', 'défaut', 'defaut',
            'insatisfait', 'inacceptable', 'retard', 'annulé', 'annule',
            'grave', 'sérieux', 'serieux',
        ];

        // Vérification des mots-clés trouvés
        $foundUrgente = [];
        $foundHaute   = [];

        foreach ($keywordsUrgente as $kw) {
            if (str_contains($texte, $kw)) {
                $foundUrgente[] = $kw;
            }
        }
        foreach ($keywordsHaute as $kw) {
            if (str_contains($texte, $kw)) {
                $foundHaute[] = $kw;
            }
        }

        // ── Détermination de la priorité ──────────────────────────
        if (!empty($foundUrgente)) {
            return $this->json([
                'priorite' => 'Urgente',
                'raison'   => 'Mots-clés détectés : ' . implode(', ', array_unique($foundUrgente)),
            ]);
        }

        if (!empty($foundHaute)) {
            return $this->json([
                'priorite' => 'Haute',
                'raison'   => 'Mots-clés détectés : ' . implode(', ', array_unique($foundHaute)),
            ]);
        }

        // Par défaut : Moyenne
        return $this->json([
            'priorite' => 'Moyenne',
            'raison'   => 'Aucun mot-clé critique détecté — priorité modérée suggérée.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Soumettre une nouvelle réclamation
    // ─────────────────────────────────────────────────────────────

    /**
     * Gère la soumission d'une nouvelle réclamation.
     * Après soumission : email de confirmation envoyé via MailerService.
     */
    #[Route('/new', name: 'reclamation_new', methods: ['GET', 'POST'])]
    public function new(
        Request                $request,
        EntityManagerInterface $entityManager,
        MailerService          $mailerService
    ): Response {
        $reclamation = new Reclamation();
        $form        = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reclamation->setUserId(1);
            $reclamation->setDateCreation(new \DateTime());
            $reclamation->setStatut('En attente');

            // S'assurer qu'une priorité est définie (valeur par défaut si non renseignée)
            if (!$reclamation->getPriorite()) {
                $reclamation->setPriorite('Moyenne');
            }

            // Mettre une valeur par défaut pour typeFeedback afin d'éviter l'erreur SQL "cannot be null"
            if (!$reclamation->getTypeFeedback()) {
                $reclamation->setTypeFeedback('Général');
            }

            $entityManager->persist($reclamation);
            $entityManager->flush();

            // ── Email de confirmation au client ────────────────────
            $mailerService->sendConfirmationReclamation($reclamation);

            // ── Notification admin en session ──────────────────────
            $session  = $request->getSession();
            $notifs   = $session->get('admin_notifications', []);
            $priorite = $reclamation->getPriorite();
            $icon     = $priorite === 'Urgente' ? '🚨' : '📋';
            $type     = $priorite === 'Urgente' ? 'danger' : 'warning';
            $notifs[] = [
                'type'    => $type,
                'icon'    => $icon,
                'message' => 'Nouvelle réclamation : "'
                    . mb_substr($reclamation->getTitre(), 0, 40)
                    . '" — Priorité : ' . $priorite,
                'time'    => (new \DateTime())->format('H:i'),
            ];
            $session->set('admin_notifications', $notifs);

            $this->addFlash('success', 'Réclamation créée avec succès ! Un email de confirmation vous a été envoyé.');

            return $this->redirectToRoute('reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reclamation/new.html.twig', [
            'reclamation' => $reclamation,
            'form'        => $form->createView(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Détail d'une réclamation
    // ─────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'avis'        => $reclamation->getAvis(),
        ]);
    }

    #[Route('/{id}/pdf', name: 'reclamation_pdf', methods: ['GET'])]
    public function exportPdf(Reclamation $reclamation): Response
    {
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

    // ─────────────────────────────────────────────────────────────
    // Modifier une réclamation
    // ─────────────────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($reclamation->getStatut() === 'Fermée') {
            $this->addFlash('error', 'Une réclamation fermée ne peut pas être modifiée.');
            return $this->redirectToRoute('reclamation_index');
        }

        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Réclamation modifiée avec succès.');

            // Notification admin
            $session = $request->getSession();
            $notifs  = $session->get('admin_notifications', []);
            $notifs[] = [
                'type'    => 'info',
                'icon'    => '✏️',
                'message' => 'La réclamation #' . $reclamation->getId() . ' a été modifiée par le client.',
                'time'    => (new \DateTime())->format('H:i'),
            ];
            $session->set('admin_notifications', $notifs);

            return $this->redirectToRoute('reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form'        => $form->createView(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Supprimer une réclamation
    // ─────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reclamation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
            $this->addFlash('success', 'Réclamation supprimée avec succès.');
        }
        return $this->redirectToRoute('reclamation_index', [], Response::HTTP_SEE_OTHER);
    }
}