<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* SECURITY */
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','massager'])) {
    exit('Unauthorized');
}

/* VALIDATION */
if (empty($_POST['feedback_id']) || empty(trim($_POST['reply']))) {
    exit('Missing feedback ID or reply');
}

$feedback_id = (int) $_POST['feedback_id'];
$reply = trim($_POST['reply']);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

/* INSERT REPLY */
$stmt = $conn->prepare("
    INSERT INTO feedback_replies (feedback_id, replied_by, role, reply)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$feedback_id, $user_id, $role, $reply]);

/* REDIRECT BACK TO REFERRER */
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
