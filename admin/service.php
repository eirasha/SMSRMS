<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Add new service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $desc = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO services (name, description, price) VALUES (?, ?, ?)");
    if ($stmt->execute([$name, $desc, $price])) {
        $success = "Service added successfully.";
    } else {
        $error = "Failed to add service.";
    }
}

// Fetch all services
$services = $conn->query("SELECT * FROM services ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Manage Services</h2>

<?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
 <a href="bookings.php">Manage Bookings</a> 
    |<a href="dashboard.php">Dashboard</a> | 
    <a href="../auth/logout.php">Logout</a>


<h3>Add Service</h3>
<form method="post">
    Name: <input type="text" name="name" required><br>
    Price: <input type="number" name="price" step="0.01" required><br>
    Description:<br>
    <textarea name="description"></textarea><br><br>
    <button type="submit">Add Service</button>
</form>

<h3>All Services</h3>
<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Description</th>
        <th>Price</th>
    </tr>
    <?php foreach($services as $s) { ?>
    <tr>
        <td><?php echo $s['id']; ?></td>
        <td><?php echo htmlspecialchars($s['name']); ?></td>
        <td><?php echo htmlspecialchars($s['description']); ?></td>
        <td>RM<?php echo $s['price']; ?></td>
    </tr>
    <?php } ?>
</table>

