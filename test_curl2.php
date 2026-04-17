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

$postData = 'grant_type=urn%3Aietf%3Aparams%3Aoauth2%3Agrant-type%3Ajwt-bearer&assertion='.$jwt;

$result = shell_exec('curl -s -X POST "https://oauth2.googleapis.com/token" -H "Content-Type: application/x-www-form-urlencoded" -d "'.$postData.'"');
echo $result;