<?php
$conn = new mysqli("localhost", "root", "");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Successfully connected to MySQL with empty password!\n";
}

$conn->query("CREATE DATABASE IF NOT EXISTS microfin_db");
if($conn->select_db("microfin_db")) {
    echo "Selected microfin_db!\n";
} else {
    echo "Failed to select db!\n";
}
?>
