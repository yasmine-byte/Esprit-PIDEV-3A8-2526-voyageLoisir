<?php
$creds = json_decode(file_get_contents('config/firebase-credentials.json'), true);
echo 'Email: ' . $creds['client_email'] . "\n";
echo 'Project: ' . $creds['project_id'] . "\n";
echo 'Key length: ' . strlen($creds['private_key']) . "\n";