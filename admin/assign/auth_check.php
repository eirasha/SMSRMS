<?php
session_start();
// Jump up two folders to reach auth/login.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit("Unauthorized access.");
}
?>