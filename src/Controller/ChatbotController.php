<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Reclamation;
use App\Repository\TypeAvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/chatbot')]
class ChatbotController extends AbstractController
{
    private const API_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const API_MODEL = 'llama-3.1-8b-instant';
    private const SYSTEM_PROMPT =
        'Tu es l\'assistant virtuel de Vianova, une plateforme de voyage et loisirs. '
        . 'Tu peux créer automatiquement des réclamations ET des avis. '

        . 'POUR UNE RÉCLAMATION (problème, plainte, insatisfaction) : '
        . '1) Collecte ce qui s\'est passé. NE REDEMANDE PAS ce que le client a déjà dit. '
        . '2) Détermine la priorité : Urgente (danger/accident), Haute (problème important), Moyenne (gêne), Basse (suggestion). '
        . '3) Génère un titre court et professionnel (max 8 mots). '
        . '4) Pour le contenu, utilise EXACTEMENT les mots du client tels qu\'il les a écrits, sans reformuler ni répondre. Copie simplement ce qu\'il a dit. '
        . '5) Réponds UNIQUEMENT avec ce JSON : '
        . '{"action":"create_reclamation","titre":"[titre court]","contenu":"[mots exacts du client]","priorite":"[Basse|Moyenne|Haute|Urgente]"} '

        . 'POUR UN AVIS (satisfaction, compliment, retour positif) : '
        . '1) Collecte le commentaire et la note sur 5. '
        . '2) Pour le contenu, utilise EXACTEMENT les mots du client tels qu\'il les a écrits, sans reformuler ni répondre. '
        . '3) Réponds UNIQUEMENT avec ce JSON : '
        . '{"action":"create_avis","contenu":"[mots exacts du client]","nbEtoiles":[1-5],"type":"Qualité du voyage"} '

        . 'Pour les salutations, réponds normalement en français. '
        . 'Pour les sujets non liés à Vianova, réponds : "Je suis désolé, je suis l\'assistant de Vianova et je ne peux répondre qu\'aux questions liées à nos services." '
        . 'Tu réponds TOUJOURS en français.';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GROQ_API_KEY)%')]
        private readonly string $apiKey
    ) {}

    #[Route('/ask', name: 'chatbot_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $message = trim((string) $request->request->get('message', ''));
        $historyJson = $request->request->get('history', '[]');
        $history = json_decode($historyJson, true) ?? [];

        if (empty($message)) {
            return $this->json(['response' => 'Veuillez saisir un message.']);
        }

        if (empty($this->apiKey)) {
            return $this->json(['response' => 'Le service IA n\'est pas configuré.']);
        }

        // Construire les messages : system + historique + nouveau message
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];

        // Ajouter l'historique (max 10 derniers messages pour éviter de dépasser les limites)
        $recentHistory = array_slice($history, -10);
        foreach ($recentHistory as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        // Ajouter le nouveau message
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::API_MODEL,
                    'messages'    => $messages,
                    'max_tokens'  => 400,
                    'temperature' => 0.6,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray(false);

            if (isset($data['choices'][0]['message']['content'])) {
                $botResponse = trim($data['choices'][0]['message']['content']);
                return $this->json(['response' => $botResponse]);
            }

            if (isset($data['error'])) {
                error_log('[Chatbot] Erreur : ' . json_encode($data['error']));
            }

        } catch (\Exception $e) {
            error_log('[Chatbot] Exception : ' . $e->getMessage());
        }

        return $this->json(['response' => 'Désolé, je rencontre des difficultés techniques. Veuillez réessayer.']);
    }

    #[Route('/submit-reclamation', name: 'chatbot_submit_reclamation', methods: ['POST'])]
    public function submitReclamation(
        Request $request,
        EntityManagerInterface $entityManager,
        TypeAvisRepository $typeAvisRepository
    ): JsonResponse {
        $titre    = trim((string) $request->request->get('titre', ''));
        $contenu  = trim((string) $request->request->get('contenu', ''));
        $priorite = trim((string) $request->request->get('priorite', 'Moyenne'));

        if (empty($titre) || empty($contenu)) {
            return $this->json(['success' => false, 'message' => 'Titre et description requis.']);
        }

        // Valider la priorité
        $prioritesValides = ['Basse', 'Moyenne', 'Haute', 'Urgente'];
        if (!in_array($priorite, $prioritesValides)) {
            $priorite = 'Moyenne';
        }

        // Trouver un type par défaut
        $type = $typeAvisRepository->findOneBy([]) ?? null;

        $reclamation = new Reclamation();
        $reclamation->setUserId(1);
        $reclamation->setTitre($titre);
        $reclamation->setContenu($contenu);
        $reclamation->setPriorite($priorite);
        $reclamation->setStatut('En attente');
        $reclamation->setTypeFeedback('Général');
        $reclamation->setDateCreation(new \DateTime());
        if ($type) {
            $reclamation->setType($type);
        }

        $entityManager->persist($reclamation);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Réclamation créée avec succès !',
            'id'      => $reclamation->getId(),
        ]);
    }

    #[Route('/submit-avis', name: 'chatbot_submit_avis', methods: ['POST'])]
    public function submitAvis(
        Request $request,
        EntityManagerInterface $entityManager,
        TypeAvisRepository $typeAvisRepository
    ): JsonResponse {
        $contenu   = trim((string) $request->request->get('contenu', ''));
        $nbEtoiles = (int) $request->request->get('nbEtoiles', 3);
        $typeNom   = trim((string) $request->request->get('type', ''));

        if (empty($contenu)) {
            return $this->json(['success' => false, 'message' => 'Contenu requis.']);
        }

        // Valider les étoiles
        if ($nbEtoiles < 1) $nbEtoiles = 1;
        if ($nbEtoiles > 5) $nbEtoiles = 5;

        // Trouver le type d'avis
        $type = null;
        if (!empty($typeNom)) {
            $type = $typeAvisRepository->findOneBy(['nom' => $typeNom]);
        }
        if (!$type) {
            $type = $typeAvisRepository->findOneBy([]);
        }

        $avis = new Avis();
        $avis->setUserId(1);
        $avis->setContenu($contenu);
        $avis->setNbEtoiles($nbEtoiles);
        $avis->setStatut('En attente');
        $avis->setDateAvis(new \DateTime());
        if ($type) {
            $avis->setType($type);
        }

        $entityManager->persist($avis);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Avis créé avec succès !',
            'id'      => $avis->getId(),
        ]);
    }
}