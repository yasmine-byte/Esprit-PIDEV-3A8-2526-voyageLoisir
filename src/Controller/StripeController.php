<?php
namespace App\Controller;

use App\Entity\Reservation;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    #[Route('/reservation/{id}/checkout', name: 'app_stripe_checkout', methods: ['POST'])]
    public function checkout(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $reservation = $em->getRepository(Reservation::class)->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }

        // ✅ Stocker le token FCM en session si fourni
        $fcmToken = $request->request->get('fcm_token')
            ?? $request->getSession()->get('fcm_token')
            ?? $reservation->getFcmToken();
        if ($fcmToken) {
            $reservation->setFcmToken($fcmToken);
            $em->flush();
            $request->getSession()->set('fcm_token_' . $id, $fcmToken);
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $totalCentimes = (int) round((float) $reservation->getTotal() * 100);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Réservation - ' . ($reservation->getHebergement() ? $reservation->getHebergement()->getDescription() : 'Hébergement'),
                        'description' => $reservation->getNbNuits() . ' nuit(s) du ' . $reservation->getDateDebut()->format('d/m/Y') . ' au ' . $reservation->getDateFin()->format('d/m/Y'),
                    ],
                    'unit_amount' => $totalCentimes,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $urlGenerator->generate('app_stripe_success', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url'  => $urlGenerator->generate('app_stripe_cancel',  ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'customer_email' => $reservation->getClientEmail(),
        ]);

        return $this->redirect($session->url);
    }

    #[Route('/reservation/{id}/success', name: 'app_stripe_success', methods: ['GET'])]
    public function success(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $reservation = $em->getRepository(Reservation::class)->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }

        // Mettre à jour le statut
        $reservation->setStatut('confirmee');
        $em->flush();

        // ✅ Envoyer notification FCM si token disponible en session
        $fcmToken = $request->getSession()->get('fcm_token_' . $id) ?? $reservation->getFcmToken();
        if ($fcmToken) {
            $this->notificationService->notifyReservationConfirmee(
                $fcmToken,
                $reservation->getClientNom() ?? 'Client',
                $reservation->getHebergement()?->getAdresse() ?? 'votre hébergement'
            );
            $this->notificationService->notifyPaiementRecu(
                $fcmToken,
                $reservation->getClientNom() ?? 'Client',
                $reservation->getTotal() ?? '0'
            );
            // Nettoyer le token de la session
            $request->getSession()->remove('fcm_token_' . $id);
        }

        // Envoyer email de confirmation
        try {
            $email = (new Email())
                ->from('ferjanimehdi02@gmail.com')
                ->to($reservation->getClientEmail())
                ->subject('✅ Confirmation de votre réservation - Vianova')
                ->html($this->renderView('emails/reservation_confirmation.html.twig', [
                    'reservation' => $reservation,
                ]));
            $mailer->send($email);
        } catch (\Exception $e) {
            // Email non bloquant
        }

        return $this->render('reservation/success.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/reservation/{id}/cancel', name: 'app_stripe_cancel', methods: ['GET'])]
    public function cancel(int $id, EntityManagerInterface $em): Response
    {
        $reservation = $em->getRepository(Reservation::class)->find($id);
        return $this->render('reservation/cancel.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}
