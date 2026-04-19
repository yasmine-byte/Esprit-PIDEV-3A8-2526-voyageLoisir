<?php
require 'vendor/autoload.php';

$client = \Symfony\Component\HttpClient\HttpClient::create();
$response = $client->request('GET', 'https://generativelanguage.googleapis.com/v1beta/models?key=AIzaSyAhFnk1kBcUkrWQHU1EYvREIA2TH53gaFM');

$data = $response->toArray(false);
foreach ($data['models'] as $m) {
    if (strpos($m['name'], 'gemini') !== false) {
        echo $m['name'] . "\n";
    }
}
