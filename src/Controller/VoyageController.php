<?php
namespace App\Controller;

use App\Entity\Voyage;
use App\Entity\Users;
use App\Form\VoyageType;
use App\Repository\VoyageRepository;
use App\Repository\DestinationRepository;
use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;


#[Route("/voyage")]
final class VoyageController extends AbstractController
{
    #[Route(name: "app_voyage_index", methods: ["GET"])]
    public function index(Request $request, VoyageRepository $voyageRepository): Response
    {
        $search  = $request->query->get("search", "");
        $tri     = $request->query->get("tri", "id");
        $ordre   = $request->query->get("ordre", "ASC");
        $voyages = $voyageRepository->findByFilters($search, $tri, $ordre);
        return $this->render("voyage/index.html.twig", [
            "voyages" => $voyages,
            "search"  => $search,
            "tri"     => $tri,
            "ordre"   => $ordre,
        ]);
    }

    #[Route("/new", name: "app_voyage_new", methods: ["GET", "POST"])]
    public function new(Request $request, EntityManagerInterface $entityManager, DestinationRepository $destRepo): Response
    {
        $voyage = new Voyage();
        $destinationId = $request->query->getInt('destination_id');
        if ($destinationId) {
            $destination = $destRepo->find($destinationId);
            if ($destination) $voyage->setDestination($destination);
        }
        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user) $voyage->setCreatedBy($user);
            $entityManager->persist($voyage);
            $entityManager->flush();
            return $this->redirectToRoute("app_voyage_index", [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("voyage/new.html.twig", [
            "voyage"         => $voyage,
            "form"           => $form,
            "destination_id" => $destinationId,
        ]);
    }

    #[Route('/expires-json', name: 'app_voyage_expires_json', methods: ['GET'])]
    public function expiresJson(
        VoyageRepository $voyageRepository,
        EntityManagerInterface $entityManager,
        TelegramService $telegram
    ): Response {
        $tous    = $voyageRepository->findAll();
        $expires = [];
        $now     = new \DateTime();

        foreach ($tous as $voyage) {
            if (!$voyage->getDateDepart() || $voyage->getDateDepart() >= $now) continue;

            $usersAnnules = [];
            foreach ($voyage->getReservedByUsers() as $user) {
                $isPaid = method_exists($voyage, 'isPaid') ? $voyage->isPaid() : false;
                if (!$isPaid) $usersAnnules[] = $user;
            }

            foreach ($usersAnnules as $user) {
                $voyage->removeReservation($user);
                if ($user instanceof Users && $user->getTelegramChatId()) {
                    $destNom = $voyage->getDestination() ? $voyage->getDestination()->getNom() : 'N/A';
                    $telegram->send(
                        $user->getTelegramChatId(),
                        "⚠️ <b>VoyageLoisir — Réservation annulée</b>\n\n"
                        . "Voyage <b>" . ($voyage->getPointDepart() ?? '?') . " → " . ($voyage->getPointArrivee() ?? '?') . "</b>"
                        . " (destination : <b>{$destNom}</b>) annulée automatiquement.\n\n"
                        . "📅 Date dépassée : <b>" . $voyage->getDateDepart()->format('d/m/Y') . "</b>\n"
                        . "💳 Raison : paiement non effectué.\n— <i>Vianova Travel Agency</i>"
                    );
                }
            }

            if (!empty($usersAnnules)) $entityManager->flush();

            $expires[] = [
                'id'          => $voyage->getId(),
                'pointDepart' => $voyage->getPointDepart()  ?? '?',
                'pointArrivee'=> $voyage->getPointArrivee() ?? '?',
                'dateDepart'  => $voyage->getDateDepart()->format('d/m/Y'),
                'destination' => $voyage->getDestination()?->getNom() ?? '',
                'annulations' => count($usersAnnules),
            ];
        }

        return $this->json(['voyages' => $expires]);
    }

   #[Route("/{id}", name: "app_voyage_show", methods: ["GET"])]
public function show(Voyage $voyage, EntityManagerInterface $entityManager): Response
{
    $rows = $entityManager->getConnection()->fetchAllAssociative(
        'SELECT users_id FROM voyage_reservations WHERE voyage_id = :vid AND paid = 1',
        ['vid' => $voyage->getId()]
    );
    $paidUserIds = array_column($rows, 'users_id');

    return $this->render("voyage/show.html.twig", [
        "voyage"      => $voyage,
        "paidUserIds" => $paidUserIds,
    ]);
}

    #[Route("/{id}/edit", name: "app_voyage_edit", methods: ["GET", "POST"])]
    public function edit(Request $request, Voyage $voyage, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute("app_voyage_index", [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("voyage/edit.html.twig", ["voyage" => $voyage, "form" => $form]);
    }

    #[Route("/{id}/annuler", name: "app_voyage_cancel", methods: ["POST"])]
    public function cancel(Request $request, Voyage $voyage, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || !$voyage->isReservedByUser($user)) {
            $this->addFlash('error', 'Action non autorisée.');
            return $this->redirectToRoute('front_profile');
        }
        if (!$this->isCsrfTokenValid('cancel' . $voyage->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('front_profile');
        }
        $voyage->removeReservation($user);
        $entityManager->flush();
        $this->addFlash('success', 'Réservation annulée.');
        return $this->redirectToRoute('front_profile');
    }

    #[Route("/{id}/reserver", name: "app_voyage_reserve", methods: ["POST"])]
    public function reserve(Request $request, Voyage $voyage, EntityManagerInterface $entityManager, TelegramService $telegram): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver.');
            return $this->redirectToRoute('app_home');
        }
        if (!$this->isCsrfTokenValid('reserve' . $voyage->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_destinations');
        }
        if ($voyage->isReservedByUser($user)) {
            $this->addFlash('error', 'Vous avez déjà réservé ce voyage.');
        } else {
            $voyage->addReservation($user);
            $entityManager->flush();
            $this->addFlash('success', 'Voyage réservé avec succès !');
            if ($user instanceof Users && $user->getTelegramChatId()) {
                $dest    = $voyage->getDestination();
                $destNom = $dest ? $dest->getNom() . ' (' . $dest->getPays() . ')' : 'N/A';
                $telegram->send($user->getTelegramChatId(),
                    "✅ <b>VoyageLoisir — Réservation confirmée !</b>\n\n"
                    . "📍 Destination : <b>{$destNom}</b>\n"
                    . "📅 Départ : " . ($voyage->getDateDepart()?->format('d/m/Y') ?? 'N/A') . "\n"
                    . "📅 Retour : " . ($voyage->getDateArrivee()?->format('d/m/Y') ?? 'N/A') . "\n"
                    . "💶 Prix : " . ($voyage->getPrix() ? number_format($voyage->getPrix(), 2, ',', ' ') . ' €' : 'N/A') . "\n\nMerci !"
                );
            }
        }
        $destination = $voyage->getDestination();
        if ($destination) return $this->redirectToRoute('app_destination_detail', ['id' => $destination->getId()]);
        return $this->redirectToRoute('app_destinations');
    }

    #[Route("/{id}", name: "app_voyage_delete", methods: ["POST"])]
    public function delete(Request $request, Voyage $voyage, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid("delete" . $voyage->getId(), $request->getPayload()->getString("_token"))) {
            $entityManager->remove($voyage);
            $entityManager->flush();
        }
        return $this->redirectToRoute("app_voyage_index", [], Response::HTTP_SEE_OTHER);
    }

    #[Route("/{id}/admin-cancel/{userId}", name: "app_voyage_admin_cancel", methods: ["POST"])]
    public function adminCancel(
        Request $request, Voyage $voyage, int $userId,
        EntityManagerInterface $entityManager,
        \App\Repository\UsersRepository $usersRepository,
        TelegramService $telegram
    ): Response {
        if (!$this->isCsrfTokenValid('admin_cancel' . $voyage->getId() . $userId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_voyage_show', ['id' => $voyage->getId()]);
        }
        $user = $usersRepository->find($userId);
        if ($user) {
            $voyage->removeReservation($user);
            $entityManager->flush();
            $this->addFlash('success', 'Réservation annulée.');
            $chatId = method_exists($user, 'getTelegramChatId') ? $user->getTelegramChatId() : null;
            if ($chatId) {
                $telegram->notifyReservationCancelled(
                    $chatId,
                    $voyage->getPointDepart() ?? 'N/A',
                    $voyage->getPointArrivee() ?? 'N/A',
                    $voyage->getDateDepart() ? $voyage->getDateDepart()->format('d/m/Y') : 'N/A'
                );
            }
        }
        return $this->redirectToRoute('app_voyage_show', ['id' => $voyage->getId()]);
    }

    // ÉTAPE 1 — Envoyer l'email de confirmation avec lien vers la page de paiement
#[Route("/{id}/checkout", name: "app_payment_checkout", methods: ["GET"])]
public function checkout(Voyage $voyage): Response
{
    $user = $this->getUser();
    if (!$user) return $this->redirectToRoute('app_home');

    // ✅ Remise -20% pour destinations Hiver
    $prix = $voyage->getPrix();
    $destination = $voyage->getDestination();
    $remise = false;
    if ($destination && $destination->getMeilleureSaison() === 'Hiver') {
        $prix = $prix * 0.80;
        $remise = true;
    }

    $nomProduit = 'Voyage ' . ($voyage->getPointDepart() ?? '') . ' → ' . ($voyage->getPointArrivee() ?? '');
    if ($remise) {
        $nomProduit .= ' ❄️ -20% Offre Hiver';
    }

    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency'    => 'eur',
                'unit_amount' => (int)($prix * 100),
                'product_data' => [
                    'name' => $nomProduit,
                ],
            ],
            'quantity' => 1,
        ]],
        'mode'        => 'payment',
        'success_url' => $this->generateUrl('app_payment_success', ['id' => $voyage->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        'cancel_url'  => $this->generateUrl('app_payment_cancel',  ['id' => $voyage->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
    ]);

    return $this->redirect($session->url);
}
    // ÉTAPE 2 — Page de paiement (depuis le lien email) → crée session Stripe → redirige vers Stripe
    

    // ✅ SUCCÈS — isPaid = true + email succès
    #[Route("/{id}/payment-success", name: "app_payment_success")]
public function paymentSuccess(Voyage $voyage, EntityManagerInterface $entityManager, TelegramService $telegram): Response
    {
        $user = $this->getUser();
        if ($user instanceof Users) {
            // ✅ Marquer SEULEMENT la réservation de cet user comme payée
            $entityManager->getConnection()->executeStatement(
                'UPDATE voyage_reservations SET paid = 1 WHERE voyage_id = :vid AND users_id = :uid',
                ['vid' => $voyage->getId(), 'uid' => $user->getId()]
            );
            $entityManager->getConnection()->executeStatement(
    'UPDATE voyage_reservations SET paid = 1 WHERE voyage_id = :vid AND users_id = :uid',
    ['vid' => $voyage->getId(), 'uid' => $user->getId()]
);

// ✅ Ajoute ça juste après :
if ($user->getTelegramChatId()) {
    $dest    = $voyage->getDestination();
    $destNom = $dest ? $dest->getNom() . ' (' . $dest->getPays() . ')' : 'N/A';
    $telegram->send(
        $user->getTelegramChatId(),
        "✅ <b>Vianova — Paiement confirmé !</b>\n\n"
        . "📍 Destination : <b>{$destNom}</b>\n"
        . "🛫 Trajet : <b>" . ($voyage->getPointDepart() ?? 'N/A') . " → " . ($voyage->getPointArrivee() ?? 'N/A') . "</b>\n"
        . "📅 Départ : " . ($voyage->getDateDepart()?->format('d/m/Y') ?? 'N/A') . "\n"
        . "📅 Retour : " . ($voyage->getDateArrivee()?->format('d/m/Y') ?? 'N/A') . "\n"
        . "💶 Montant payé : <b>" . number_format($voyage->getPrix(), 2, ',', ' ') . " €</b>\n\n"
        . "🌍 Bon voyage avec <b>Vianova Travel Agency</b> !"
    );
}

            
        }

        $this->addFlash('success', '✅ Paiement effectué ! Un email de confirmation vous a été envoyé.');
        return $this->redirectToRoute('front_profile');
    }
    #[Route("/{id}/payment-cancel", name: "app_payment_cancel")]
    public function paymentCancel(Voyage $voyage): Response
    {
        $this->addFlash('error', '❌ Paiement annulé.');
        return $this->redirectToRoute('front_profile');
    }
}