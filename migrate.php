<?php
$conn = new PDO('mysql:host=127.0.0.1;port=3309;dbname=voyage;charset=utf8mb4', 'root', '');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = file_get_contents(__DIR__ . '/dump.txt');
$conn->exec("SET FOREIGN_KEY_CHECKS=0;");
$stmts = preg_split('/;/', $sql);
foreach($stmts as $stmt) {
    if(trim($stmt)) {
        try {
            $conn->exec($stmt);
            echo "Success!\n";
        } catch(Exception $e) {
            echo "Error: ".$e->getMessage()."\n";
        }
    }
}
$conn->exec("SET FOREIGN_KEY_CHECKS=1;");
echo "Done!\n";
