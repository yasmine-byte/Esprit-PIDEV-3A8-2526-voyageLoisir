<?php
$creds = json_decode(file_get_contents('config/firebase-credentials.json'), true);
$now = time();

$headerData = ['alg'=>'RS256','typ'=>'JWT'];
$payloadData = [
    'iss'   => $creds['client_email'],
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'iat'   => $now,
    'exp'   => $now + 3600,
];

echo "ISS: " . $creds['client_email'] . "\n";
echo "NOW: " . $now . "\n";

$header  = rtrim(strtr(base64_encode(json_encode($headerData)),'+/','-_'),'=');
$payload = rtrim(strtr(base64_encode(json_encode($payloadData)),'+/','-_'),'=');
$input   = $header.'.'.$payload;

$ok = openssl_sign($input, $sig, $creds['private_key'], OPENSSL_ALGO_SHA256);
echo "OpenSSL sign: " . ($ok ? 'OK' : 'FAILED') . "\n";

$jwt = $input.'.'.rtrim(strtr(base64_encode($sig),'+/','-_'),'=');
echo "JWT length: " . strlen($jwt) . "\n";
echo "JWT parts: " . count(explode('.', $jwt)) . "\n";

// Test avec curl via file
file_put_contents('test_payload.txt', 'grant_type=urn%3Aietf%3Aparams%3Aoauth2%3Agrant-type%3Ajwt-bearer&assertion='.$jwt);
$result = shell_exec('curl -s -X POST "https://oauth2.googleapis.com/token" -H "Content-Type: application/x-www-form-urlencoded" --data-binary @test_payload.txt');
echo "Response: " . $result . "\n";