<?php

namespace App\Service;

use App\Entity\Avis;
use App\Entity\Reclamation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * Service d'envoi d'emails pour le module Réclamations et Avis — Villa Agency.
 *
 * Méthodes disponibles :
 *   - sendConfirmationAvis()         → confirmation au client après soumission d'un avis
 *   - sendConfirmationReclamation()  → confirmation au client après soumission d'une réclamation
 *   - sendReponseAvis()              → notifie le client que l'admin a répondu à son avis
 *   - sendReponseReclamation()       → notifie le client que l'admin a répondu à sa réclamation
 *   - sendAlertAdminAvisNegatif()    → alerte l'admin en cas d'avis négatif (note ≤ 2)
 */
class MailerService
{
    /** Adresse expéditeur officielle */
    private const FROM_EMAIL = 'rayenhafian72@gmail.com';
    private const FROM_NAME  = 'Villa Agency';

    /** Email de l'administrateur (destinataire des alertes) */
    private const ADMIN_EMAIL = 'rayenhafian72@gmail.com';

    public function __construct(private readonly MailerInterface $mailer) {}

    // ─────────────────────────────────────────────────────────────
    // 1. Confirmation d'avis
    // ─────────────────────────────────────────────────────────────

    /**
     * Envoie un email de confirmation au client après la soumission d'un avis.
     * L'email du client est fictif : user{id}@vianova.tn
     */
    public function sendConfirmationAvis(Avis $avis): void
    {
        $to    = $this->getUserEmail($avis->getUserId());
        $stars = str_repeat('★', $avis->getNbEtoiles()) . str_repeat('☆', 5 - $avis->getNbEtoiles());

        $html = $this->buildLayout(
            '✅ Votre avis a bien été reçu',
            'Bonjour,<br>Votre avis a été soumis avec succès.',
            [
                'Note'      => $stars . '&nbsp;&nbsp;<small style="color:#999;">(' . $avis->getNbEtoiles() . '/5)</small>',
                'Catégorie' => htmlspecialchars($avis->getType() ? $avis->getType()->getNom() : '—'),
                'Contenu'   => '<em style="color:#555;">' . nl2br(htmlspecialchars($avis->getContenu())) . '</em>',
                'Statut'    => '<span style="color:#e65100; font-weight:700;">⏳ En attente de validation</span>',
            ],
            '<p style="color:#555; margin-top:16px;">Notre équipe examinera votre avis dans les <strong>48 heures</strong>. Merci pour votre confiance !</p>'
        );

        $this->send($to, '✅ Votre avis a bien été reçu - Villa Agency', $html);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. Confirmation de réclamation
    // ─────────────────────────────────────────────────────────────

    /**
     * Envoie un email de confirmation au client après la soumission d'une réclamation.
     */
    public function sendConfirmationReclamation(Reclamation $reclamation): void
    {
        $to = $this->getUserEmail($reclamation->getUserId());

        // Numéro de suivi unique : REC-{id}-{date}
        $tracking = 'REC-' . $reclamation->getId() . '-'
                  . ($reclamation->getDateCreation()
                        ? $reclamation->getDateCreation()->format('Ymd')
                        : date('Ymd'));

        $html = $this->buildLayout(
            '📋 Réclamation reçue - Villa Agency',
            'Votre réclamation a bien été enregistrée. Voici un récapitulatif :',
            [
                'Numéro de suivi' => '<strong style="color:#f35525; font-size:1.1rem;">' . $tracking . '</strong>',
                'Titre'           => htmlspecialchars($reclamation->getTitre()),
                'Catégorie'       => htmlspecialchars($reclamation->getType() ? $reclamation->getType()->getNom() : '—'),
                'Priorité'        => htmlspecialchars($reclamation->getPriorite()),
                'Statut'          => '<span style="color:#e65100;">⏳ En attente de traitement</span>',
            ],
            '<p style="color:#555; margin-top:16px;">Notre équipe traitera votre demande selon la priorité assignée. Conservez votre numéro de suivi.</p>'
        );

        $this->send($to, '📋 Réclamation #' . $reclamation->getId() . ' reçue - Villa Agency', $html);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. Réponse admin à un avis
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifie le client que l'administrateur a répondu à son avis.
     */
    public function sendReponseAvis(Avis $avis): void
    {
        $to = $this->getUserEmail($avis->getUserId());

        $body  = '<p style="color:#555; font-size:1rem;">L\'administrateur de Villa Agency a répondu à votre avis.</p>';

        // Bloc : avis original
        $body .= '<div style="background:#f9f9f9; border-left:4px solid #ccc; padding:12px 16px; margin:16px 0; border-radius:0 8px 8px 0;">';
        $body .= '<small style="color:#999; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Votre avis original</small><br><br>';
        $body .= '<span style="color:#555; font-style:italic;">' . nl2br(htmlspecialchars($avis->getContenu())) . '</span>';
        $body .= '</div>';

        // Bloc : réponse admin (vert)
        $body .= '<div style="background:#f0fff4; border:1px solid #b2dfdb; border-radius:10px; padding:16px 20px; margin:16px 0;">';
        $body .= '<small style="color:#28a745; font-weight:700; text-transform:uppercase; letter-spacing:1px;">💬 Réponse de l\'administrateur</small><br><br>';
        $body .= '<span style="color:#155724; font-size:1rem;">' . nl2br(htmlspecialchars($avis->getReponse() ?? '')) . '</span>';
        $body .= '</div>';

        $html = $this->buildLayoutRaw('💬 Réponse à votre avis', $body);
        $this->send($to, '💬 L\'admin a répondu à votre avis - Villa Agency', $html);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. Réponse admin à une réclamation
    // ─────────────────────────────────────────────────────────────

    /**
     * Notifie le client que l'administrateur a répondu à sa réclamation.
     */
    public function sendReponseReclamation(Reclamation $reclamation): void
    {
        $to = $this->getUserEmail($reclamation->getUserId());

        $body  = '<p style="color:#555;">Bonne nouvelle ! Votre réclamation <strong>'
               . htmlspecialchars($reclamation->getTitre())
               . '</strong> a reçu une réponse.</p>';

        // Réponse de l'admin (vert)
        $body .= '<div style="background:#f0fff4; border:1px solid #b2dfdb; border-radius:10px; padding:16px 20px; margin:16px 0;">';
        $body .= '<small style="color:#28a745; font-weight:700; text-transform:uppercase; letter-spacing:1px;">💬 Réponse de l\'administrateur</small><br><br>';
        $body .= '<span style="color:#155724; font-size:1rem;">' . nl2br(htmlspecialchars($reclamation->getReponse() ?? '')) . '</span>';
        $body .= '</div>';

        // Nouveau statut
        $body .= '<p style="color:#555; margin-top:12px;">Nouveau statut de votre réclamation : '
               . '<strong style="color:#f35525;">' . htmlspecialchars($reclamation->getStatut()) . '</strong></p>';

        $html = $this->buildLayoutRaw('💬 Réponse à votre réclamation #' . $reclamation->getId(), $body);
        $this->send($to, '💬 Réponse à votre réclamation #' . $reclamation->getId() . ' - Villa Agency', $html);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. Alerte admin — avis négatif
    // ─────────────────────────────────────────────────────────────

    /**
     * Envoie une alerte à l'administrateur quand un avis négatif (note ≤ 2) est soumis.
     */
    public function sendAlertAdminAvisNegatif(Avis $avis): void
    {
        $stars = str_repeat('★', $avis->getNbEtoiles()) . str_repeat('☆', 5 - $avis->getNbEtoiles());

        $body  = '<div style="background:#fff3cd; border:1px solid #ffc107; border-radius:10px; padding:14px 18px; margin-bottom:16px;">';
        $body .= '<strong>⚠️ Un avis négatif a été soumis et requiert votre attention.</strong>';
        $body .= '</div>';

        $html = $this->buildLayout(
            '🚨 Alerte : Avis négatif #' . $avis->getId(),
            'Action requise — Un client a soumis un avis avec une note très basse.',
            [
                'Note'     => $stars . ' (' . $avis->getNbEtoiles() . '/5)',
                'Catégorie' => htmlspecialchars($avis->getType() ? $avis->getType()->getNom() : '—'),
                'Contenu'  => nl2br(htmlspecialchars($avis->getContenu())),
                'Client'   => 'User #' . $avis->getUserId(),
            ],
            $body,
            '#fff5f5',
            '#dc3545'
        );

        $this->send(self::ADMIN_EMAIL, '🚨 Nouvel avis négatif - Action requise', $html);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────

    /**
     * Génère l'email fictif du client : user{id}@vianova.tn
     */
    private function getUserEmail(int $userId): string
    {
        // Pour les tests, tous les emails clients vont vers cette adresse
        return 'rayenhafian72@gmail.com';
    }

    /**
     * Construit un email HTML avec un tableau de propriétés.
     */
    private function buildLayout(
        string $title,
        string $intro,
        array  $infos,
        string $footer     = '',
        string $bgColor    = '#f8f9fa',
        string $accentColor = '#f35525'
    ): string {
        // Lignes du tableau d'informations
        $rows = '';
        $alt  = false;
        foreach ($infos as $label => $value) {
            $bg    = $alt ? '#f9f9f9' : '#ffffff';
            $rows .= "<tr>
                        <td style='padding:10px 14px; font-weight:700; color:#555; background:{$bg}; width:35%; border-bottom:1px solid #f0f0f0;'>{$label}</td>
                        <td style='padding:10px 14px; color:#333; background:{$bg}; border-bottom:1px solid #f0f0f0;'>{$value}</td>
                      </tr>";
            $alt = !$alt;
        }

        $body = "<p style='color:#555; font-size:1rem; margin-bottom:16px;'>{$intro}</p>
                 <table style='width:100%; border-collapse:collapse; border-radius:8px; overflow:hidden; border:1px solid #f0f0f0;'>
                   {$rows}
                 </table>
                 {$footer}";

        return $this->buildLayoutRaw($title, $body, $bgColor, $accentColor);
    }

    /**
     * Construit le layout HTML complet de l'email (avec en-tête et pied de page Villa Agency).
     */
    private function buildLayoutRaw(
        string $title,
        string $body,
        string $bgColor     = '#f8f9fa',
        string $accentColor = '#f35525'
    ): string {
        return "<!DOCTYPE html>
<html lang='fr'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
</head>
<body style='margin:0; padding:20px; background:{$bgColor}; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Arial, sans-serif;'>
  <div style='max-width:600px; margin:0 auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08);'>

    <!-- En-tête -->
    <div style='background:{$accentColor}; padding:28px 32px; text-align:center;'>
      <h1 style='color:#ffffff; font-size:1.6rem; margin:0; font-weight:900; letter-spacing:-0.5px;'>🏖️ Villa Agency</h1>
      <p style='color:rgba(255,255,255,0.85); margin:6px 0 0; font-size:0.85rem;'>Votre agence de voyage de confiance</p>
    </div>

    <!-- Titre de l'email -->
    <div style='padding:24px 32px 8px;'>
      <h2 style='color:#222; font-size:1.25rem; font-weight:700; margin:0 0 8px;'>{$title}</h2>
      <div style='width:50px; height:3px; background:{$accentColor}; border-radius:2px; margin-bottom:20px;'></div>
    </div>

    <!-- Corps -->
    <div style='padding:0 32px 28px;'>
      {$body}
    </div>

    <!-- Pied de page -->
    <div style='background:#f8f9fa; padding:16px 32px; text-align:center; border-top:1px solid #eee;'>
      <p style='color:#aaa; font-size:0.75rem; margin:0; line-height:1.5;'>
        Villa Agency — Document généré automatiquement.<br>
        Merci de ne pas répondre directement à cet email.
      </p>
    </div>

  </div>
</body>
</html>";
    }

    /**
     * Envoie l'email — les exceptions sont capturées silencieusement
     * pour ne pas bloquer le flux de l'application.
     */
    private function send(string $to, string $subject, string $html): void
    {
        try {
            $email = (new Email())
                ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
                ->to($to)
                ->subject($subject)
                ->html($html);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Lancer l'exception pour voir exactement l'erreur de Gmail (débogage)
            throw $e;
        }
    }
}
