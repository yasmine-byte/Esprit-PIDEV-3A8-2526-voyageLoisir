<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Reclamation;
use App\Entity\Users;
use App\Form\AvisType;
use App\Repository\AvisRepository;
use App\Repository\UsersRepository;
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
use Knp\Component\Pager\PaginatorInterface;
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

        #[Autowire('%env(HUGGINGFACE_API_KEY)%')]
        private readonly string $hfApiKey
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Liste des avis du client
    // ─────────────────────────────────────────────────────────────

    #[Route('/', name: 'avis_index', methods: ['GET'])]
    public function index(
        Request $request,
        AvisRepository $avisRepository,
        UsersRepository $usersRepository,
        PaginatorInterface $paginator
    ): Response {
        $qb = $avisRepository->createQueryBuilder('a')
            ->orderBy('a.dateAvis', 'DESC');

        $avis = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
            6
        );

        // Build a map [userId => User] so the template can display real names
        $usersMap = [];
        foreach ($usersRepository->findAll() as $u) {
            $usersMap[$u->getId()] = $u;
        }

        return $this->render('avis/index.html.twig', [
            'avis'     => $avis,
            'usersMap' => $usersMap,
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
        $q        = $request->query->get('q', '');
        $statut   = $request->query->get('statut', '');
        $etoiles  = $request->query->get('etoiles', '');
        $sort     = $request->query->get('sort', 'date-desc');

        // Construction de la requête DQL
        $qb = $avisRepository->createQueryBuilder('a');

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
            'sentiment'=> $a->getSentimentLabel(),
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
        MailerService          $mailerService,
        UsersRepository        $usersRepository
    ): Response {
        $avis = new Avis();
        /** @var \App\Entity\Users|null $user */
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\Users) {
            return $this->redirectToRoute('admin_login');
        }

        $form = $this->createForm(AvisType::class, $avis, [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avis->setUserId($user->getId());
            $avis->setStatut('En attente');
            $avis->setDateAvis(new \DateTime());

            // 1. Analyse de sentiment IA (Hugging Face)
            $sentiment = $this->analyzeSentimentInternal((string)$avis->getContenu());
            $avis->setSentimentLabel($sentiment['label'] ?? 'neutral');
            $avis->setSentimentScore((float)($sentiment['score'] ?? 0.5));

            $entityManager->persist($avis);
            $entityManager->flush();

            // 2. Email de confirmation au client
            $mailerService->sendConfirmationAvis($avis);

            // 3. Si note <= 2 : Alerte admin + Création Réclamation Auto
            if ($avis->getNbEtoiles() <= 2) {
                // Alerte Admin
                $mailerService->sendAlertAdminAvisNegatif($avis);

                // Réclamation automatique
                $reclamation = new Reclamation();
                $reclamation->setUserId($user->getId());
                $reclamation->setTitre('Avis négatif #' . $avis->getId());
                $reclamation->setContenu('Avis client (' . $avis->getNbEtoiles() . '/5) : ' . $avis->getContenu());
                $reclamation->setPriorite($avis->getNbEtoiles() === 1 ? 'Urgente' : 'Haute');
                $reclamation->setStatut('En attente');
                $reclamation->setDateCreation(new \DateTime());
                $reclamation->setAvis($avis); // Liaison bidirectionnelle
                $reclamation->setTypeFeedback('reclamation');
                
                // Le type est obligatoire pour Reclamation aussi
                if ($avis->getType()) {
                    $reclamation->setType($avis->getType());
                }

                $entityManager->persist($reclamation);
                $entityManager->flush();

                $this->addFlash('warning', 'Votre avis a été publié. Une réclamation a été créée automatiquement car votre note est faible.');
            } else {
                $this->addFlash('success', 'Votre avis a bien été publié ! Merci pour votre retour.');
            }

            return $this->redirectToRoute('front_profile');
        }

        return $this->render('avis/new.html.twig', [
            'form'         => $form->createView(),
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
            'user'        => $this->getUser(),
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
        /** @var \App\Entity\Users|null $user */
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\Users) {
            return $this->redirectToRoute('admin_login');
        }

        if ($avis->getStatut() !== 'En attente') {
            $this->addFlash('error', 'Seuls les avis "En attente" peuvent être modifiés.');
            return $this->redirectToRoute('avis_index');
        }
        $form = $this->createForm(AvisType::class, $avis, [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
        ]);
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

            return $this->redirectToRoute('front_profile', [], Response::HTTP_SEE_OTHER);
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

        if ($this->isCsrfTokenValid('delete' . $avis->getId(), (string)$request->request->get('_token'))) {
            $reclamation = $entityManager->getRepository(Reclamation::class)
                ->findOneBy(['avis' => $avis]);
            if ($reclamation) {
                $reclamation->setAvis(null);
            }
            $entityManager->remove($avis);
            $entityManager->flush();
            $this->addFlash('success', 'Avis supprimé avec succès.');
        }

        return $this->redirectToRoute('front_profile', [], Response::HTTP_SEE_OTHER);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper privé : analyse de sentiment Hugging Face
    // ─────────────────────────────────────────────────────────────

    #[Route('/analyze-sentiment', name: 'avis_analyze_sentiment', methods: ['POST'])]
    /** @return array<string, mixed> */
    public function analyzeSentiment(Request $request): JsonResponse
    {
        $text = (string)$request->request->get('text', '');
        if (empty($text)) {
            return $this->json(['label' => 'neutral', 'score' => 0.5]);
        }
        // ... (rest of the logic could go here, but I'll keep the private one for now if it's used elsewhere)
        return $this->json($this->analyzeSentimentInternal($text));
    }

    /** @return array<string, mixed> */
    private function analyzeSentimentInternal(string $text): array
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