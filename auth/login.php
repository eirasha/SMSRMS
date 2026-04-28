<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Enable error reporting (optional)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Safely get inputs
    $userInput = trim($_POST['userInput'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    if (empty($userInput) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        // Database check as before
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$userInput, $userInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['username']= $user['username'];
            
            // redirect based on role
            switch ($user['role']) {
                case 'admin': header("Location: ../admin/dashboard.php"); break;
                case 'customer': header("Location: ../customer/dashboard.php"); break;
                case 'massager': header("Location: ../massager/dashboard.php"); break;
            }
            exit;
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}

?>


<!-- Display errors -->
<?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Sunflower Theme</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="auth-container">
    <div style="display: flex; justify-content: center;">
  <img src="/SMSRMS/uploads/logo.png" alt="System Logo">
</div>

    <h2>
        <p style="font-family: Poppins; font-style: ; font-weight: bold;">

    Login </p> </h2>

    <?php if (isset($error)) : ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="auth-form">
        <label>Username or Email</label>
        <input type="text" name="userInput" value="<?= isset($userInput) ? htmlspecialchars($userInput) : '' ?>" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn">Login</button>
    </form>

    <p class="login-link">Don't have an account? <a href="register.php">Register here</a></p>
</div>

</body>
</html>
