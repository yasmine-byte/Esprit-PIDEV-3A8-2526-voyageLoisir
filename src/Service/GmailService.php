<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class GmailService
{
    private string $fromEmail = 'elarbiahmed0@gmail.com';
    private string $fromName  = 'ViaNoVa';

    public function __construct(private MailerInterface $mailer) {}

    // ── Email de bienvenue après inscription ──────────────────────────────────
    public function sendWelcomeEmail(string $toEmail, string $toName): void
    {
        $email = (new Email())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($toEmail)
            ->subject('Bienvenue sur ViaNoVa ! 🌍')
            ->html("
                <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:20px;'>
                    <div style='background:linear-gradient(135deg,#e8a87c,#d4845a); padding:30px; border-radius:12px; text-align:center; margin-bottom:20px;'>
                        <h1 style='color:#fff; margin:0; font-size:28px;'>🌍 ViaNoVa</h1>
                        <p style='color:#fff; margin:8px 0 0; font-size:14px;'>Votre plateforme de voyage</p>
                    </div>
                    <div style='background:#f9f9f9; padding:30px; border-radius:12px;'>
                        <h2 style='color:#d4845a;'>Bienvenue {$toName} ! 👋</h2>
                        <p style='color:#555; line-height:1.6;'>
                            Votre compte a été créé avec succès sur <strong>ViaNoVa</strong>.
                            Nous sommes ravis de vous accueillir sur notre plateforme de voyage et de loisir.
                        </p>
                        <p style='color:#555; line-height:1.6;'>
                            Vous pouvez dès maintenant explorer nos destinations, hébergements et activités.
                        </p>
                        <div style='text-align:center; margin:30px 0;'>
                            <a href='http://localhost:8000'
                               style='background:linear-gradient(135deg,#e8a87c,#d4845a); color:#fff; padding:14px 32px; border-radius:8px; text-decoration:none; font-weight:600; font-size:16px;'>
                                Découvrir ViaNoVa
                            </a>
                        </div>
                        <p style='color:#999; font-size:12px; text-align:center; margin-top:20px;'>
                            Si vous n'avez pas créé ce compte, ignorez cet email.
                        </p>
                    </div>
                </div>
            ");

        $this->mailer->send($email);
    }

    // ── Email de vérification d'adresse email ────────────────────────────────
    public function sendVerificationEmail(string $toEmail, string $toName, string $verificationLink): void
    {
        $email = (new Email())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($toEmail)
            ->subject('Vérifiez votre adresse email - ViaNoVa')
            ->html("
                <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:20px;'>
                    <div style='background:linear-gradient(135deg,#e8a87c,#d4845a); padding:30px; border-radius:12px; text-align:center; margin-bottom:20px;'>
                        <h1 style='color:#fff; margin:0; font-size:28px;'>✉️ ViaNoVa</h1>
                        <p style='color:#fff; margin:8px 0 0; font-size:14px;'>Vérification de votre email</p>
                    </div>
                    <div style='background:#f9f9f9; padding:30px; border-radius:12px;'>
                        <h2 style='color:#d4845a;'>Bonjour {$toName},</h2>
                        <p style='color:#555; line-height:1.6;'>
                            Merci de vous être inscrit sur <strong>ViaNoVa</strong>.
                            Veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous.
                        </p>
                        <div style='text-align:center; margin:30px 0;'>
                            <a href='{$verificationLink}'
                               style='background:linear-gradient(135deg,#e8a87c,#d4845a); color:#fff; padding:14px 32px; border-radius:8px; text-decoration:none; font-weight:600; font-size:16px;'>
                                Vérifier mon email
                            </a>
                        </div>
                        <p style='color:#999; font-size:13px; line-height:1.6;'>
                            Ce lien expire dans <strong>24 heures</strong>.<br>
                            Si vous n'avez pas créé de compte, ignorez cet email.
                        </p>
                        <p style='color:#bbb; font-size:12px; margin-top:20px;'>
                            Ou copiez ce lien dans votre navigateur :<br>
                            <span style='color:#d4845a; word-break:break-all;'>{$verificationLink}</span>
                        </p>
                    </div>
                </div>
            ");

        $this->mailer->send($email);
    }

    // ── Email de réinitialisation de mot de passe ────────────────────────────
    public function sendResetPasswordEmail(string $toEmail, string $toName, string $resetLink): void
    {
        $email = (new Email())
            ->from("{$this->fromName} <{$this->fromEmail}>")
            ->to($toEmail)
            ->subject('Réinitialisation de votre mot de passe ViaNoVa')
            ->html("
                <div style='font-family:Arial,sans-serif; max-width:600px; margin:0 auto; padding:20px;'>
                    <div style='background:linear-gradient(135deg,#e8a87c,#d4845a); padding:30px; border-radius:12px; text-align:center; margin-bottom:20px;'>
                        <h1 style='color:#fff; margin:0; font-size:28px;'>🔐 ViaNoVa</h1>
                        <p style='color:#fff; margin:8px 0 0; font-size:14px;'>Réinitialisation du mot de passe</p>
                    </div>
                    <div style='background:#f9f9f9; padding:30px; border-radius:12px;'>
                        <h2 style='color:#d4845a;'>Bonjour {$toName},</h2>
                        <p style='color:#555; line-height:1.6;'>
                            Vous avez demandé la réinitialisation de votre mot de passe.
                            Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe.
                        </p>
                        <div style='text-align:center; margin:30px 0;'>
                            <a href='{$resetLink}'
                               style='background:linear-gradient(135deg,#e8a87c,#d4845a); color:#fff; padding:14px 32px; border-radius:8px; text-decoration:none; font-weight:600; font-size:16px;'>
                                Réinitialiser mon mot de passe
                            </a>
                        </div>
                        <p style='color:#999; font-size:13px; line-height:1.6;'>
                            Ce lien expire dans <strong>1 heure</strong>.<br>
                            Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.
                        </p>
                    </div>
                </div>
            ");

        $this->mailer->send($email);
    }
}