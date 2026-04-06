<?php
function testLogin() {
    $ch = curl_init('http://127.0.0.1:8000/admin/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    $html = curl_exec($ch);
    
    // Extract CSRF token
    preg_match('/name="_csrf_token" value="(.*?)"/', $html, $matches);
    if (!isset($matches[1])) {
        echo "No CSRF token found.\n";
        return;
    }
    $csrf = $matches[1];
    
    // POST request
    $ch = curl_init('http://127.0.0.1:8000/admin/login/check');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        '_username' => 'admin@admin.com',
        '_password' => 'admin123',
        '_csrf_token' => $csrf
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    
    $result = curl_exec($ch);
    echo "Response Headers:\n";
    echo $result;
}
testLogin();
