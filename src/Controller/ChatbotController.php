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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;

#[Route('/chatbot')]
class ChatbotController extends AbstractController
{
    private const API_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const API_MODEL = 'llama-3.1-8b-instant';
    private const SYSTEM_PROMPT =
        'Tu es l\'assistant virtuel de Vianova, une plateforme de voyage et loisirs. '
        . 'Tu aides les clients à créer des réclamations ET des avis. '

        . 'RÈGLES IMPORTANTES : '
        . '- Une RÉCLAMATION = un problème, une plainte, quelque chose qui s\'est mal passé. '
        . '- Un AVIS = une opinion, une note, un retour d\'expérience (positif OU négatif avec étoiles). '
        . '- Si le client mentionne des étoiles ou une note, c\'est TOUJOURS un AVIS. '
        . '- Si le client dit "je veux laisser un avis" ou "je donne X étoiles", c\'est TOUJOURS un AVIS. '
        . '- Si le client dit "je veux faire une réclamation" ou décrit un problème sans note, c\'est une RÉCLAMATION. '

        . 'POUR UNE RÉCLAMATION : '
        . '1) Vérifie si une réclamation similaire existe déjà en demandant. '
        . '2) Détermine la priorité : Urgente, Haute, Moyenne, Basse. '
        . '3) Génère un titre court professionnel (max 8 mots). '
        . '4) REFORMULE en description formelle et professionnelle de 2-4 phrases. '
        . '5) Réponds UNIQUEMENT avec ce JSON : '
        . '{"action":"create_reclamation","titre":"...","contenu":"...","priorite":"..."} '

        . 'POUR UN AVIS : '
        . '1) Collecte la note sur 5 et le commentaire. '
        . '2) REFORMULE en avis élégant et professionnel de 2-3 phrases. '
        . '3) Réponds UNIQUEMENT avec ce JSON : '
        . '{"action":"create_avis","contenu":"...","nbEtoiles":X,"type":"Qualité du voyage"} '

        . 'IMPORTANT : '
        . '- Réponds TOUJOURS directement avec le JSON sans explication. '
        . '- Ne jamais créer une réclamation si le client veut laisser un avis. '
        . '- Pour les salutations, réponds normalement. '
        . '- Tu réponds TOUJOURS en français.';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GROQ_API_KEY)%')]
        private readonly string $apiKey
    ) {}

    #[Route('/widget', name: 'chatbot_widget', methods: ['GET'])]
    public function widget(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'message' => 'Chatbot is ready']);
    }

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
        TypeAvisRepository $typeAvisRepository,
        MailerInterface $mailer
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

        // Envoi email de notification
        try {
            $email = (new Email())
                ->from('rayenhafian72@gmail.com')
                ->to('rayenhafian72@gmail.com')
                ->subject('Nouvelle réclamation #' . $reclamation->getId() . ' via Chatbot')
                ->html('
                    <h2 style="color:#f35525;">Nouvelle Réclamation créée via Chatbot</h2>
                    <p><strong>ID :</strong> #' . $reclamation->getId() . '</p>
                    <p><strong>Titre :</strong> ' . $reclamation->getTitre() . '</p>
                    <p><strong>Priorité :</strong> ' . $reclamation->getPriorite() . '</p>
                    <p><strong>Contenu :</strong> ' . $reclamation->getContenu() . '</p>
                    <p><strong>Date :</strong> ' . $reclamation->getDateCreation()->format('d/m/Y H:i') . '</p>
                ');
            $transport = Transport::fromDsn($_ENV['MAILER_DSN_RAYEN']);
            $customMailer = new Mailer($transport);
            $customMailer->send($email);
        } catch (\Exception $e) {
            // Email non bloquant
        }

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
        TypeAvisRepository $typeAvisRepository,
        MailerInterface $mailer
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

        // Envoi email de notification
        try {
            $emailMsg = (new Email())
                ->from('rayenhafian72@gmail.com')
                ->to('rayenhafian72@gmail.com')
                ->subject('Nouvel avis #' . $avis->getId() . ' via Chatbot')
                ->html('
                    <h2 style="color:#f35525;">Nouvel Avis créé via Chatbot</h2>
                    <p><strong>ID :</strong> #' . $avis->getId() . '</p>
                    <p><strong>Note :</strong> ' . $avis->getNbEtoiles() . '/5 ★</p>
                    <p><strong>Contenu :</strong> ' . $avis->getContenu() . '</p>
                    <p><strong>Date :</strong> ' . $avis->getDateAvis()->format('d/m/Y H:i') . '</p>
                ');
            $transport = Transport::fromDsn($_ENV['MAILER_DSN_RAYEN']);
            $customMailer = new Mailer($transport);
            $customMailer->send($emailMsg);
        } catch (\Exception $e) {
            // Email non bloquant
        }

        return $this->json([
            'success' => true,
            'message' => 'Avis créé avec succès !',
            'id'      => $avis->getId(),
        ]);
    }
}