<?php
session_start();
if ($_SESSION['role'] != 'admin') { header("Location: ../index.php"); exit; }
?>
<h1>Admin Dashboard</h1>
<p>Welcome <?php echo $_SESSION['name']; ?></p>
<a href="../auth/logout.php">Logout</a>
