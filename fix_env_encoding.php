<?php

$envFile = 'C:\\esprit\\3A\\Pi Dev\\Esprit-PIDEV-3A8-2526-voyageLoisir\\.env';

// 1. Check for NUL bytes before
$bytesBefore = file_get_contents($envFile);
$nulPresentBefore = strpos($bytesBefore, "\x00") !== false;

// 2. Determine if conversion is needed and perform it
$conversionPerformed = false;
if ($nulPresentBefore) {
    try {
        // Try to decode from UTF-16 LE (most common encoding with NUL bytes)
        $content = iconv('UTF-16LE', 'UTF-8', $bytesBefore);
        if ($content === false) {
            // Fall back: try UTF-16
            $content = iconv('UTF-16', 'UTF-8', $bytesBefore);
        }
    } catch (Exception $e) {
        // Fall back to identity conversion
        $content = $bytesBefore;
    }
    
    // Write back as UTF-8 without BOM
    file_put_contents($envFile, $content);
    $conversionPerformed = true;
}

// 3. Check for NUL bytes after
$bytesAfter = file_get_contents($envFile);
$nulPresentAfter = strpos($bytesAfter, "\x00") !== false;

// 4. Check for key presence
$keysToCheck = ['APP_ENV', 'APP_SECRET', 'DATABASE_URL', 'MESSENGER_TRANSPORT_DSN'];
$keysPresent = [];

$lines = explode("\n", $bytesAfter);
foreach ($keysToCheck as $key) {
    $keysPresent[$key] = false;
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) > 0 && trim($parts[0]) === $key) {
            $keysPresent[$key] = true;
            break;
        }
    }
}

$result = [
    'nul_present_before' => $nulPresentBefore,
    'conversion_performed' => $conversionPerformed,
    'nul_present_after' => $nulPresentAfter,
    'keys' => $keysPresent
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
