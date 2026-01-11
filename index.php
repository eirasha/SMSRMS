<!DOCTYPE html>
<html>
<head>
  <title>Login</title>
</head>
<body>

<h2>Login</h2>

<form method="POST" action="auth/login.php">
  <input type="email" name="email" placeholder="Email" required><br><br>
  <input type="password" name="password" placeholder="Password" required><br><br>
  <button type="submit">Login</button>
</form>

<p>No account? <a href="register.php">Register here</a></p>

<a href="admin/dashboard.php">Admin Dashboard</a>
<a href="customer/bookings.php">Customer Bookings</a>
<a href="massager/bookings.php">Massager Bookings</a>

</body>
</html>
