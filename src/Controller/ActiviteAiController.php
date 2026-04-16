<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Repository\ActiviteRepository;
use App\Repository\ReservationActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/activite')]
final class ActiviteAiController extends AbstractController
{
    #[Route('/generate-description', name: 'app_activite_generate_description', methods: ['POST'])]
    public function generateDescription(Request $request, HttpClientInterface $client): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Requête invalide.'
            ], 400);
        }

        $nom = trim((string) ($data['nom'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));
        $lieu = trim((string) ($data['lieu'] ?? ''));
        $duree = trim((string) ($data['duree'] ?? ''));

        if ($nom === '' || $type === '' || $lieu === '' || $duree === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez remplir le nom, le type, le lieu et la durée avant de générer la description.'
            ], 400);
        }

        $prompt = "Génère une description professionnelle, attractive et claire en français pour une activité touristique.
Nom : $nom
Type : $type
Lieu : $lieu
Durée : $duree heures

Contraintes :
- écrire un seul paragraphe
- ton professionnel et engageant
- entre 40 et 80 mots
- ne pas mettre de titre
- répondre uniquement avec la description.";

        $aiResult = $this->callOpenRouter($client, $prompt, 0.7, 'Vianova Activite AI');

        if (!$aiResult['success']) {
            return new JsonResponse($aiResult, 500);
        }

        return new JsonResponse([
            'success' => true,
            'description' => trim($aiResult['content'])
        ]);
    }

    #[Route('/generate-rating-from-form', name: 'app_activite_generate_rating_from_form', methods: ['POST'])]
    public function generateRatingFromForm(Request $request, HttpClientInterface $client): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Requête invalide.'
            ], 400);
        }

        $nom = trim((string) ($data['nom'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));
        $lieu = trim((string) ($data['lieu'] ?? ''));
        $prix = trim((string) ($data['prix'] ?? ''));
        $duree = trim((string) ($data['duree'] ?? ''));

        if ($nom === '' || $description === '' || $type === '' || $lieu === '' || $prix === '' || $duree === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez remplir le nom, la description, le type, le lieu, le prix et la durée avant de générer la note AI.'
            ], 400);
        }

        $prompt = "Tu es un évaluateur STRICT d'activités touristiques.

Évalue cette activité et donne uniquement une note sur 10.

Nom : $nom
Description : $description
Type : $type
Lieu : $lieu
Prix : $prix TND
Durée : $duree minutes

Barème STRICT :
- 0 à 3 : activité très mauvaise, incohérente ou vide
- 4 à 5 : activité faible, peu détaillée ou peu attractive
- 6 à 7 : activité correcte mais améliorable
- 8 à 9 : bonne activité claire et intéressante
- 10 : excellente activité exceptionnelle

Règles IMPORTANTES :
- si le nom est court ou générique (ex: test), note max = 3
- si la description contient moins de 15 mots, note max = 4
- si le lieu est vague (ex: x, ville, endroit), pénalité forte
- si le prix est incohérent, pénalité
- si la durée est trop faible ou bizarre, pénalité

Contraintes :
- retourne uniquement un nombre
- entre 0 et 10
- avec au maximum un chiffre après la virgule
- aucun texte";

        $aiResult = $this->callOpenRouter($client, $prompt, 0.4, 'Vianova Activite AI Rating Form');

        if (!$aiResult['success']) {
            return new JsonResponse($aiResult, 500);
        }

        $ratingResult = $this->extractRating($aiResult['content']);

        if (!$ratingResult['success']) {
            return new JsonResponse($ratingResult, 500);
        }

        return new JsonResponse([
            'success' => true,
            'rating' => $ratingResult['rating']
        ]);
    }

    #[Route('/{id}/generate-rating', name: 'app_activite_generate_rating', methods: ['POST'])]
    public function generateRating(
        int $id,
        ActiviteRepository $activiteRepository,
        EntityManagerInterface $entityManager,
        HttpClientInterface $client
    ): JsonResponse {
        $activite = $activiteRepository->find($id);

        if (!$activite) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Activité introuvable.'
            ], 404);
        }

        $nom = trim((string) ($activite->getNom() ?? ''));
        $description = trim((string) ($activite->getDescription() ?? ''));
        $type = trim((string) ($activite->getType() ?? ''));
        $lieu = trim((string) ($activite->getLieu() ?? ''));
        $prix = trim((string) ($activite->getPrix() ?? '0'));
        $duree = trim((string) ($activite->getDuree() ?? '0'));

        $prompt = "Tu es un évaluateur STRICT d'activités touristiques.

