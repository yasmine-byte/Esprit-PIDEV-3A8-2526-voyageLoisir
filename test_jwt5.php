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

// Sauvegarder JWT dans fichier
file_put_contents('jwt.txt', $jwt);

// Utiliser -F pour multipart ou JSON
$cmd = 'curl -s -X POST "https://oauth2.googleapis.com/token" '
     . '-H "Content-Type: application/x-www-form-urlencoded" '
     . '-d "grant_type=urn:ietf:params:oauth2:grant-type:jwt-bearer" '
     . '-d "assertion=' . $jwt . '"';

echo "CMD length: " . strlen($cmd) . "\n";
$result = shell_exec($cmd);
echo $result;
