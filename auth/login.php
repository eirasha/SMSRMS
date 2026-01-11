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

<h2>Login</h2>

<!-- Display errors -->
<?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>

<form method="post">
    Username or Email: <input type="text" name="userInput" value="<?php echo isset($userInput) ? htmlspecialchars($userInput) : ''; ?>" required><br>
    Password: <input type="password" name="password" required><br><br>
    <button type="submit">Login</button>
</form>
<p>Don't have an account? <a href="register.php">Register here</a></p>
