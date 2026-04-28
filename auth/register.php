<?php
require_once __DIR__ . '/../config/db.php';

// Enable error reporting (optional for debugging)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get and trim user inputs
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    // Step 1: Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
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
                $success = "Registration successful! <a href='login.php'>login here</a>.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>



<!-- Display errors / success -->
<?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
<?php if (isset($success)) { echo "<p style='color:green;'>$success</p>"; } ?>




<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Sunflower Theme</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="auth-container">
    <div style="display: flex; justify-content: center;">
  <img src="your-image.jpg" alt="Centered Image">
</div>

    <h2>Register</h2>

    <?php if (isset($error)) : ?>
        <div class="alert error"><?= $error ?></div>
    <?php endif; ?>
    <?php if (isset($success)) : ?>
        <div class="alert success"><?= $success ?></div>
    <?php endif; ?>

    <form method="post" class="auth-form">
        <label>Username</label>
        <input type="text" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn">Register</button>
    </form>

    <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
</div>

</body>
</html>