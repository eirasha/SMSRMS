<?php
$host = "localhost";
$user = "root";  // default XAMPP
$pass = "";      // default XAMPP
$dbname = "smsrms";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
