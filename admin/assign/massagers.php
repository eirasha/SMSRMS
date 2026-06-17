<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// High-End Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 1;

    try {
        if ($action === 'add' && !empty($name)) {
            $conn->beginTransaction();
            $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'massager', 1)");
            $stmt1->execute([strtolower(str_replace(' ', '_', $name)), $email, password_hash('password123', PASSWORD_DEFAULT)]);
            $new_user_id = $conn->lastInsertId();
            
            $stmt2 = $conn->prepare("INSERT INTO massagers (user_id, name, phone, email, status) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$new_user_id, $name, $phone, $email, $status]);
            $conn->commit();
        } elseif ($action === 'edit' && $id) {
            $stmt = $conn->prepare("UPDATE massagers SET name=?, phone=?, email=?, status=? WHERE id=?");
            $stmt->execute([$name, $phone, $email, $status, $id]);
        }
    } catch (PDOException $e) {
        if (isset($conn)) $conn->rollBack();
        die("Database Error: " . $e->getMessage());
    }
    header("Location: massagers.php");
    exit;
}

$massagers = $conn->query("SELECT * FROM massagers")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Massagers | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Exact match to the premium palette */
            --gold: #c9a84c;
            --gold-light: #f4d03f;
            --gold-pale: #fdf8ec;
            --dark: #1a1208;
            --dark-soft: #2d2010;
            --text: #3d2e0e;
            --text-muted: #8a7355;
            --border: #e8d9b5;
            --white: #fffef9;
            --green: #2d6a4f;
            --green-light: #d8f3dc;
            --red: #c0392b;
            --red-light: #fdecea;
            --amber: #b7791f;
            --amber-light: #fef3c7;
            --card-shadow: 0 4px 24px rgba(201,168,76,0.10);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--gold-pale);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--dark);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 24px rgba(0,0,0,0.15);
            z-index: 100;
        }

        .brand {
            padding: 30px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 20px;
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            color: var(--gold-light);
            letter-spacing: 2px;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 0 16px;
            flex: 1;
        }

        .nav-links a {
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            color: #c4b08a;
            padding: 12px 18px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--gold-light);
            background: rgba(244,208,63,0.08);
        }

        .nav-links a.logout {
            color: #e57373;
            margin-top: auto;
            margin-bottom: 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-links a.logout:hover {
            background: rgba(229,115,115,0.1);
            color: #ff8a8a;
        }

        .nav-links a.logout span {
            margin-right: 8px;
        }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: 260px;
            padding: 40px 50px;
            flex-grow: 1;
            width: calc(100% - 260px);
        }
        .welcome { margin-bottom: 35px; }
        .welcome h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--dark);
            line-height: 1.2;
        }
        .welcome-desc {
            font-size: 0.95rem; color: var(--text-muted);
            font-weight: 400; margin-top: 6px;
        }

        /* ── DATA CARD & TABLE ── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        .card-header {
            padding: 22px 26px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h2 {
            font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--dark); margin: 0;
        }
        
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            padding: 14px 26px; text-align: left; font-size: 0.78rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
            color: var(--text-muted); background: var(--gold-pale);
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 16px 26px; font-size: 0.92rem;
            border-bottom: 1px solid #f0e8d0; vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: var(--gold-pale); }

        /* ── BUTTONS & BADGES ── */
        .btn {
            padding: 10px 20px; border-radius: 8px; font-size: 0.9rem; font-family: inherit;
            font-weight: 600; background: var(--gold); color: var(--dark); border: none;
            cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block;
        }
        .btn:hover { background: #b8942e; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(201,168,76,0.2); }
        
        /* Action Buttons for Table */
        .btn-action {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            font-family: inherit;
        }
        .btn-edit {
            background: var(--amber-light);
            color: var(--amber);
        }
        .btn-edit:hover {
            background: var(--amber);
            color: var(--white);
        }
        .btn-delete {
            background: var(--red-light);
            color: var(--red);
            margin-left: 6px;
        }
        .btn-delete:hover {
            background: var(--red);
            color: var(--white);
        }
        
        .badge {
            display: inline-block; padding: 5px 12px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .badge-available { background: var(--green-light); color: var(--green); }
        .badge-busy { background: var(--amber-light); color: var(--amber); }

        /* ── MODAL OVERLAY ── */
        .modal-overlay { 
            display: none; position: fixed; top:0; left:0; width: 100%; height: 100%; 
            background: rgba(26, 18, 8, 0.6); backdrop-filter: blur(4px);
            justify-content: center; align-items: center; z-index: 2000; 
            opacity: 0; transition: opacity 0.3s;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        
        .modal-content {
            background: var(--white);
            padding: 35px 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            transform: translateY(20px);
            transition: transform 0.3s;
        }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        
        .modal-content h2 {
            font-family: 'Playfair Display', serif; margin-top: 0; margin-bottom: 25px; color: var(--dark);
        }

        .auth-form label {
            display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted);
            margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .auth-form input, .auth-form select {
            width: 100%; padding: 12px 14px; margin-bottom: 20px; border: 1px solid var(--border);
            border-radius: 8px; font-family: inherit; font-size: 0.95rem; color: var(--dark);
            background: var(--white); transition: all 0.2s; box-sizing: border-box;
        }
        .auth-form input:focus, .auth-form select:focus {
            outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
        }

        .btn-cancel {
            width: 100%; margin-top: 10px; padding: 10px; background: none; border: none;
            color: var(--text-muted); font-weight: 600; cursor: pointer; transition: color 0.2s;
            font-family: inherit; font-size: 0.9rem;
        }
        .btn-cancel:hover { color: var(--red); }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; }
            .brand-name, .nav-links a span:not(:first-child) { display: none; }
            .brand { justify-content: center; padding: 25px 10px; }
            .nav-links a { justify-content: center; padding: 14px; font-size: 1.2rem; }
            .main-content { margin-left: 80px; width: calc(100% - 80px); padding: 30px 25px; }
        }
    </style>
</head>
<body>
    
   <aside class="sidebar">
    <div class="brand">
        <img src="../../uploads/logo.png" alt="Sunflower Logo" style="height: 40px; width: 40px; object-fit: contain; border-radius: 50%;">
        <span class="brand-name">SUNFLOWER</span>
    </div>

    <nav class="nav-links">
        <a href="/SMSRMS/admin/dashboard.php">Dashboard</a>
        <a href="/SMSRMS/admin/bookings.php">Manage Reservation</a>
        <a href="/SMSRMS/admin/assign/massagers.php" class="active">Manage Massagers</a>
        <a href="/SMSRMS/admin/service.php">Manage Services</a>
        <a href="/SMSRMS/admin/transactions.php">Manage Payments</a>
        <a href="/SMSRMS/admin/availability.php">Manage Availability</a>
        <a href="/SMSRMS/admin/feedback.php">Manage Feedback</a>
        <a href="/SMSRMS/admin/reports.php">Generate Reports</a>
        <a href="/SMSRMS/auth/logout.php" class="logout">
            <span>🚪</span>
            <span>Logout</span>
        </a>
    </nav>
</aside>

    <main class="main-content">
        <div class="welcome">
            <h1>Staff Directory</h1>
            <p class="welcome-desc">Manage your therapist roster, update contact details, and adjust active status.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Active Massagers</h2>
                <button onclick="openAddModal()" class="btn">+ Add New Massager</button>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($massagers) === 0): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">No massagers added yet.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach ($massagers as $m): ?>
                        <tr>
                            <td><strong style="color: var(--dark);"><?= htmlspecialchars($m['name']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($m['phone']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td>
                                <?php if($m['status'] == 1): ?>
                                    <span class="badge badge-available">Available</span>
                                <?php else: ?>
                                    <span class="badge badge-busy">Busy / Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-action btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($m)) ?>)">Edit</button>
                                <a href="delete_massager.php?id=<?= $m['id'] ?>" class="btn-action btn-delete">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="massagerModal">
        <div class="modal-content">
            <h2 id="modalTitle">Add New Massager</h2>
            <form class="auth-form" method="POST" action="massagers.php">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="massagerId">
                
                <label>Full Name</label>
                <input type="text" name="name" id="name" placeholder="e.g. Jane Doe" required>
                
                <label>Phone Number</label>
                <input type="text" name="phone" id="phone" placeholder="e.g. 0123456789" required>
                
                <label>Email Address</label>
                <input type="email" name="email" id="email" placeholder="jane@sunflower.com" required>
                
                <label>System Status</label>
                <select name="status" id="status">
                    <option value="1">Available (Active)</option>
                    <option value="0">Busy (Inactive)</option>
                </select>
                
                <button type="submit" class="btn" style="width:100%;">Save Details</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('massagerModal');
        
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add New Massager';
            document.getElementById('formAction').value = 'add';
            document.getElementById('massagerId').value = '';
            document.getElementById('name').value = '';
            document.getElementById('phone').value = '';
            document.getElementById('email').value = '';
            document.getElementById('status').value = '1';
            
            modal.style.display = 'flex'; // Make visible first
            setTimeout(() => { modal.classList.add('active'); }, 10); // Then fade in
        }
        
        function openEditModal(data) {
            document.getElementById('modalTitle').innerText = 'Edit Profile';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('massagerId').value = data.id;
            document.getElementById('name').value = data.name;
            document.getElementById('phone').value = data.phone;
            document.getElementById('email').value = data.email;
            document.getElementById('status').value = data.status;
            
            modal.style.display = 'flex';
            setTimeout(() => { modal.classList.add('active'); }, 10);
        }
        
        function closeModal() { 
            modal.classList.remove('active'); 
            setTimeout(() => { modal.style.display = 'none'; }, 300); // Wait for fade out
        }
        
        window.onclick = (e) => { 
            if (e.target == modal) closeModal(); 
        }
    </script>
</body>
</html>