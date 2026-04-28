
<?php
session_start();

// 1. Clear all session variables
$_SESSION = array();

// 2. Destroy the session cookie if it exists (optional but cleaner)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to your login page
// Change 'login.php' to your actual file name
header("Location: login.php");
exit;
?>