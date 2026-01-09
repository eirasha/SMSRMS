<?php
session_start();
include "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $sql);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {

            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['name'] = $row['name'];

            // Redirect based on role
            if ($row['role'] == 'admin') {
                header("Location: ../admin/dashboard.php");
            } elseif ($row['role'] == 'customer') {
                header("Location: ../customer/dashboard.php");
            } else {
                header("Location: ../massager/dashboard.php");
            }
            exit;

        } else {
            echo "Wrong password";
        }
    } else {
        echo "User not found";
    }
}
?>
