<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Reclamation;
use App\Repository\TypeAvisRepository;
use App\Repository\TypeReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Mailer;

#[Route('/chatbot')]
class ChatbotController extends AbstractController
{
    private const API_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const API_MODEL = 'llama-3.1-8b-instant';
    private const SYSTEM_PROMPT = 'Tu es un assistant ViaNoVa. INSTRUCTIONS STRICTES : '
        . 'Si le message de l\'utilisateur décrit un problème, une plainte ou une mauvaise expérience : '
        . 'réponds UNIQUEMENT avec ce JSON sans aucun autre texte : '
        . '{"action":"create_reclamation","titre":"TITRE_COURT","contenu":"DESCRIPTION_FORMELLE","priorite":"Moyenne"} '
        . 'Si le message mentionne des étoiles, une note ou un avis positif : '
        . 'réponds UNIQUEMENT avec ce JSON sans aucun autre texte : '
        . '{"action":"create_avis","contenu":"AVIS_PROFESSIONNEL","nbEtoiles":3,"type":"Service Client"} '
        . 'Si le message est une salutation (bonjour, merci, etc.) : réponds normalement en 1 phrase. '
        . 'INTERDICTION ABSOLUE de poser des questions. INTERDICTION de demander des confirmations. '
        . 'Génère TOUJOURS le JSON immédiatement dès qu\'il y a un problème ou un avis. '
        . 'Réponds toujours en français.';

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

