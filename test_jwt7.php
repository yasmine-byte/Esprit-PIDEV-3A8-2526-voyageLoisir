<?php
// Test avec l'endpoint d'échange de token Google
$creds = json_decode(file_get_contents('config/firebase-credentials.json'), true);
$now = time();

$header  = rtrim(strtr(base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT'])),'+/','-_'),'=');
$payload = rtrim(strtr(base64_encode(json_encode([
    'iss'   => $creds['client_email'],
    'sub'   => $creds['client_email'],
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'iat'   => $now,
    'exp'   => $now + 3600,
])),'+/','-_'),'=');

$input = $header.'.'.$payload;
openssl_sign($input, $sig, $creds['private_key'], OPENSSL_ALGO_SHA256);
$jwt = $input.'.'.rtrim(strtr(base64_encode($sig),'+/','-_'),'=');

$postData = http_build_query([
    'grant_type' => 'urn:ietf:params:oauth2:grant-type:jwt-bearer',
    'assertion'  => $jwt,
]);

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $response\n";