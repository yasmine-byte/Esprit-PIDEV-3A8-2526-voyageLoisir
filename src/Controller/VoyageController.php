<?php
namespace App\Controller;
use App\Entity\Voyage;
use App\Form\VoyageType;
use App\Repository\VoyageRepository;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route("/voyage")]
final class VoyageController extends AbstractController
{
    #[Route(name: "app_voyage_index", methods: ["GET"])]
    public function index(Request $request, VoyageRepository $voyageRepository): Response
    {
        $search = $request->query->get("search", "");
        $tri    = $request->query->get("tri", "id");
        $ordre  = $request->query->get("ordre", "ASC");
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
            if ($destination) {
                $voyage->setDestination($destination);
            }
        }

        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user) {
                $voyage->setCreatedBy($user);
            }
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

    #[Route("/{id}", name: "app_voyage_show", methods: ["GET"])]
    public function show(Voyage $voyage): Response
    {
        return $this->render("voyage/show.html.twig", [
            "voyage" => $voyage,
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
        return $this->render("voyage/edit.html.twig", [
            "voyage" => $voyage,
            "form"   => $form,
        ]);
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
    public function reserve(Request $request, Voyage $voyage, EntityManagerInterface $entityManager): Response
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
        }

        $destination = $voyage->getDestination();
        if ($destination) {
            return $this->redirectToRoute('app_destination_detail', ['id' => $destination->getId()]);
        }
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
        Request $request,
        Voyage $voyage,
        int $userId,
        EntityManagerInterface $entityManager,
        \App\Repository\UsersRepository $usersRepository
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
        }

        return $this->redirectToRoute('app_voyage_show', ['id' => $voyage->getId()]);
    }

    #[Route("/{id}/checkout", name: "app_payment_checkout", methods: ["POST"])]
    public function checkout(Voyage $voyage): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        if (!$voyage->isReservedByUser($user)) {
            $this->addFlash('error', 'Vous n\'avez pas réservé ce voyage.');
            return $this->redirectToRoute('front_profile');
        }

        $destination = $voyage->getDestination();
        if ($destination && !$destination->isStatut()) {
            $this->addFlash('error', 'Cette destination est inactive, le paiement est impossible.');
            return $this->redirectToRoute('front_profile');
        }

        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? '');

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency'     => 'eur',
                    'unit_amount'  => (int)($voyage->getPrix() * 100),
                    'product_data' => [
                        'name'        => 'Voyage ' . ($voyage->getPointDepart() ?? '') . ' → ' . ($voyage->getPointArrivee() ?? ''),
                        'description' => 'Du ' . ($voyage->getDateDepart()?->format('d/m/Y') ?? '-')
                                       . ' au ' . ($voyage->getDateArrivee()?->format('d/m/Y') ?? '-'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => $this->generateUrl('app_payment_success', ['id' => $voyage->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url'  => $this->generateUrl('app_payment_cancel',  ['id' => $voyage->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url, 303);
    }

    #[Route("/{id}/payment-success", name: "app_payment_success")]
    public function paymentSuccess(Voyage $voyage, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if ($user && $voyage->isReservedByUser($user)) {
            $voyage->setPaid(true);
            $entityManager->flush();
        }

        $this->addFlash('success', '✅ Paiement effectué avec succès !');
        return $this->redirectToRoute('front_profile');
    }

    #[Route("/{id}/payment-cancel", name: "app_payment_cancel")]
    public function paymentCancel(Voyage $voyage): Response
    {
        $this->addFlash('error', '❌ Paiement annulé.');
        return $this->redirectToRoute('front_profile');
    }
}