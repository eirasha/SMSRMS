<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$users = $conn->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>All Users</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Created At</th>
    </tr>
    <?php foreach($users as $u) { ?>
    <tr>
        <td><?php echo $u['id']; ?></td>
        <td><?php echo htmlspecialchars($u['username']); ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><?php echo ucfirst($u['role']); ?></td>
        <td><?php echo $u['created_at']; ?></td>
    </tr>
    <?php } ?>
</table>
