<?php
function testLogin() {
    $ch = curl_init('http://127.0.0.1:8000/admin/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $html = substr($response, $header_size);
    curl_close($ch);
    
    preg_match('/name="_csrf_token" value="(.*?)"/', $html, $matches);
    if (!isset($matches[1])) {
        echo "No CSRF token found.\n";
        return;
    }
    $csrf = $matches[1];
    
    // POST request with WRONG password
    $ch = curl_init('http://127.0.0.1:8000/admin/login/check');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // FOLLOW LOCATION THIS TIME to get the body
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        '_username' => 'admin@admin.com',
        '_password' => 'wrongpassword123',
        '_csrf_token' => $csrf
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);
    
    if (strpos($body, 'alert-danger') !== false) {
         preg_match('/<div class="alert alert-danger">\s*(.*?)\s*<\/div>/s', $body, $matches);
         echo "Error found: " . trim($matches[1]) . "\n";
    } else {
         echo "NO ERROR FOUND on page! This means the error isn't displaying or NO error occurred!\n";
    }
}
testLogin();