Évalue cette activité et donne uniquement une note sur 10.

Nom : $nom
Description : $description
Type : $type
Lieu : $lieu
Prix : $prix TND
Durée : $duree minutes

Barème STRICT :
- 0 à 3 : activité très mauvaise, incohérente ou vide
- 4 à 5 : activité faible, peu détaillée ou peu attractive
- 6 à 7 : activité correcte mais améliorable
- 8 à 9 : bonne activité claire et intéressante
- 10 : excellente activité exceptionnelle

Règles IMPORTANTES :
- si le nom est court ou générique (ex: test), note max = 3
- si la description contient moins de 15 mots, note max = 4
- si le lieu est vague (ex: x, ville, endroit), pénalité forte
- si le prix est incohérent, pénalité
- si la durée est trop faible ou bizarre, pénalité

Contraintes :
- retourne uniquement un nombre
- entre 0 et 10
- avec au maximum un chiffre après la virgule
- aucun texte";

        $aiResult = $this->callOpenRouter($client, $prompt, 0.4, 'Vianova Activite AI Rating');

        if (!$aiResult['success']) {
            return new JsonResponse($aiResult, 500);
        }

        $ratingResult = $this->extractRating($aiResult['content']);

        if (!$ratingResult['success']) {
            return new JsonResponse($ratingResult, 500);
        }

        $rating = $ratingResult['rating'];
        $activite->setAiRating($rating);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'rating' => $rating,
            'message' => 'La note AI a été générée avec succès.'
        ]);
    }

    #[Route('/{id}/chat', name: 'app_activite_chat', methods: ['POST'])]
    public function chatAboutActivity(
        int $id,
        Request $request,
        ActiviteRepository $activiteRepository,
        HttpClientInterface $client
    ): JsonResponse {
        $activite = $activiteRepository->find($id);

        if (!$activite) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Activité introuvable.'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Requête invalide.'
            ], 400);
        }

        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez écrire une question.'
            ], 400);
        }

        $nom = trim((string) ($activite->getNom() ?? ''));
        $description = trim((string) ($activite->getDescription() ?? ''));
        $type = trim((string) ($activite->getType() ?? ''));
        $lieu = trim((string) ($activite->getLieu() ?? ''));
        $prix = trim((string) ($activite->getPrix() ?? ''));
        $duree = trim((string) ($activite->getDuree() ?? ''));
        $aiRating = $activite->getAiRating();

        $prompt = "Tu es l’assistant intelligent de Vianova.

Ta mission :
- répondre en français
- aider l’utilisateur à comprendre cette activité
- répondre de manière claire, utile et naturelle
- rester strictement centré sur les informations de l’activité
- si une information n’est pas disponible, dire honnêtement qu’elle n’est pas précisée
- ne pas inventer des détails non fournis
- ton chaleureux, professionnel et concis
- réponse entre 2 et 5 phrases

Contexte de l’activité :
Nom : $nom
Description : $description
Type : $type
Lieu : $lieu
Prix : $prix TND
Durée : $duree minutes
Note AI : " . ($aiRating !== null ? $aiRating . "/10" : "non disponible") . "

Question de l’utilisateur :
$message";

        $aiResult = $this->callOpenRouter($client, $prompt, 0.6, 'Vianova Activite Chatbot');

        if (!$aiResult['success']) {
            return new JsonResponse($aiResult, 500);
        }

        return new JsonResponse([
            'success' => true,
            'reply' => trim($aiResult['content'])
        ]);
    }

    #[Route('/plan-day', name: 'app_plan_day', methods: ['POST'])]
    public function planDay(
        Request $request,
        HttpClientInterface $client,
        ActiviteRepository $activiteRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Requête invalide.'
            ], 400);
        }

        $message = trim((string) ($data['message'] ?? ''));

        if ($message === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez entrer votre demande.'
            ], 400);
        }

        $activites = $activiteRepository->findAll();

        if (!$activites) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucune activité disponible dans la base de données.'
            ], 404);
        }

        $activitiesText = "";

        foreach ($activites as $activite) {
            $activitiesText .= "- Nom : " . ($activite->getNom() ?? 'N/A') . "\n";
            $activitiesText .= "  Type : " . ($activite->getType() ?? 'N/A') . "\n";
            $activitiesText .= "  Lieu : " . ($activite->getLieu() ?? 'N/A') . "\n";
            $activitiesText .= "  Prix : " . ($activite->getPrix() ?? 'N/A') . " TND\n";
            $activitiesText .= "  Durée : " . ($activite->getDuree() ?? 'N/A') . " min\n";
            $activitiesText .= "  Description : " . ($activite->getDescription() ?? 'Aucune description') . "\n\n";
        }

        $prompt = "Tu es un assistant touristique intelligent pour Vianova.

