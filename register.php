<!DOCTYPE html>
<html>
<head>
  <title>Registration</title>
</head>
<body>

<h2>Register</h2>

<form method="POST" action="auth/register.php">

  <input type="text" name="name" placeholder="Full Name" required><br><br>

  <input type="email" name="email" placeholder="Email" required><br><br>

  <input type="password" name="password" placeholder="Password" required><br><br>

  
  <select name="role" required>
    <option value="">-- Select Role --</option>
    <option value="customer">Customer</option>
    <option value="massager">Massager</option>
  </select><br><br>

  <button type="submit">Register</button>

</form>

<p>Already have an account? <a href="index.php">Login</a></p>

</body>
</html>
