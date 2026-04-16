<?php
namespace App\Controller;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends AbstractController
{
    #[Route('/reservation/{id}/checkout', name: 'app_stripe_checkout', methods: ['POST'])]
    public function checkout(
        int $id,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $reservation = $em->getRepository(Reservation::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
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
            'cancel_url' => $urlGenerator->generate('app_stripe_cancel', ['id' => $reservation->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'customer_email' => $reservation->getClientEmail(),
        ]);

        return $this->redirect($session->url);
    }

    #[Route('/reservation/{id}/success', name: 'app_stripe_success', methods: ['GET'])]
    public function success(
        int $id,
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

        // Envoyer email de confirmation
        $email = (new Email())
            ->from('ferjanimehdi02@gmail.com')
            ->to($reservation->getClientEmail())
            ->subject('✅ Confirmation de votre réservation - Vianova')
            ->html($this->renderView('emails/reservation_confirmation.html.twig', [
                'reservation' => $reservation,
            ]));

        $mailer->send($email);

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