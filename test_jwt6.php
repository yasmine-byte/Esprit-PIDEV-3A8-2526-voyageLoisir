<?php
$creds = json_decode(file_get_contents('config/firebase-credentials.json'), true);
$now = time();

$header  = rtrim(strtr(base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT'])),'+/','-_'),'=');
$payload = rtrim(strtr(base64_encode(json_encode([
    'iss'   => $creds['client_email'],
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'aud'   => 'https://oauth2.googleapis.com/token',
    'iat'   => $now,
    'exp'   => $now + 3600,
])),'+/','-_'),'=');

$input = $header.'.'.$payload;
openssl_sign($input, $sig, $creds['private_key'], OPENSSL_ALGO_SHA256);
$jwt = $input.'.'.rtrim(strtr(base64_encode($sig),'+/','-_'),'=');

// Afficher le payload exact envoyé
$postData = http_build_query([
    'grant_type' => 'urn:ietf:params:oauth2:grant-type:jwt-bearer',
    'assertion'  => $jwt,
]);

echo "POST DATA (first 100 chars):\n";
echo substr($postData, 0, 100) . "\n\n";

// Vérifier que grant_type est correct
parse_str($postData, $parsed);
echo "grant_type decoded: " . $parsed['grant_type'] . "\n\n";

// Envoyer avec PHP curl
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_VERBOSE, false);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Response: $response\n";