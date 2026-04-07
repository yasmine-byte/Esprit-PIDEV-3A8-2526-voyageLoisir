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
    
    // Extract CSRF token
    preg_match('/name="_csrf_token" value="(.*?)"/', $html, $matches);
    if (!isset($matches[1])) {
        echo "No CSRF token found.\n";
        return;
    }
    $csrf = $matches[1];
    
    echo "Using CSRF token: $csrf\n";
    
    // POST request
    $ch = curl_init('http://127.0.0.1:8000/admin/login/check');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        '_username' => 'admin@admin.com',
        '_password' => 'admin123',
        '_csrf_token' => $csrf
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    curl_close($ch);
    
    echo "POST Headers:\n$headers\n";
    
    preg_match('/Location: (.*)/', $headers, $locMatch);
    if (isset($locMatch[1])) {
        $loc = trim($locMatch[1]);
        echo "Redirected to: $loc\n";
        
        // GET the redirected location
        $ch = curl_init($loc);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers2 = substr($response, 0, $header_size);
        $body2 = substr($response, $header_size);
        curl_close($ch);
        
        echo "Redirect Headers:\n$headers2\n";
        if (strpos($body2, 'alert-danger') !== false) {
             preg_match('/<div class="alert alert-danger">\s*(.*?)\s*<\/div>/s', $body2, $matches);
             echo "Error on redirected page: " . trim($matches[1]) . "\n";
        }
    }
}
testLogin();
