<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Enable error reporting (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = "";
$userInput = "";

// =========================
// HANDLE LOGIN
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $userInput = trim($_POST['userInput'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($userInput) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$userInput, $userInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // HIGH-END SECURITY: Prevent Session Fixation attacks
            session_regenerate_id(true);

            // SESSION
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['username'] = $user['username'];

            // REDIRECT ROUTING
            $routes = [
                'admin' => '../admin/dashboard.php',
                'customer' => '../customer/dashboard.php',
                'massager' => '../massager/dashboard.php'
            ];

            $redirect_url = $routes[$user['role']] ?? '../index.php';
            header("Location: " . $redirect_url);
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
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/login.css"> </head>
<body>

<div class="container">
    <form method="post" action="">
        <img src="../uploads/logo.png" alt="Sunflower Logo" style="width: 85px; height: auto; margin-bottom: 15px; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
        <h1>Welcome Back</h1>
        
        <?php if (!empty($error)) : ?>
            <p style="color: #d9534f; font-size: 13px; font-weight: 600; margin-top: 5px;"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <p>Log in to access your dashboard</p>
        <?php endif; ?>

        <div class="input-box">
            <input type="text" name="userInput" placeholder="Username or Email" value="<?= htmlspecialchars($userInput) ?>" required>
            <i class='bx bxs-user'></i>
        </div>

        <div class="input-box">
            <input type="password" name="password" placeholder="Password" required>
            <i class='bx bxs-lock-alt'></i>
        </div>

        <div class="forgot-link">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>

        <button type="submit" name="login" class="btn">Login</button>
        
        <div class="divider">
        </div>

      

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Sign up here</a></p>
        </div>
    </form>
</div>

</body>
</html>