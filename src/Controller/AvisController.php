<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Reclamation;
use App\Form\AvisType;
use App\Repository\AvisRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Contrôleur front des Avis (client).
 *
 * Fonctionnalités avancées ajoutées :
 *  - Envoi d'emails via MailerService (confirmation, alerte admin)
 *  - Analyse de sentiment IA via Hugging Face (cardiffnlp/twitter-xlm-roberta-base-sentiment)
 *  - Route AJAX GET /avis/search pour la recherche multicritères côté serveur
 */
#[Route('/avis')]
class AvisController extends AbstractController
{
    /** Modèle HF pour l'analyse de sentiment */
    private const SENTIMENT_MODEL_URL =
        'https://api-inference.huggingface.co/models/cardiffnlp/twitter-xlm-roberta-base-sentiment';

    public function __construct(
        private readonly HttpClientInterface $httpClient,

        /** Clé API Hugging Face injectée depuis .env */
        #[Autowire('%env(HUGGINGFACE_API_KEY)%')]
        private readonly string $hfApiKey
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Liste des avis du client
    // ─────────────────────────────────────────────────────────────

    #[Route('/', name: 'avis_index', methods: ['GET'])]
    public function index(AvisRepository $avisRepository): Response
    {
        $userId = 1; // À remplacer par $this->getUser()->getId() une fois l'auth activée
        return $this->render('avis/index.html.twig', [
            'avis' => $avisRepository->findBy(['userId' => $userId]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Recherche AJAX multicritères — GET /avis/search
    // ─────────────────────────────────────────────────────────────

    /**
     * Retourne les avis filtrés en JSON pour la recherche AJAX côté serveur.
     * Paramètres GET : q (texte), statut, etoiles, sort
     */
    #[Route('/search', name: 'avis_search', methods: ['GET'])]
    public function search(Request $request, AvisRepository $avisRepository, \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $userId   = 1;
        $q        = $request->query->get('q', '');
        $statut   = $request->query->get('statut', '');
        $etoiles  = $request->query->get('etoiles', '');
        $sort     = $request->query->get('sort', 'date-desc');

        // Construction de la requête DQL
        $qb = $avisRepository->createQueryBuilder('a')
            ->where('a.userId = :userId')
            ->setParameter('userId', $userId);

        if ($q) {
            $qb->andWhere('a.contenu LIKE :q')->setParameter('q', '%' . $q . '%');
        }
        if ($statut) {
            $qb->andWhere('a.statut = :statut')->setParameter('statut', $statut);
        }
        if ($etoiles) {
            $qb->andWhere('a.nbEtoiles = :etoiles')->setParameter('etoiles', (int) $etoiles);
        }

        // Tri
        match ($sort) {
            'date-asc'     => $qb->orderBy('a.dateAvis', 'ASC'),
            'etoiles-desc' => $qb->orderBy('a.nbEtoiles', 'DESC'),
            'etoiles-asc'  => $qb->orderBy('a.nbEtoiles', 'ASC'),
            default        => $qb->orderBy('a.dateAvis', 'DESC'),
        };

        $results = $qb->getQuery()->getResult();

        // Sérialisation manuelle (pas de JMS/serializer requis)
        $data = array_map(fn(Avis $a) => [
            'id'       => $a->getId(),
            'contenu'  => $a->getContenu(),
            'statut'   => $a->getStatut(),
            'nbEtoiles'=> $a->getNbEtoiles(),
            'type'     => $a->getType() ? $a->getType()->getNom() : '—',
            'dateAvis' => $a->getDateAvis() ? $a->getDateAvis()->format('d/m/Y') : '—',
            'reponse'  => $a->getReponse(),
            'showUrl'  => $this->generateUrl('avis_show', ['id' => $a->getId()]),
            'editUrl'  => $this->generateUrl('avis_edit', ['id' => $a->getId()]),
            'deleteUrl'=> $this->generateUrl('avis_delete', ['id' => $a->getId()]),
            'csrfToken'=> $csrfTokenManager->getToken('delete' . $a->getId())->getValue(),
        ], $results);

        return $this->json(['results' => $data, 'total' => count($data)]);
    }

    // ─────────────────────────────────────────────────────────────
    // Soumettre un nouvel avis
    // ─────────────────────────────────────────────────────────────

    /**
     * Gère la soumission d'un nouvel avis.
     *
     * Après soumission :
     *  1. Analyse de sentiment IA (Hugging Face) → flash si incohérence note/ton
     *  2. Email de confirmation client (MailerService)
     *  3. Alerte admin si note ≤ 2 (MailerService)
     *  4. Génération automatique d'une réclamation si note ≤ 2
     */
    #[Route('/new', name: 'avis_new', methods: ['GET', 'POST'])]
    public function new(
        Request                $request,
        EntityManagerInterface $entityManager,
        MailerService          $mailerService
    ): Response {
        $avis = new Avis();
        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Données de base
            $avis->setUserId(1);
            $avis->setDateAvis(new \DateTime());
            $avis->setStatut('En attente');

            $entityManager->persist($avis);

            // ── Analyse de sentiment IA ────────────────────────────
            $sentiment = $this->analyzeSentiment($avis->getContenu());
            $label     = strtolower($sentiment['label'] ?? 'neutral');

            // Incohérence : ton négatif mais note ≥ 3
            if ($label === 'negative' && $avis->getNbEtoiles() >= 3) {
                $this->addFlash('warning',
                    '⚠️ Notre IA a détecté un ton négatif dans votre message. '
                    . 'Voulez-vous reconsidérer votre note ?'
                );
            }
            // Incohérence : ton positif mais note ≤ 2
            if ($label === 'positive' && $avis->getNbEtoiles() <= 2) {
                $this->addFlash('info',
                    'ℹ️ Notre IA a détecté un ton positif dans votre message.'
                );
            }

            // ── Notification admin en session ─────────────────────
            $session = $request->getSession();
            $notifs  = $session->get('admin_notifications', []);

            if ($avis->getNbEtoiles() <= 2) {
                // Génération automatique d'une réclamation
                $reclamation = new Reclamation();
                $reclamation->setAvis($avis);
                $reclamation->setContenu($avis->getContenu());
                $reclamation->setTypeFeedback('Négatif');
                $reclamation->setStatut('En attente');
                $reclamation->setPriorite('Moyenne');
                $reclamation->setTitre('Réclamation automatique suite à un avis négatif');
                $reclamation->setUserId($avis->getUserId());
                $reclamation->setType($avis->getType());
                $reclamation->setDateCreation(new \DateTime());
                $entityManager->persist($reclamation);

                $this->addFlash('warning', 'Votre avis négatif a généré une réclamation automatique.');

                $notifs[] = [
                    'type'    => 'danger',
                    'icon'    => '🚨',
                    'message' => 'Nouveau avis négatif (' . $avis->getNbEtoiles() . '★) + réclamation générée.',
                    'time'    => (new \DateTime())->format('H:i'),
                ];

                // ── Email alerte admin (note ≤ 2) ─────────────────
                $mailerService->sendAlertAdminAvisNegatif($avis);
            } else {
                $this->addFlash('success', 'Votre avis a été soumis avec succès.');
                $notifs[] = [
                    'type'    => 'success',
                    'icon'    => '⭐',
                    'message' => 'Nouvel avis positif (' . $avis->getNbEtoiles() . '★) — en attente.',
                    'time'    => (new \DateTime())->format('H:i'),
                ];
            }

            $session->set('admin_notifications', $notifs);
            $entityManager->flush();

            // ── Email de confirmation au client ───────────────────
            $mailerService->sendConfirmationAvis($avis);

            return $this->redirectToRoute('avis_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('avis/new.html.twig', [
            'avis' => $avis,
            'form' => $form->createView(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Détail d'un avis
    // ─────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'avis_show', methods: ['GET'])]
    public function show(Avis $avis, EntityManagerInterface $entityManager): Response
    {
        $reclamation = $entityManager->getRepository(Reclamation::class)
            ->findOneBy(['avis' => $avis]);

        return $this->render('avis/show.html.twig', [
            'avis'        => $avis,
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/pdf', name: 'avis_pdf', methods: ['GET'])]
    public function exportPdf(
        Avis $avis,
        EntityManagerInterface $entityManager
    ): Response {
        $reclamation = $entityManager->getRepository(Reclamation::class)
            ->findOneBy(['avis' => $avis]);

        $html = $this->renderView('avis/pdf.html.twig', [
            'avis'        => $avis,
            'reclamation' => $reclamation,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'avis_' . $avis->getId() . '_' . date('Ymd') . '.pdf';

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
    // Modifier un avis
    // ─────────────────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'avis_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Avis $avis, EntityManagerInterface $entityManager): Response
    {
        if ($avis->getStatut() !== 'En attente') {
            $this->addFlash('error', 'Seuls les avis "En attente" peuvent être modifiés.');
            return $this->redirectToRoute('avis_index');
        }

        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Avis modifié avec succès.');

            // Notification admin
            $session = $request->getSession();
            $notifs  = $session->get('admin_notifications', []);
            $notifs[] = [
                'type'    => 'info',
                'icon'    => '✏️',
                'message' => 'Un client a modifié son avis #' . $avis->getId() . ' (' . $avis->getNbEtoiles() . '★).',
                'time'    => (new \DateTime())->format('H:i'),
            ];
            $session->set('admin_notifications', $notifs);

            return $this->redirectToRoute('avis_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('avis/edit.html.twig', [
            'avis' => $avis,
            'form' => $form->createView(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Supprimer un avis
    // ─────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'avis_delete', methods: ['POST'])]
    public function delete(Request $request, Avis $avis, EntityManagerInterface $entityManager): Response
    {
        if ($avis->getStatut() !== 'En attente') {
            $this->addFlash('error', 'Seuls les avis "En attente" peuvent être supprimés.');
            return $this->redirectToRoute('avis_index');
        }

        if ($this->isCsrfTokenValid('delete' . $avis->getId(), $request->request->get('_token'))) {
            $reclamation = $entityManager->getRepository(Reclamation::class)
                ->findOneBy(['avis' => $avis]);
            if ($reclamation) {
                $reclamation->setAvis(null);
            }
            $entityManager->remove($avis);
            $entityManager->flush();
            $this->addFlash('success', 'Avis supprimé avec succès.');
        }

        return $this->redirectToRoute('avis_index', [], Response::HTTP_SEE_OTHER);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper privé : analyse de sentiment Hugging Face
    // ─────────────────────────────────────────────────────────────

    /**
     * Analyse le sentiment du texte via Hugging Face.
     * Modèle : cardiffnlp/twitter-xlm-roberta-base-sentiment
     * Retourne un tableau ['label' => 'positive|negative|neutral', 'score' => float]
     */
    private function analyzeSentiment(string $text): array
    {
        if (empty($this->hfApiKey)) {
            return ['label' => 'neutral', 'score' => 0.5];
        }

        try {
            $response = $this->httpClient->request('POST', self::SENTIMENT_MODEL_URL, [
                'headers' => ['Authorization' => 'Bearer ' . $this->hfApiKey],
                'json'    => ['inputs' => mb_substr($text, 0, 500)], // HF limite à ~512 tokens
                'timeout' => 15,
            ]);

            $data = $response->toArray(false);

            // Données invalides ou erreur API
            if (!isset($data[0]) || isset($data['error'])) {
                return ['label' => 'neutral', 'score' => 0.5];
            }

            // Le modèle retourne [[{label, score}, ...]] — on prend le label avec le score max
            $best      = ['label' => 'neutral', 'score' => 0.0];
            $sentiments = $data[0];

            foreach ($sentiments as $s) {
                if (($s['score'] ?? 0) > $best['score']) {
                    $best = ['label' => strtolower($s['label']), 'score' => $s['score']];
                }
            }

            return $best;
        } catch (\Exception) {
            return ['label' => 'neutral', 'score' => 0.5];
        }
    }
}