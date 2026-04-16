<?php

use App\Kernel;
use App\Entity\Users;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/Esprit-PIDEV-3A8-2526-voyageLoisir/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/Esprit-PIDEV-3A8-2526-voyageLoisir/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$admin = $em->getRepository(Users::class)->findOneBy(['email' => 'admin@admin.com']);

if ($admin) {
    echo "Found admin!\n";
    echo "Roles from getRoles(): " . implode(', ', $admin->getRoles()) . "\n";
    echo "Is valid password (admin123)? " . (password_verify('admin123', $admin->getPassword()) ? 'Yes' : 'No') . "\n";
} else {
    echo "Admin not found!\n";
}

