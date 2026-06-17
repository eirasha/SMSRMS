<?php 
require_once __DIR__ . '/../../config/db.php';
include 'auth_check.php'; 

// 🔥 FORCE PDO TO SHOW ERRORS AND STOP REDIRECTS
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle Add Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add'; 
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 1;

    if ($action === 'add' && !empty($name)) {
        
        $default_password = password_hash('password123', PASSWORD_DEFAULT);
        $role = 'massager';
        $username = strtolower(str_replace(' ', '_', $name)); 

        try {
            $conn->beginTransaction();

            // 1. INSERT INTO USERS (Notice status is 1, not 'active')
            $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
            $stmt1->execute([$username, $email, $default_password, $role]);

            // 2. GRAB NEW ID
            $new_user_id = $conn->lastInsertId();

            // 3. INSERT INTO MASSAGERS
            $stmt2 = $conn->prepare("INSERT INTO massagers (user_id, name, phone, email, status) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$new_user_id, $name, $phone, $email, $status]);

            $conn->commit();
            
            // 🔥 IF SUCCESSFUL, STOP HERE AND SHOW GREEN
            die("<h1 style='color: green; text-align: center; margin-top: 50px;'>✅ SUCCESS! SAVED TO BOTH TABLES!</h1>");

        } catch (PDOException $e) {
            $conn->rollBack();
            // 🔥 IF FAILED, STOP HERE AND SHOW RED ERROR
            die("<h1 style='color: red; text-align: center; margin-top: 50px;'>❌ DATABASE ERROR: <br><br>" . $e->getMessage() . "</h1>");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Massager</title>
    <link rel="stylesheet" href="../../css/admin.css">
</head>
<body class="admin-body">
    <div class="auth-container" style="margin-top: 100px;">
        <h2 class="login-title">Add New Massager</h2>
        <form class="auth-form" method="POST">
            <input type="hidden" name="action" value="add">
            
            <label>Full Name</label>
            <input type="text" name="name" required>
            
            <label>Phone Number</label>
            <input type="text" name="phone" required>
            
            <label>Email Address</label>
            <input type="email" name="email" required>
            
            <button type="submit" class="btn">Save Massager</button>
            <div class="login-link"><a href="massagers.php">Cancel & Return</a></div>
        </form>
    </div>
</body>
</html>