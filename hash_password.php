<?php
// Replace 'admin123' with the password you want to use
$password = 'admin123';

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Your hashed password is: <br>";
echo $hashedPassword;
?>
