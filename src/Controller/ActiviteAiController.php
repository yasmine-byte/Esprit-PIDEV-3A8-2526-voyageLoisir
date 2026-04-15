<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
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

        $nom = trim($data['nom'] ?? '');
        $type = trim($data['type'] ?? '');
        $lieu = trim($data['lieu'] ?? '');
        $duree = trim((string) ($data['duree'] ?? ''));

        if ($nom === '' || $type === '' || $lieu === '' || $duree === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez remplir le nom, le type, le lieu et la durée avant de générer la description.'
            ], 400);
        }

        $apiKey = $_ENV['OPENROUTER_API_KEY'] ?? null;

        if (!$apiKey) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La clé API OpenRouter est introuvable dans le fichier .env.'
            ], 500);
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

        try {
            $response = $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'http://127.0.0.1:8000',
                    'X-Title' => 'Vianova Activite AI',
                ],
                'json' => [
                    'model' => 'openai/gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ]
                    ],
                    'temperature' => 0.7,
                ],
            ]);

            $result = $response->toArray(false);
            $description = $result['choices'][0]['message']['content'] ?? null;

            if (!$description) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucune description n’a été générée.'
                ], 500);
            }

            return new JsonResponse([
                'success' => true,
                'description' => trim($description)
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l’appel API : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/generate-rating-from-form', name: 'app_activite_generate_rating_from_form', methods: ['POST'])]
    public function generateRatingFromForm(Request $request, HttpClientInterface $client): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $nom = trim($data['nom'] ?? '');
        $description = trim($data['description'] ?? '');
        $type = trim($data['type'] ?? '');
        $lieu = trim($data['lieu'] ?? '');
        $prix = trim((string) ($data['prix'] ?? ''));
        $duree = trim((string) ($data['duree'] ?? ''));

        if ($nom === '' || $description === '' || $type === '' || $lieu === '' || $prix === '' || $duree === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Veuillez remplir le nom, la description, le type, le lieu, le prix et la durée avant de générer la note AI.'
            ], 400);
        }

        $apiKey = $_ENV['OPENROUTER_API_KEY'] ?? null;

        if (!$apiKey) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La clé API OpenRouter est introuvable dans le fichier .env.'
            ], 500);
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

        try {
            $response = $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'http://127.0.0.1:8000',
                    'X-Title' => 'Vianova Activite AI Rating Form',
                ],
                'json' => [
                    'model' => 'openai/gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ]
                    ],
                    'temperature' => 0.4,
                ],
            ]);

            $result = $response->toArray(false);
            $content = trim($result['choices'][0]['message']['content'] ?? '');

            if ($content === '') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucune note n’a été générée.'
                ], 500);
            }

            preg_match('/\d+([.,]\d+)?/', $content, $matches);
            $rawRating = $matches[0] ?? null;

            if ($rawRating === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Réponse AI invalide : ' . $content
                ], 500);
            }

            $rating = (float) str_replace(',', '.', $rawRating);
            $rating = max(0, min(10, $rating));
            $rating = round($rating, 1);

            return new JsonResponse([
                'success' => true,
                'rating' => $rating
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l’appel API : ' . $e->getMessage()
            ], 500);
        }
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

        $apiKey = $_ENV['OPENROUTER_API_KEY'] ?? null;

        if (!$apiKey) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La clé API OpenRouter est introuvable dans le fichier .env.'
            ], 500);
        }

        $nom = $activite->getNom() ?? '';
        $description = $activite->getDescription() ?? '';
        $type = $activite->getType() ?? '';
        $lieu = $activite->getLieu() ?? '';
        $prix = $activite->getPrix() ?? 0;
        $duree = $activite->getDuree() ?? 0;

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

        try {
            $response = $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'http://127.0.0.1:8000',
                    'X-Title' => 'Vianova Activite AI Rating',
                ],
                'json' => [
                    'model' => 'openai/gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ]
                    ],
                    'temperature' => 0.4,
                ],
            ]);

            $result = $response->toArray(false);
            $content = trim($result['choices'][0]['message']['content'] ?? '');

            if ($content === '') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucune note n’a été générée.'
                ], 500);
            }

            preg_match('/\d+([.,]\d+)?/', $content, $matches);
            $rawRating = $matches[0] ?? null;

            if ($rawRating === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Réponse AI invalide : ' . $content
                ], 500);
            }

            $rating = (float) str_replace(',', '.', $rawRating);
            $rating = max(0, min(10, $rating));
            $rating = round($rating, 1);

            $activite->setAiRating($rating);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'rating' => $rating,
                'message' => 'La note AI a été générée avec succès.'
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l’appel API : ' . $e->getMessage()
            ], 500);
        }
    }
}