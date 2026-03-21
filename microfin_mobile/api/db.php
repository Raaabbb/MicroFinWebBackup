<?php
// db.php
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "microfin_db";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "Database connection error: " . $e->getMessage()]);
    exit;
}
?>
