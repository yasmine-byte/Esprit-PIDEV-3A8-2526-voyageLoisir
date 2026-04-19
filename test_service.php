<?php
require 'vendor/autoload.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$service = $container->get('App\Service\ChatbotService');

$response = $service->sendMessage("Hello from CLI in context");
echo "Response: " . $response . "\n";
