<?php
require 'vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

$dsn = 'gmail+smtp://ferjanimehdi02@gmail.com:cpfwrmylhbyjxjxe@default';
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('ferjanimehdi02@gmail.com')
    ->to('ferjanimehdi02@gmail.com')
    ->subject('Test email Vianova')
    ->text('Email test fonctionne !');

try {
    $mailer->send($email);
    echo "Email envoyé avec succès !\n";
} catch (\Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}