Ta mission :
- construire un planning de journée basé UNIQUEMENT sur les activités disponibles dans la base
- ne jamais inventer une activité absente de la liste
- choisir les activités les plus pertinentes selon la demande utilisateur
- répondre en français
- respecter si possible le budget, la ville et le temps mentionnés
- si aucune activité ne correspond bien, le dire honnêtement

Voici les activités disponibles dans la base :

$activitiesText

Format STRICT de réponse :

Matin :
- nom de l'activité choisie + courte justification

Après-midi :
- nom de l'activité choisie + courte justification

Soir :
- nom de l'activité choisie + courte justification

Contraintes :
- utiliser seulement les activités listées
- ne pas proposer plus d'une activité par section
- rester clair et court

Demande utilisateur :
$message";

        $aiResult = $this->callOpenRouter($client, $prompt, 0.4, 'Vianova Plan Day DB');

        if (!$aiResult['success']) {
            return new JsonResponse($aiResult, 500);
        }

        return new JsonResponse([
            'success' => true,
            'plan' => trim($aiResult['content'])
        ]);
    }

    #[Route('/{id}/recommend-date', name: 'app_activite_recommend_date', methods: ['POST'])]
    public function recommendDate(
        int $id,
        Request $request,
        ActiviteRepository $activiteRepository
    ): JsonResponse {
        $activite = $activiteRepository->find($id);

        if (!$activite) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Activité introuvable.'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Requête invalide.'
            ], 400);
        }

        $dateString = trim((string) ($data['date'] ?? ''));

        if ($dateString === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez choisir une date.'
            ], 400);
        }

        try {
            $date = new \DateTime($dateString);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Date invalide.'
            ], 400);
        }

        $month = (int) $date->format('n');
        $dayName = strtolower($date->format('l'));
        $type = strtolower(trim((string) ($activite->getType() ?? '')));
        $nom = strtolower(trim((string) ($activite->getNom() ?? '')));

        $textToAnalyze = $type . ' ' . $nom;

        $status = 'Bon choix';
        $recommendedPeriod = 'Toute l’année';
        $advice = 'Cette date convient bien pour cette activité.';
        $bestMonths = [];

        if (
            str_contains($textToAnalyze, 'plong') ||
            str_contains($textToAnalyze, 'plage') ||
            str_contains($textToAnalyze, 'bateau') ||
            str_contains($textToAnalyze, 'jet') ||
            str_contains($textToAnalyze, 'aquatique') ||
            str_contains($textToAnalyze, 'surf')
        ) {
            $bestMonths = [5, 6, 7, 8, 9];
            $recommendedPeriod = 'De mai à septembre';

            if (in_array($month, $bestMonths, true)) {
                $status = 'Bon choix';
                $advice = 'Cette période est idéale pour profiter pleinement des activités aquatiques.';
            } elseif (in_array($month, [4, 10], true)) {
                $status = 'Correct';
                $advice = 'La date choisie peut convenir, mais les conditions sont souvent meilleures entre mai et septembre.';
            } else {
                $status = 'À éviter';
                $advice = 'Cette période est généralement moins adaptée pour une activité aquatique. Les mois chauds sont recommandés.';
            }
        } elseif (
            str_contains($textToAnalyze, 'sport') ||
            str_contains($textToAnalyze, 'randonn') ||
            str_contains($textToAnalyze, 'escalad') ||
            str_contains($textToAnalyze, 'quad') ||
            str_contains($textToAnalyze, 'extérieur') ||
            str_contains($textToAnalyze, 'aventure')
        ) {
            $bestMonths = [3, 4, 5, 10, 11];
            $recommendedPeriod = 'Au printemps et en automne';

            if (in_array($month, $bestMonths, true)) {
                $status = 'Bon choix';
                $advice = 'Cette période est agréable pour les activités sportives extérieures.';
            } elseif (in_array($month, [6, 9], true)) {
                $status = 'Correct';
                $advice = 'La date peut convenir, mais il faut prévoir la chaleur selon l’activité.';
            } else {
                $status = 'À éviter';
                $advice = 'Cette période peut être moins confortable pour une activité sportive extérieure.';
            }
        } elseif (
            str_contains($textToAnalyze, 'culture') ||
            str_contains($textToAnalyze, 'musée') ||
            str_contains($textToAnalyze, 'musee') ||
            str_contains($textToAnalyze, 'visite') ||
            str_contains($textToAnalyze, 'patrimoine')
        ) {
            $recommendedPeriod = 'Toute l’année';

            if (in_array($month, [7, 8], true)) {
                $status = 'Correct';
                $advice = 'La visite est possible, mais elle peut être plus agréable hors forte chaleur.';
            } else {
                $status = 'Bon choix';
                $advice = 'Cette activité culturelle peut se faire confortablement à cette période.';
            }
        }

        if (in_array($dayName, ['saturday', 'sunday'], true) && $status !== 'À éviter') {
            $advice .= ' Le week-end peut être un bon choix pour profiter davantage de l’expérience.';
        }

        return new JsonResponse([
            'success' => true,
            'status' => $status,
            'recommended_period' => $recommendedPeriod,
            'advice' => $advice,
            'chosen_date' => $date->format('d/m/Y'),
            'activity_name' => $activite->getNom(),
        ]);
    }

    #[Route('/reservation/{id}/weather-alert', name: 'app_ai_reservation_weather_alert', methods: ['GET'])]
    public function reservationWeatherAlert(
        int $id,
        ReservationActiviteRepository $reservationActiviteRepository,
        HttpClientInterface $client
    ): JsonResponse {
        $reservation = $reservationActiviteRepository->find($id);

        if (!$reservation) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Réservation introuvable.'
            ], 404);
        }

        $activite = $reservation->getActivite();

        if (!$activite) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Activité introuvable.'
            ], 404);
        }

        $originalLieu = trim((string) ($activite->getLieu() ?? ''));
        $lieu = preg_replace('/\s+/', ' ', $originalLieu);
        $lieu = trim((string) $lieu);

        if (str_contains($lieu, ',')) {
            $parts = array_filter(array_map('trim', explode(',', $lieu)));
            if (!empty($parts)) {
                $lieu = $parts[0];
            }
        }

        $date = $reservation->getDateReservation();

        if ($lieu === '' || !$date) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Lieu ou date manquant.'
            ], 400);
        }

        $today = new \DateTime('today');
        $reservationDay = \DateTime::createFromFormat('Y-m-d', $date->format('Y-m-d'));

        if (!$reservationDay) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Date invalide.'
            ], 400);
        }

        $daysDiff = (int) $today->diff($reservationDay)->format('%r%a');

        if ($daysDiff < 0) {
            return new JsonResponse([
                'success' => true,
                'label' => 'Passée',
                'level' => 'error',
                'advice' => 'La date de cette réservation est déjà passée.'
            ]);
        }

        if ($daysDiff > 16) {
            return new JsonResponse([
                'success' => true,
                'label' => 'Trop loin',
                'level' => 'warning',
                'advice' => 'Les prévisions météo ne sont pas encore disponibles pour cette date.'
            ]);
        }

        try {
            $geoResponse = $client->request('GET', 'https://geocoding-api.open-meteo.com/v1/search', [
                'query' => [
                    'name' => $lieu,
                    'count' => 1,
                    'language' => 'fr',
                    'format' => 'json',
                ],
                'timeout' => 20,
            ]);

            $geoData = $geoResponse->toArray(false);

            if (empty($geoData['results'][0]) && $originalLieu !== $lieu) {
                $geoResponse = $client->request('GET', 'https://geocoding-api.open-meteo.com/v1/search', [
                    'query' => [
                        'name' => $originalLieu,
                        'count' => 1,
                        'language' => 'fr',
                        'format' => 'json',
                    ],
                    'timeout' => 20,
                ]);

                $geoData = $geoResponse->toArray(false);
            }

            if (empty($geoData['results'][0])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible de localiser ce lieu.',
                    'lieu_envoye' => $lieu,
                    'lieu_original' => $originalLieu,
                    'geo_data' => $geoData
                ], 404);
            }

            $latitude = $geoData['results'][0]['latitude'] ?? null;
            $longitude = $geoData['results'][0]['longitude'] ?? null;

            if ($latitude === null || $longitude === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Coordonnées météo introuvables.'
                ], 404);
            }

            $dateString = $reservationDay->format('Y-m-d');

            $weatherResponse = $client->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'daily' => 'weathercode,temperature_2m_max,temperature_2m_min,precipitation_sum',
                    'timezone' => 'auto',
                    'forecast_days' => 16,
                ],
                'timeout' => 20,
            ]);

            $weatherData = $weatherResponse->toArray(false);

            if (empty($weatherData['daily']['time']) || !is_array($weatherData['daily']['time'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Réponse météo invalide.'
                ], 404);
            }

            $index = array_search($dateString, $weatherData['daily']['time'], true);

            if ($index === false) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucune météo disponible pour la date ' . $dateString
                ], 404);
            }

            $weatherCode = (int) ($weatherData['daily']['weathercode'][$index] ?? -1);
            $tempMax = $weatherData['daily']['temperature_2m_max'][$index] ?? null;
            $tempMin = $weatherData['daily']['temperature_2m_min'][$index] ?? null;
            $precipitation = $weatherData['daily']['precipitation_sum'][$index] ?? 0;

            $typeText = strtolower(trim((string) ($activite->getType() ?? '')));
            $nomText = strtolower(trim((string) ($activite->getNom() ?? '')));
            $activityText = $typeText . ' ' . $nomText;

            $isOutdoor = (
                str_contains($activityText, 'sport') ||
                str_contains($activityText, 'randonn') ||
                str_contains($activityText, 'escalad') ||
                str_contains($activityText, 'quad') ||
                str_contains($activityText, 'aventure') ||
                str_contains($activityText, 'plage') ||
                str_contains($activityText, 'bateau') ||
                str_contains($activityText, 'extérieur') ||
                str_contains($activityText, 'exterieur')
            );

            $label = 'Favorable';
            $level = 'good';
            $advice = 'Les conditions météo semblent convenir à cette réservation.';

            $isRainy = $precipitation > 3;
            $isStorm = in_array($weatherCode, [95, 96, 99], true);
            $isSnow = in_array($weatherCode, [71, 73, 75, 77, 85, 86], true);
            $isBadCode = $weatherCode >= 51;

            if ($isOutdoor) {
                if ($isStorm || $isSnow || $isRainy || $isBadCode) {
                    $label = 'Défavorable';
                    $level = 'bad';
                    $advice = 'Cette activité extérieure risque d’être impactée par la météo.';
                } elseif (($tempMax !== null && $tempMax >= 35) || $precipitation > 0) {
                    $label = 'À surveiller';
                    $level = 'warning';
                    $advice = 'Conditions possibles, mais la météo peut réduire le confort.';
                }
            } else {
                if ($isStorm || $isSnow) {
                    $label = 'À surveiller';
                    $level = 'warning';
                    $advice = 'Activité possible, mais les conditions de déplacement sont à surveiller.';
                }
            }

            return new JsonResponse([
                'success' => true,
                'label' => $label,
                'level' => $level,
                'advice' => $advice,
                'temperature_max' => $tempMax,
                'temperature_min' => $tempMin,
                'precipitation' => $precipitation,
                'date' => $reservationDay->format('d/m/Y'),
                'location' => $originalLieu,
                'weather_code' => $weatherCode
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur météo : ' . $e->getMessage()
            ], 500);
        }
    }

    private function callOpenRouter(
        HttpClientInterface $client,
        string $prompt,
        float $temperature,
        string $title
    ): array {
        $apiKey = $_SERVER['OPENROUTER_API_KEY'] ?? $_ENV['OPENROUTER_API_KEY'] ?? getenv('OPENROUTER_API_KEY');

        if (!$apiKey) {
            return [
                'success' => false,
                'message' => 'La clé API OpenRouter est introuvable dans le fichier .env.'
            ];
        }

        try {
            $response = $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'http://127.0.0.1:8000',
                    'X-Title' => $title,
                ],
                'json' => [
                    'model' => 'openai/gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ]
                    ],
                    'temperature' => $temperature,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);

            if ($statusCode >= 400) {
                return [
                    'success' => false,
                    'message' => 'Erreur API OpenRouter : ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ];
            }

            if (isset($result['error'])) {
                return [
                    'success' => false,
                    'message' => 'Erreur API OpenRouter : ' . json_encode($result['error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ];
            }

            $content = trim((string) ($result['choices'][0]['message']['content'] ?? ''));

            if ($content === '') {
                return [
                    'success' => false,
                    'message' => 'Aucune réponse valide n’a été retournée par OpenRouter : ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ];
            }

            return [
                'success' => true,
                'content' => $content
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erreur de connexion à l’API : ' . $e->getMessage()
            ];
        }
    }

    private function extractRating(string $content): array
    {
        preg_match('/\d+([.,]\d+)?/', $content, $matches);
        $rawRating = $matches[0] ?? null;

        if ($rawRating === null) {
            return [
                'success' => false,
                'message' => 'Réponse AI invalide : ' . $content
            ];
        }

        $rating = (float) str_replace(',', '.', $rawRating);
        $rating = max(0, min(10, $rating));
        $rating = round($rating, 1);

        return [
            'success' => true,
            'rating' => $rating
        ];
    }
}