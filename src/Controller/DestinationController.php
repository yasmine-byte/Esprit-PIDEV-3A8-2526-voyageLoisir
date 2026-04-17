<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Form\DestinationType;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/destination')]
final class DestinationController extends AbstractController
{
    #[Route(name: 'app_destination_index', methods: ['GET'])]
    public function index(Request $request, DestinationRepository $destinationRepository): Response
    {
        $search = $request->query->get('search', '');
        $saison = $request->query->get('saison', '');
        $statut = $request->query->get('statut', '');
        $tri    = $request->query->get('tri', 'id');
        $ordre  = $request->query->get('ordre', 'ASC');

        $destinations = $destinationRepository->findByFilters($search, $saison, $statut, $tri, $ordre);

        return $this->render('destination/index.html.twig', [
            'destinations' => $destinations,
            'search'       => $search,
            'saison'       => $saison,
            'statut'       => $statut,
            'tri'          => $tri,
            'ordre'        => $ordre,
        ]);
    }

    #[Route('/new', name: 'app_destination_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $destination = new Destination();
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $request->request->has('generate_ai')) {
            $nom  = $destination->getNom();
            $pays = $destination->getPays();
            if ($nom && $pays) {
                $prompt = "Génère UNIQUEMENT une description touristique courte et attractive de $nom situé en $pays. Maximum 100 mots. Réponds avec le texte de la description uniquement, sans titre, sans introduction, sans explication.";
                $apiKey = $_ENV['GROQ_API_KEY'] ?? '';
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => "https://api.groq.com/openai/v1/chat/completions",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Authorization: Bearer $apiKey"],
                    CURLOPT_POSTFIELDS     => json_encode([
                        "model"      => "llama-3.1-8b-instant",
                        "max_tokens" => 200,
                        "messages"   => [
                            ["role" => "system", "content" => "Tu es un rédacteur touristique. Tu réponds UNIQUEMENT avec la description demandée, sans aucun autre texte."],
                            ["role" => "user",   "content" => $prompt]
                        ]
                    ])
                ]);
                $response = curl_exec($ch); curl_close($ch);
                $result = json_decode($response, true);
                $text = trim($result['choices'][0]['message']['content'] ?? "Erreur génération");
                $destination->setDescription($text);
            }
            return $this->render('destination/new.html.twig', [
                'destination' => $destination,
                'form'        => $this->createForm(DestinationType::class, $destination)->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($destination);
            $entityManager->flush();
            return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('destination/new.html.twig', [
            'destination' => $destination,
            'form'        => $form->createView(),
        ]);
    }

    #[Route('/voice-search', name: 'app_destination_voice_search', methods: ['GET'])]
    public function voiceSearch(): Response
    {
        return $this->render('destination/voice_search.html.twig');
    }

    #[Route('/voice-query', name: 'app_destination_voice_query', methods: ['POST'])]
    public function voiceQuery(Request $request, DestinationRepository $destinationRepository): Response
    {
        $query = $request->toArray()['query'] ?? '';

        $session = $request->getSession();
        $session->start();
        $session->set('total_recherches_vocales',
            ($session->get('total_recherches_vocales', 0)) + 1
        );

        $saisonsMots = [
            'Printemps' => ['printemps', 'spring', 'mars', 'avril', 'mai'],
            'Ete'       => ['été', 'ete', 'summer', 'juin', 'juillet', 'août', 'aout'],
            'Automne'   => ['automne', 'autumn', 'fall', 'septembre', 'octobre', 'novembre'],
            'Hiver'     => ['hiver', 'winter', 'décembre', 'janvier', 'février'],
        ];
        $queryLower = mb_strtolower($query);
        foreach ($saisonsMots as $saison => $mots) {
            foreach ($mots as $mot) {
                if (str_contains($queryLower, $mot)) {
                    $key = 'recherche_saison_' . $saison;
                    $session->set($key, ($session->get($key, 0)) + 1);
                    break;
                }
            }
        }

        $destinations = $destinationRepository->findAll();
        $liste = array_map(function($d) {
            return $d->getId() . ':' . $d->getNom() . ' (' . $d->getPays() . ', ' . ($d->getMeilleureSaison() ?? 'N/A') . ')';
        }, $destinations);
        $listeTexte = implode(' | ', $liste);

        $prompt = "You are a multilingual travel assistant for VoyageLoisir.

RULE 1 — LANGUAGE DETECTION (most important rule):
Detect the language of the user's message and respond ONLY in that language.
- User writes in English → ALL JSON fields must be in English
- User writes in French → ALL JSON fields must be in French
- User writes in Arabic → ALL JSON fields must be in Arabic
Never mix languages. Never respond in French if the user wrote in English.

RULE 2 — GREETINGS:
If the user says hello, hi, hey, bonjour, merci, thank you, bye, au revoir, or any message that is NOT a travel destination search, return ONLY this JSON with the message translated into the user's detected language:
{\"disponibles\":[], \"hors_catalogue\":{\"existe\":true, \"nom\":\"\", \"pays\":\"\", \"message\":\"[Write a friendly greeting and invite the user to describe their dream destination — in their language]\", \"infos\":\"\", \"conseil\":\"\"}}

RULE 3 — DESTINATION SEARCH:
If the user is searching for a destination, use this catalogue (format id:nom (pays, saison)):
$listeTexte

Search behaviour:
1. If one or more catalogue destinations match → put them in 'disponibles' with their exact id
2. If nothing matches → empty 'disponibles', describe the destination in 'hors_catalogue'
3. You can combine both: catalogue results AND a worldwide suggestion

RULE 4 — JSON FORMAT (strict):
Return ONLY this JSON, no text before or after, no markdown:
{
  \"disponibles\": [
    {\"id\": 0, \"nom\":\"...\", \"pays\":\"...\", \"saison\":\"...\", \"description\":\"...\", \"activites\":[\"...\",\"...\"]}
  ],
  \"hors_catalogue\": {
    \"existe\": false,
    \"nom\": \"...\",
    \"pays\": \"...\",
    \"message\": \"...\",
    \"infos\": \"...\",
    \"conseil\": \"...\"
  }
}

RULE 5 — STRING FIELDS ONLY:
Every field (nom, pays, message, infos, conseil, description) must be a simple string, never an object or nested structure.
The id in 'disponibles' must be the exact numeric id from the catalogue.
If all results are from the catalogue, set hors_catalogue.existe = false.";

        $apiKey = $_ENV['GROQ_API_KEY'] ?? '';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.groq.com/openai/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Authorization: Bearer $apiKey"],
            CURLOPT_POSTFIELDS     => json_encode([
                "model"      => "llama-3.3-70b-versatile",
                "max_tokens" => 1200,
                "messages"   => [
                    ["role" => "system", "content" => $prompt],
                    ["role" => "user",   "content" => $query]
                ]
            ])
        ]);
        $response = curl_exec($ch); curl_close($ch);
        $result = json_decode($response, true);
        $raw    = $result['choices'][0]['message']['content'] ?? '{}';
        $raw    = preg_replace('/```json|```/', '', $raw);
        $raw    = trim($raw);
        $data   = json_decode($raw, true);

        if (isset($data['hors_catalogue'])) {
            $hc = &$data['hors_catalogue'];
            $hc['nom']     = isset($hc['nom'])     ? (is_array($hc['nom'])     ? implode(', ', $hc['nom'])    : (string)$hc['nom'])     : '';
            $hc['pays']    = isset($hc['pays'])    ? (is_array($hc['pays'])    ? implode(', ', $hc['pays'])   : (string)$hc['pays'])    : '';
            $hc['message'] = isset($hc['message']) ? (is_array($hc['message']) ? implode(' ', $hc['message']) : (string)$hc['message']) : '';
            $hc['infos']   = isset($hc['infos'])   ? (is_array($hc['infos'])   ? implode(' ', $hc['infos'])   : (string)$hc['infos'])   : '';
            $hc['conseil'] = isset($hc['conseil']) ? (is_array($hc['conseil']) ? implode(' ', $hc['conseil']) : (string)$hc['conseil']) : '';
            $hc['existe']  = (bool)($hc['existe'] ?? false);
        }

        return $this->json($data);
    }

    #[Route('/exchange/{from}/{to}', name: 'app_exchange_rate', methods: ['GET'])]
    public function exchangeRate(string $from, string $to): Response
    {
        $apiKey = $_ENV['EXCHANGERATE_API_KEY'] ?? '';
        $url    = "https://v6.exchangerate-api.com/v6/$apiKey/pair/$from/$to";
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true]);
        $response = curl_exec($ch); curl_close($ch);
        $data = json_decode($response, true);
        return $this->json(['rate' => $data['conversion_rate'] ?? null]);
    }

    #[Route('/stats', name: 'app_destination_stats', methods: ['GET'])]
    public function stats(DestinationRepository $destinationRepository, Request $request): Response
    {
        $destinations = $destinationRepository->findAll();

        $parPays = [];
        foreach ($destinations as $d) {
            $pays = $d->getPays() ?? 'Inconnu';
            $parPays[$pays] = ($parPays[$pays] ?? 0) + 1;
        }
        arsort($parPays);

        $parSaison = ['Printemps' => 0, 'Ete' => 0, 'Automne' => 0, 'Hiver' => 0];
        foreach ($destinations as $d) {
            $s = $d->getMeilleureSaison() ?? '';
            if (isset($parSaison[$s])) $parSaison[$s]++;
        }

        $visitesSaison = ['Printemps' => 0, 'Ete' => 0, 'Automne' => 0, 'Hiver' => 0];
        foreach ($destinations as $d) {
            $s = $d->getMeilleureSaison() ?? '';
            if (isset($visitesSaison[$s])) $visitesSaison[$s] += $d->getNbVisites() ?? 0;
        }

        $likesSaison = ['Printemps' => 0, 'Ete' => 0, 'Automne' => 0, 'Hiver' => 0];
        foreach ($destinations as $d) {
            $s = $d->getMeilleureSaison() ?? '';
            if (isset($likesSaison[$s])) $likesSaison[$s] += $d->getNbLikes() ?? 0;
        }

        $topVisites = $destinations;
        usort($topVisites, fn($a, $b) => $b->getNbVisites() - $a->getNbVisites());
        $topVisites = array_slice($topVisites, 0, 5);

        $topLikes = $destinations;
        usort($topLikes, fn($a, $b) => ($b->getNbLikes() ?? 0) - ($a->getNbLikes() ?? 0));
        $topLikes = array_slice($topLikes, 0, 5);

        $actifs = 0;
        foreach ($destinations as $d) {
            if ($d->isStatut()) $actifs++;
        }
        $inactifs = count($destinations) - $actifs;

        $totalVisites = 0;
        $totalLikes   = 0;
        foreach ($destinations as $d) {
            $totalVisites += $d->getNbVisites() ?? 0;
            $totalLikes   += $d->getNbLikes()   ?? 0;
        }

        $session = $request->getSession();
        $session->start();
        $totalRechercheVocale = $session->get('total_recherches_vocales', 0);
        $recherchesSaison = [
            'Printemps' => $session->get('recherche_saison_Printemps', 0),
            'Ete'       => $session->get('recherche_saison_Ete', 0),
            'Automne'   => $session->get('recherche_saison_Automne', 0),
            'Hiver'     => $session->get('recherche_saison_Hiver', 0),
        ];

        $totalReservations     = 0;
        $reservationsParSaison = ['Printemps' => 0, 'Ete' => 0, 'Automne' => 0, 'Hiver' => 0];
        $topReservations       = [];

        foreach ($destinations as $d) {
            $nbRes = 0;
            foreach ($d->getVoyages() as $voyage) {
                $nbRes += $voyage->getReservedByUsers()->count();
            }
            $totalReservations += $nbRes;
            $s = $d->getMeilleureSaison() ?? '';
            if (isset($reservationsParSaison[$s])) $reservationsParSaison[$s] += $nbRes;
            $topReservations[] = [
                'nom'    => $d->getNom(),
                'pays'   => $d->getPays(),
                'saison' => $d->getMeilleureSaison(),
                'nbRes'  => $nbRes,
                'statut' => $d->isStatut(),
            ];
        }

        usort($topReservations, fn($a, $b) => $b['nbRes'] - $a['nbRes']);
        $topReservations = array_slice($topReservations, 0, 5);

        $topVisitesData = [];
        foreach ($topVisites as $d) {
            $nbResD = 0;
            foreach ($d->getVoyages() as $v) {
                $nbResD += $v->getReservedByUsers()->count();
            }
            $topVisitesData[] = [
                'nom'       => $d->getNom(),
                'pays'      => $d->getPays(),
                'saison'    => $d->getMeilleureSaison(),
                'nbVisites' => $d->getNbVisites() ?? 0,
                'nbLikes'   => $d->getNbLikes()   ?? 0,
                'nbRes'     => $nbResD,
                'statut'    => $d->isStatut(),
            ];
        }

        $topLikesData = [];
        foreach ($topLikes as $d) {
            $topLikesData[] = [
                'nom'     => $d->getNom(),
                'pays'    => $d->getPays(),
                'nbLikes' => $d->getNbLikes() ?? 0,
            ];
        }

        return $this->json([
            'total'                  => count($destinations),
            'actifs'                 => $actifs,
            'inactifs'               => $inactifs,
            'totalVisites'           => $totalVisites,
            'totalLikes'             => $totalLikes,
            'totalRechercheVocale'   => $totalRechercheVocale,
            'totalReservations'      => $totalReservations,
            'parPays'                => $parPays,
            'parSaison'              => $parSaison,
            'visitesSaison'          => $visitesSaison,
            'likesSaison'            => $likesSaison,
            'recherchesSaison'       => $recherchesSaison,
            'reservationsParSaison'  => $reservationsParSaison,
            'topVisites'             => $topVisitesData,
            'topLikes'               => $topLikesData,
            'topReservations'        => $topReservations,
        ]);
    }

    #[Route('/like/{id}', name: 'app_destination_like', methods: ['POST'])]
    public function like(Destination $destination, Request $request, EntityManagerInterface $em): Response
    {
        $session  = $request->getSession();
        $session->start();
        $likedKey = 'liked_dest_' . $destination->getId();
        $liked    = $session->get($likedKey, false);

        if ($liked) {
            $destination->setNbLikes(max(0, ($destination->getNbLikes() ?? 0) - 1));
            $session->set($likedKey, false);
            $liked = false;
        } else {
            $destination->setNbLikes(($destination->getNbLikes() ?? 0) + 1);
            $session->set($likedKey, true);
            $liked = true;
        }

        $em->flush();

        return $this->json([
            'liked' => $liked,
            'total' => $destination->getNbLikes() ?? 0,
        ]);
    }

    #[Route('/like-status/{id}', name: 'app_destination_like_status', methods: ['GET'])]
    public function likeStatus(Destination $destination, Request $request): Response
    {
        $session = $request->getSession();
        $session->start();
        $liked = $session->get('liked_dest_' . $destination->getId(), false);

        return $this->json([
            'liked' => $liked,
            'total' => $destination->getNbLikes() ?? 0,
        ]);
    }

    #[Route('/{id}', name: 'app_destination_show', methods: ['GET'])]
    public function show(Destination $destination): Response
    {
        return $this->render('destination/show.html.twig', [
            'destination' => $destination,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_destination_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Destination $destination, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DestinationType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $request->request->has('generate_ai')) {
            $nom  = $destination->getNom();
            $pays = $destination->getPays();
            if ($nom && $pays) {
                $prompt = "Génère UNIQUEMENT une description touristique courte et attractive de $nom situé en $pays. Maximum 100 mots. Réponds avec le texte de la description uniquement, sans titre, sans introduction, sans explication.";
                $apiKey = $_ENV['GROQ_API_KEY'] ?? '';
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => "https://api.groq.com/openai/v1/chat/completions",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Authorization: Bearer $apiKey"],
                    CURLOPT_POSTFIELDS     => json_encode([
                        "model"      => "llama-3.1-8b-instant",
                        "max_tokens" => 200,
                        "messages"   => [
                            ["role" => "system", "content" => "Tu es un rédacteur touristique. Tu réponds UNIQUEMENT avec la description demandée, sans aucun autre texte."],
                            ["role" => "user",   "content" => $prompt]
                        ]
                    ])
                ]);
                $response = curl_exec($ch); curl_close($ch);
                $result = json_decode($response, true);
                $text   = trim($result['choices'][0]['message']['content'] ?? "Erreur génération");
                $destination->setDescription($text);
            }
            return $this->render('destination/edit.html.twig', [
                'destination' => $destination,
                'form'        => $this->createForm(DestinationType::class, $destination)->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('destination/edit.html.twig', [
            'destination' => $destination,
            'form'        => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_destination_delete', methods: ['POST'])]
    public function delete(Request $request, Destination $destination, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $destination->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($destination);
            $entityManager->flush();
        }
        return $this->redirectToRoute('app_destination_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/itineraire', name: 'app_destination_itineraire', methods: ['POST'])]
    public function itineraire(Destination $destination, Request $request): Response
    {
        $duree = (int) $request->request->get('duree', 3);
        $nom   = $destination->getNom();
        $pays  = $destination->getPays();
        $prompt = "Tu es un expert en voyage. Génère un itinéraire touristique détaillé de $duree jours pour $nom, $pays.
Pour chaque jour, donne exactement ce format JSON :
{\"jours\":[{\"jour\":1,\"titre\":\"Titre du jour\",\"matin\":\"...\",\"dejeuner\":\"...\",\"apres_midi\":\"...\",\"diner\":\"...\",\"transport\":\"...\",\"conseil\":\"...\"}]}
Réponds UNIQUEMENT avec le JSON, sans texte avant ou après.";
        $apiKey = $_ENV['GROQ_API_KEY'] ?? '';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.groq.com/openai/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Authorization: Bearer $apiKey"],
            CURLOPT_POSTFIELDS     => json_encode([
                "model"      => "llama-3.1-8b-instant",
                "max_tokens" => 2000,
                "messages"   => [["role" => "user", "content" => $prompt]]
            ])
        ]);
        $response = curl_exec($ch); curl_close($ch);
        $result = json_decode($response, true);
        $text   = preg_replace('/```json|```/', '', $result['choices'][0]['message']['content'] ?? '{}');
        return $this->json(json_decode(trim($text), true));
    }

    #[Route('/{id}/videos', name: 'app_destination_videos', methods: ['GET'])]
    public function videos(Destination $destination): Response
    {
        $nom    = $destination->getNom();
        $pays   = $destination->getPays();
        $apiKey = $_ENV['YOUTUBE_API_KEY'] ?? '';
        $query  = urlencode("voyage $nom $pays");
        $url    = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=$query&type=video&maxResults=1&key=$apiKey";
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true]);
        $response = curl_exec($ch); curl_close($ch);
        $data  = json_decode($response, true);
        $video = null;
        if (!empty($data['items'][0])) {
            $item  = $data['items'][0];
            $video = [
                'id'     => $item['id']['videoId'],
                'titre'  => $item['snippet']['title'],
                'chaine' => $item['snippet']['channelTitle'],
            ];
        }
        return $this->json($video);
    }
}