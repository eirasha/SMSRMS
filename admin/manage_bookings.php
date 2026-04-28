<?php
session_start();
require_once __DIR__ . '/../includes/booking_actions.php';
?>

<h2>All Bookings (Admin)</h2>

<table border="1" cellpadding="5">
<tr>
    <th>Customer</th>
    <th>Massager</th>
    <th>Service</th>
    <th>Date</th>
    <th>Time</th>
    <th>Status</th>
    <th>Action</th>
</tr>
<?php foreach($bookings as $b): ?>
<tr>
    <td><?= htmlspecialchars($b['customer_name']) ?></td>
    <td><?= htmlspecialchars($b['massager_name']) ?></td>
    <td><?= htmlspecialchars($b['service_name']) ?></td>
    <td><?= $b['booking_date'] ?></td>
    <td><?= $b['booking_time'] ?></td>
    <td><?= ucfirst($b['status']) ?></td>
    <td>
        <form method="post">
            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
            <select name="status">
                <option value="pending" <?= $b['status']=='pending'?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $b['status']=='approved'?'selected':'' ?>>Approved</option>
                <option value="completed" <?= $b['status']=='completed'?'selected':'' ?>>Completed</option>
                <option value="cancelled" <?= $b['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
            <button type="submit">Update</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>

<nav>
    <a href="dashboard.php">Dashboard</a> | 
    <a href="../auth/logout.php">Logout</a>
</nav>
