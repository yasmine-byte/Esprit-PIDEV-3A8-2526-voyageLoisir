<?php
require 'vendor/autoload.php';

$client = \Symfony\Component\HttpClient\HttpClient::create();
$response = $client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=AIzaSyAhFnk1kBcUkrWQHU1EYvREIA2TH53gaFM', [
    'json' => [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Hello, es-tu réveillé ? Réponds par "OUI JE FONCTIONNE" pour confirmer.']
                ]
            ]
        ]
    ]
]);

$data = $response->toArray(false);
print_r($data);
