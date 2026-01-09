<?php
include "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Get role from form
    $role = $_POST['role'];

    // Security: allow only customer or massager
    if ($role !== 'customer' && $role !== 'massager') {
        echo "Invalid role selected";
        exit;
    }

    // Check email exists
    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        echo "Email already registered";
        exit;
    }

    $sql = "INSERT INTO users (name, email, password, role)
            VALUES ('$name', '$email', '$password', '$role')";

    if (mysqli_query($conn, $sql)) {
        header("Location: ../index.php");
        exit;
    } else {
        echo "Registration failed";
    }
}
?>
