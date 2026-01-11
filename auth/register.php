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

<h2>Register</h2>

<!-- Display errors / success -->
<?php if (isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
<?php if (isset($success)) { echo "<p style='color:green;'>$success</p>"; } ?>

<form method="post">
    Username: <input type="text" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required><br>
    Email: <input type="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required><br>
    Password: <input type="password" name="password" required><br>
    Role: 
    <select name="role" required>
        <option value="">--Select Role--</option>
        <option value="customer" <?php if(isset($role) && $role=="customer") echo "selected"; ?>>Customer</option>
        <option value="massager" <?php if(isset($role) && $role=="massager") echo "selected"; ?>>Massager</option>
        
    </select><br><br>
    <button type="submit">Register</button>
</form>
