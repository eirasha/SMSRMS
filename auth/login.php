<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Enable error reporting (for development)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = "";
$userInput = "";

// =========================
// HANDLE LOGIN
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $userInput = trim($_POST['userInput'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($userInput) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$userInput, $userInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // SESSION
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['username'] = $user['username'];

            // REDIRECT
            if ($user['role'] === 'admin') {
                header("Location: ../admin/dashboard.php");
            } elseif ($user['role'] === 'customer') {
                header("Location: ../customer/dashboard.php");
            } elseif ($user['role'] === 'massager') {
                header("Location: ../massager/dashboard.php");
            }

            exit;
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Sunflower</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="auth-container">

    <div class="logo-container">
        <div style="display: flex; justify-content: center;">
        <img src="../uploads/logo.png" alt="System Logo" class="logo" style="width: 120px; height: auto;" class="centered-image">
        
    </div>
    <!-- TITLE -->
    <h2 class="login-title">Login</h2>

    <!-- ERROR -->
    <?php if (!empty($error)) : ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- FORM -->
    <form method="post" class="auth-form">

        <label>Username or Email</label>
        <input type="text" name="userInput"
            value="<?= htmlspecialchars($userInput) ?>" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn">Login</button>
    </form>

    <!-- LINK -->
    <p class="login-link">
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>

</body>
</html>