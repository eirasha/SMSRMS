<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $stmt = $conn->prepare("UPDATE services SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $id]);
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $field = $_POST['field']; // 'name', 'description', or 'price'
        $value = $_POST['value'];
        $stmt = $conn->prepare("UPDATE services SET $field = ? WHERE id = ?");
        $stmt->execute([$value, $id]);
    }
}