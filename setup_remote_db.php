<?php
$host = "centerbeam.proxy.rlwy.net";
$port = 52624;
$db   = "railway";
$user = "root";
$pass = "zVULvPIbSyHVavTRnPFAkMWGVmvRwInd";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [ 
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30
    ]);
    
    $sql = file_get_contents("microfin_platform/docs/database-plan.txt");    
    $sql = str_replace("CREATE DATABASE IF NOT EXISTS fundline_microfinancing;", "", $sql);
    $sql = str_replace("USE fundline_microfinancing;", "", $sql);
    
    // Remove the event entirely for this remote run to prevent syntax errors with PDO
    $sql = preg_replace('/-- Event to auto-expire OTPs.*?DELIMITER ;/is', '', $sql);
    
    $statements = array_filter(array_map('trim', explode(';', $sql)));       
    
    $count = 0;
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
            $count++;
        }
    }
    echo "Successfully migrated $count tables/chunks to Railway!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
