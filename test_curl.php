<?php
$result = shell_exec('curl -s -X POST "https://oauth2.googleapis.com/token" -d "grant_type=test"');
echo $result;
echo "\nCURL exit: " . ($result === null ? 'NULL' : 'OK');