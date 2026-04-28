<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Add new service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $price = $_POST['price'];
    $desc  = $_POST['description'];

    $stmt = $conn->prepare(
        "INSERT INTO services (name, description, price) VALUES (?, ?, ?)"
    );

    if ($stmt->execute([$name, $desc, $price])) {
        $success = "Service added successfully.";
    } else {
        $error = "Failed to add service.";
    }
}

// FETCH SERVICES (YOU MISSED THIS)
$services = $conn->query(
    "SELECT * FROM services ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

?>  <!-- ✅ PHP CLOSED PROPERLY -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Services</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Admin CSS -->
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="page-content">

    <h2>Manage Services</h2>

    <?php if(isset($error)): ?>
        <p class="msg error"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if(isset($success)): ?>
        <p class="msg success"><?php echo $success; ?></p>
    <?php endif; ?>

    <!-- Navigation -->
    <div class="admin-nav">
        <a href="bookings.php">Manage Bookings</a> |
        <a href="dashboard.php">Dashboard</a> |
        <a href="../auth/logout.php">Logout</a>
    </div>

    <div class="section">
    <h3>Add Service</h3>

    <form method="post">
        <div class="form-grid">

            <div>
                <label>Service Name</label>
                <input type="text" name="name" required>
            </div>

            <div>
                <label>Price (RM)</label>
                <input type="number" name="price" step="0.01" required>
            </div>

            <div class="full">
                <label>Description</label>
                <textarea name="description"></textarea>
            </div>

            <div class="full">
                <button type="submit">Add Service</button>
            </div>

        </div>
    </form>
</div>

<div class="section">
   


    <h3>All Services</h3>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price (RM)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($services as $s): ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo htmlspecialchars($s['description']); ?></td>
                    <td><?php echo number_format($s['price'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
