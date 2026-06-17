<?php 
require_once __DIR__ . '/../../config/db.php';
include 'auth_check.php'; 

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: massagers.php");
    exit;
}

// Fetch current details
$stmt = $conn->prepare("SELECT * FROM massagers WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $status = $_POST['status'];

    $update = $conn->prepare("UPDATE massagers SET name=?, phone=?, email=?, status=? WHERE id=?");
    $update->execute([$name, $phone, $email, $status, $id]);
    
    header("Location: massagers.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Massager</title>
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body class="admin-body">
    <div class="auth-container" style="margin-top: 100px;">
        <h2 class="login-title">Edit Massager</h2>
        <form class="auth-form" method="POST">
            <label>Full Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($data['name']) ?>" required>
            
            <label>Phone Number</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($data['phone']) ?>" required>
            
            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>" required>
            
            <label>Status</label>
            <select name="status">
                <option value="1" <?= $data['status'] == 1 ? 'selected' : '' ?>>Available</option>
                <option value="0" <?= $data['status'] == 0 ? 'selected' : '' ?>>Busy</option>
            </select>
            
            <button type="submit" class="btn">Update Info</button>
            <div class="login-link"><a href="massagers.php">Cancel & Return</a></div>
        </form>
    </div>
</body>
</html>