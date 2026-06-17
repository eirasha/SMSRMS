<?php 
require_once __DIR__ . '/../../config/db.php';
include 'auth_check.php'; 

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM massagers WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: massagers.php");
exit;
?>