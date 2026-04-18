<?php
require 'vendor/autoload.php';

$key = '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';
echo "Key is: " . $key . "\n";

$client = \Symfony\Component\HttpClient\HttpClient::create();
$response = $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
    'headers' => [
        'Authorization' => 'Bearer ' . trim($key),
        'Content-Type' => 'application/json',
        'HTTP-Referer' => 'http://localhost'
    ],
    'json' => [
        'model' => 'meta-llama/llama-3.1-8b-instruct:free',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello']
        ]
    ]
]);

$data = $response->toArray(false);
print_r($data);