    #[Route('/create', name: 'chatbot_create', methods: ['POST'])]
    public function createFromChatbot(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non connecté'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $type = $data['type'] ?? null; // 'avis' ou 'reclamation'

        if ($type === 'avis') {
            $avis = new Avis();
            $avis->setContenu($data['contenu'] ?? '');
            $avis->setNbEtoiles((int)($data['note'] ?? 3));
            $avis->setStatut('En attente');
            $avis->setUserId($user->getId());
            $avis->setDateAvis(new \DateTime());

            // Chercher le type d'avis par nom
            $typeAvis = $entityManager->getRepository(\App\Entity\TypeAvis::class)
                ->findOneBy(['nom' => $data['categorie'] ?? 'Service Client']);
            if ($typeAvis) $avis->setType($typeAvis);

            $entityManager->persist($avis);
            $entityManager->flush();

            // Email de confirmation
            try {
                if (method_exists($user, 'getEmail') && $user->getEmail()) {
                    $email = (new Email())
                        ->from(new Address('rayenhafian72@gmail.com', 'ViaNoVa'))
                        ->to($user->getEmail())
                        ->subject('⭐ Votre avis a bien été reçu – ViaNoVa')
                        ->html('
                            <div style="font-family:Arial,sans-serif;max-width:580px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                                <div style="background:linear-gradient(135deg,#d4845a,#e8a87c);padding:36px 32px;text-align:center;">
                                    <div style="font-size:2rem;font-weight:900;color:#fff;letter-spacing:4px;margin-bottom:6px;">VIANOVA</div>
                                    <div style="color:rgba(255,255,255,0.85);font-size:0.9rem;">Confirmation de votre avis</div>
                                </div>
                                <div style="padding:32px;">
                                    <p style="font-size:1.1rem;font-weight:700;color:#333;margin-bottom:12px;">Bonjour ' . htmlspecialchars($user->getPrenom() . ' ' . $user->getNom()) . ',</p>
                                    <p style="color:#666;line-height:1.7;">Votre avis a bien été enregistré via notre assistant IA. Il sera examiné par notre équipe.</p>
                                    <div style="background:#fff8f4;border-radius:12px;padding:16px;border:1px solid #f5cba7;margin-top:16px;">
                                        <p style="margin:0;font-size:0.85rem;color:#d4845a;font-weight:700;">Note : ' . ($data['note'] ?? 3) . '/5 étoiles<br>Catégorie : ' . htmlspecialchars($data['categorie'] ?? 'Service Client') . '</p>
                                    </div>
                                </div>
                                <div style="background:#f8f9fa;padding:16px;text-align:center;font-size:0.75rem;color:#aaa;">VIANOVA — Plateforme de Voyage & Loisirs</div>
                            </div>
                        ');
                    $mailer->send($email);
                }
            } catch (\Exception $e) {}

            return new JsonResponse(['success' => true, 'type' => 'avis', 'id' => $avis->getId()]);
        }

        if ($type === 'reclamation') {
            $reclamation = new Reclamation();
            $reclamation->setTitre($data['titre'] ?? 'Réclamation via Assistant IA');
            $reclamation->setContenu($data['contenu'] ?? '');
            $reclamation->setPriorite($data['priorite'] ?? 'Moyenne');
            $reclamation->setStatut('En attente');
            $reclamation->setUserId($user->getId());
            $reclamation->setDateCreation(new \DateTime());
            $reclamation->setTypeFeedback('reclamation');

            // Chercher le type de réclamation par nom
            $typeRecl = $entityManager->getRepository(\App\Entity\TypeReclamation::class)
                ->findOneBy(['nom' => $data['categorie'] ?? 'Service Client']);
            if ($typeRecl) $reclamation->setType($typeRecl);

            $entityManager->persist($reclamation);
            $entityManager->flush();

            // Email de confirmation
            try {
                if (method_exists($user, 'getEmail') && $user->getEmail()) {
                    $email = (new Email())
                        ->from(new Address('rayenhafian72@gmail.com', 'ViaNoVa'))
                        ->to($user->getEmail())
                        ->subject('📋 Votre réclamation a bien été reçue – ViaNoVa')
                        ->html('
                            <div style="font-family:Arial,sans-serif;max-width:580px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
                                <div style="background:linear-gradient(135deg,#d4845a,#e8a87c);padding:36px 32px;text-align:center;">
                                    <div style="font-size:2rem;font-weight:900;color:#fff;letter-spacing:4px;margin-bottom:6px;">VIANOVA</div>
                                    <div style="color:rgba(255,255,255,0.85);font-size:0.9rem;">Confirmation de votre réclamation</div>
                                </div>
                                <div style="padding:32px;">
                                    <p style="font-size:1.1rem;font-weight:700;color:#333;margin-bottom:12px;">Bonjour ' . htmlspecialchars($user->getPrenom() . ' ' . $user->getNom()) . ',</p>
                                    <p style="color:#666;line-height:1.7;">Votre réclamation a bien été enregistrée via notre assistant IA. Numéro de suivi sera disponible après traitement.</p>
                                    <div style="background:#fff8f4;border-radius:12px;padding:16px;border:1px solid #f5cba7;margin-top:16px;">
                                        <p style="margin:0;font-size:0.85rem;color:#d4845a;font-weight:700;">Titre : ' . htmlspecialchars($data['titre'] ?? '') . '<br>Priorité : ' . htmlspecialchars($data['priorite'] ?? 'Moyenne') . '<br>Catégorie : ' . htmlspecialchars($data['categorie'] ?? 'Service Client') . '</p>
                                    </div>
                                </div>
                                <div style="background:#f8f9fa;padding:16px;text-align:center;font-size:0.75rem;color:#aaa;">VIANOVA — Plateforme de Voyage & Loisirs</div>
                            </div>
                        ');
                    $mailer->send($email);
                }
            } catch (\Exception $e) {}

            return new JsonResponse(['success' => true, 'type' => 'reclamation', 'id' => $reclamation->getId()]);
        }

        return new JsonResponse(['error' => 'Type invalide'], 400);
    }

    #[Route('/submit-reclamation', name: 'chatbot_submit_reclamation', methods: ['POST'])]
    public function submitReclamation(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): JsonResponse {
        $titre    = trim((string) $request->request->get('titre', ''));
        $contenu  = trim((string) $request->request->get('contenu', ''));
        $priorite = trim((string) $request->request->get('priorite', 'Moyenne'));

        if (empty($titre)) $titre = 'Réclamation via Assistant IA';
        if (empty($contenu)) $contenu = $titre;

        $prioritesValides = ['Basse', 'Moyenne', 'Haute', 'Urgente'];
        if (!in_array($priorite, $prioritesValides)) $priorite = 'Moyenne';

        /** @var \App\Entity\Users|null $currentUser */
        $currentUser = $this->getUser();

        $reclamation = new Reclamation();
        $reclamation->setUserId($currentUser ? (int)$currentUser->getId() : 1);
        $reclamation->setTitre($titre);
        $reclamation->setContenu($contenu);
        $reclamation->setPriorite($priorite);
        $reclamation->setStatut('En attente');
        $reclamation->setTypeFeedback('Général');
        $reclamation->setDateCreation(new \DateTime());

        $entityManager->persist($reclamation);
        $entityManager->flush();

        // Email au client
        try {
            if ($currentUser && $currentUser->getEmail()) {
                $email = (new Email())
                    ->from(new \Symfony\Component\Mime\Address('rayenhafian72@gmail.com', 'ViaNoVa Agency'))
                    ->to($currentUser->getEmail())
                    ->subject('📋 Réclamation reçue – ViaNoVa')
                    ->html('<p>Bonjour ' . htmlspecialchars((string)$currentUser->getPrenom()) . ',</p><p>Votre réclamation <strong>#' . $reclamation->getId() . '</strong> a été créée.</p>');
                $mailer->send($email);
            }
        } catch (\Exception $e) {}

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

        if (empty($contenu)) $contenu = 'Avis créé via assistant IA';
        if ($nbEtoiles < 1) $nbEtoiles = 1;
        if ($nbEtoiles > 5) $nbEtoiles = 5;

        $type = null;
        if (!empty($typeNom)) {
            $type = $typeAvisRepository->findOneBy(['nom' => $typeNom]);
        }
        if (!$type) $type = $typeAvisRepository->findOneBy([]);

        /** @var \App\Entity\Users|null $currentUser */
        $currentUser = $this->getUser();
        $userId = $currentUser ? (int)$currentUser->getId() : 1;

        $avis = new Avis();
        $avis->setUserId($userId);
        $avis->setContenu($contenu);
        $avis->setNbEtoiles($nbEtoiles);
        $avis->setStatut('En attente');
        $avis->setDateAvis(new \DateTime());
        if ($type) $avis->setType($type);

        $entityManager->persist($avis);
        $entityManager->flush();

        // Créer automatiquement une réclamation si note < 3 étoiles
        if ($nbEtoiles < 3) {
            try {
                $reclamationAuto = new Reclamation();
                $reclamationAuto->setUserId($currentUser ? (int)$currentUser->getId() : 1);
                $reclamationAuto->setTitre('Avis négatif – Note ' . $nbEtoiles . '/5');
                $reclamationAuto->setContenu(
                    'Réclamation générée automatiquement suite à un avis négatif (' . $nbEtoiles . '/5 étoiles). ' .
                    'Contenu de l\'avis : ' . $contenu
                );
                $reclamationAuto->setPriorite($nbEtoiles === 1 ? 'Urgente' : 'Haute');
                $reclamationAuto->setStatut('En attente');
                $reclamationAuto->setTypeFeedback('Général');
                $reclamationAuto->setDateCreation(new \DateTime());
                $entityManager->persist($reclamationAuto);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Non bloquant
            }
        }

        // Email au client connecté
        try {
            if ($currentUser && $currentUser->getEmail()) {
                $prenom = $currentUser->getPrenom() ?? '';
                $nom    = $currentUser->getNom() ?? '';
                $stars  = str_repeat('★', $nbEtoiles) . str_repeat('☆', 5 - $nbEtoiles);
                $emailMsg = (new Email())
                    ->from(new \Symfony\Component\Mime\Address('rayenhafian72@gmail.com', 'ViaNoVa Agency'))
                    ->to($currentUser->getEmail())
                    ->subject('⭐ Votre avis a bien été reçu – ViaNoVa')
                    ->html('
                        <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;">
                            <div style="background:linear-gradient(135deg,#d4845a,#e8a87c);padding:28px;text-align:center;">
                                <div style="font-size:1.6rem;font-weight:900;color:#fff;letter-spacing:3px;">VIANOVA</div>
                            </div>
                            <div style="padding:24px;">
                                <p style="font-weight:700;color:#333;">Bonjour ' . htmlspecialchars($prenom . ' ' . $nom) . ',</p>
                                <p style="color:#666;">Votre avis <strong>#' . $avis->getId() . '</strong> a été créé via notre assistant IA.</p>
                                <div style="background:#fff8f4;border-radius:10px;padding:14px;border:1px solid #f5cba7;margin-top:14px;font-size:0.87rem;color:#d4845a;font-weight:700;">
                                    ⭐ Note : ' . $stars . ' (' . $nbEtoiles . '/5)
                                </div>
                            </div>
                            <div style="background:#f8f9fa;padding:12px;text-align:center;font-size:0.72rem;color:#aaa;">VIANOVA — Plateforme de Voyage & Loisirs</div>
                        </div>
                    ');
                $mailer->send($emailMsg);
            }
        } catch (\Exception $e) {
            // Email non bloquant
        }

        return $this->json([
            'success' => true,
            'message' => 'Avis créé avec succès !',
            'id'      => $avis->getId(),
            'reclamation_auto' => $nbEtoiles < 3,
        ]);
    }
}