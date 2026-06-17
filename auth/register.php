<?php
require_once __DIR__ . '/../config/db.php';

// Enable error reporting (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get and trim user inputs
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Default to 'customer' if role isn't posted
    $role = $_POST['role'] ?? 'customer'; 

    // Step 1: Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        // NEW CHECK: Ensure passwords match
        $error = "Passwords do not match. Please try again.";
    } else {
        // Step 2: Check if username or email already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $error = "Username or Email already exists!";
        } else {
            // Step 3: Hash password and insert new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            
            if ($insert->execute([$username, $email, $hashedPassword, $role])) {
                $success = "Registration successful! <a href='login.php' style='color: #b59b35; text-decoration: underline;'>Login here</a>.";
                // Clear inputs on success
                $username = "";
                $email = "";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sunflower</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/login.css"> 
</head>
<body>

<div class="container">
    <form method="post" action="">
        <img src="../uploads/logo.png" alt="Sunflower Logo" style="width: 85px; height: auto; margin-bottom: 15px; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">

        <h1>Create Account</h1>

        <?php if (!empty($error)) : ?>
            <p style="color: #d9534f; font-size: 13px; font-weight: 600; margin-top: 5px;"><?= htmlspecialchars($error) ?></p>
        <?php elseif (!empty($success)) : ?>
            <p style="color: #4caf50; font-size: 14px; font-weight: 600; margin-top: 5px;"><?= $success ?></p>
        <?php else: ?>
            <p>Join Sunflower today</p>
        <?php endif; ?>

        <input type="hidden" name="role" value="customer">

        <div class="input-box">
            <input type="text" name="username" placeholder="Username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>
            <i class='bx bxs-user'></i>
        </div>

        <div class="input-box">
            <input type="email" name="email" placeholder="Email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
            <i class='bx bxs-envelope'></i>
        </div>

        <div class="input-box">
            <input type="password" name="password" placeholder="Password" required>
            <i class='bx bxs-lock-alt'></i>
        </div>

        <div class="input-box">
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <i class='bx bx-check-shield'></i>
        </div>

        <button type="submit" class="btn" style="margin-top: 15px;">Register</button>

        <div class="register-link">
            <p>Already have an account? <a href="login.php">Log in here</a></p>
        </div>
    </form>
</div>

</body>
</html>