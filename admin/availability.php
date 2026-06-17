<?php
// SMSRMS/admin/availability.php
session_start();
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fixed slots — must match get_synced_slots.php master_slots
$fixed_slots = [
    ['start' => '09:00', 'end' => '11:00', 'label' => '09:00 AM – 11:00 AM'],
    ['start' => '11:00', 'end' => '13:00', 'label' => '11:00 AM – 01:00 PM'],
    ['start' => '14:00', 'end' => '16:00', 'label' => '02:00 PM – 04:00 PM'],
    ['start' => '16:00', 'end' => '18:00', 'label' => '04:00 PM – 06:00 PM'],
];

// =====================================================================
// AJAX HANDLER
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Security token expired. Please refresh the page.']);
        exit;
    }

    $action = $_POST['action'];

    // ------------------------------------------------------------------
    // add_slot
    // ------------------------------------------------------------------
    if ($action === 'add_slot') {
        $massager_id = filter_input(INPUT_POST, 'massager_id', FILTER_VALIDATE_INT);
        $date        = htmlspecialchars(trim($_POST['available_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $start       = htmlspecialchars(trim($_POST['available_start'] ?? ''), ENT_QUOTES, 'UTF-8');
        $end         = htmlspecialchars(trim($_POST['available_end']   ?? ''), ENT_QUOTES, 'UTF-8');
        $type        = htmlspecialchars(trim($_POST['slot_type']       ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$massager_id) { echo json_encode(['status' => 'error', 'message' => 'Please select a massager.']); exit; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']); exit; }
        if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) { echo json_encode(['status' => 'error', 'message' => 'Please select a valid time slot.']); exit; }
        if (!in_array($type, ['available', 'blocked'], true)) { echo json_encode(['status' => 'error', 'message' => 'Invalid slot type.']); exit; }
        if ($start >= $end) { echo json_encode(['status' => 'error', 'message' => 'Start time must be before end time.']); exit; }

        $is_past = $date < date('Y-m-d');

        $overlap = $conn->prepare("
            SELECT id FROM massager_availability
            WHERE massager_id = ? AND available_date = ?
              AND available_start < ? AND available_end > ?
        ");
        $overlap->execute([$massager_id, $date, $end . ':00', $start . ':00']);
        if ($overlap->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This slot overlaps with an existing slot for this massager.']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO massager_availability (massager_id, available_date, available_start, available_end, slot_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute([$massager_id, $date, $start . ':00', $end . ':00', $type]);
        $newId   = $conn->lastInsertId();

        if ($success) {
            $nameStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $nameStmt->execute([$massager_id]);
            $massager_name = $nameStmt->fetchColumn();

            echo json_encode([
                'status'      => 'success',
                'message'     => 'Slot added!' . ($is_past ? ' (Note: date is in the past)' : ''),
                'is_past'     => $is_past,
                'slot' => [
                    'id'             => $newId,
                    'massager_id'    => $massager_id,
                    'massager_name'  => $massager_name,
                    'available_date' => $date,
                    'available_start'=> $start . ':00',
                    'available_end'  => $end   . ':00',
                    'slot_type'      => $type,
                    'display_type'   => $type,
                ],
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
        }
        exit;
    }

    // ------------------------------------------------------------------
    // delete_slot
    // ------------------------------------------------------------------
    if ($action === 'delete_slot') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'Invalid slot ID.']); exit; }

        $bookingCheck = $conn->prepare("
            SELECT COUNT(*) FROM massager_availability ma
            JOIN bookings b ON b.massager_id = ma.massager_id
                AND b.booking_date = ma.available_date
                AND b.booking_time >= ma.available_start
                AND b.booking_time <  ma.available_end
                AND b.status NOT IN ('cancelled')
                AND b.payment_status NOT IN ('failed', 'refunded')
            WHERE ma.id = ?
        ");
        $bookingCheck->execute([$id]);
        if ($bookingCheck->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot remove — a customer booking exists in this slot. Cancel the booking first.']);
            exit;
        }

        $stmt    = $conn->prepare("DELETE FROM massager_availability WHERE id = ?");
        $success = $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Slot not found.']);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Slot removed.']);
        }
        exit;
    }

    // ------------------------------------------------------------------
    // toggle_slot_type
    // ------------------------------------------------------------------
    if ($action === 'toggle_slot_type') {
        $id      = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $newType = htmlspecialchars(trim($_POST['new_type'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$id || !in_array($newType, ['available', 'blocked'], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE massager_availability SET slot_type = ? WHERE id = ?");
        $stmt->execute([$newType, $id]);
        echo json_encode(['status' => 'success', 'message' => 'Slot updated to ' . $newType . '.', 'new_type' => $newType]);
        exit;
    }

    // ------------------------------------------------------------------
    // get_slots
    // ------------------------------------------------------------------
    if ($action === 'get_slots') {
        $massager_id = !empty($_POST['massager_id']) ? (int)$_POST['massager_id'] : null;
        $filter_date = $_POST['filter_date'] ?? null;

        if ($filter_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
            $filter_date = null;
        }

        $sql = "
            SELECT a.*,
                   u.username AS massager_name,
                   CASE
                       WHEN EXISTS (
                           SELECT 1 FROM bookings b
                           WHERE b.massager_id = a.massager_id
                             AND DATE(b.booking_date) = a.available_date
                             AND b.booking_time >= a.available_start
                             AND b.booking_time <  a.available_end
                             AND b.status NOT IN ('cancelled')
                             AND b.payment_status NOT IN ('failed','refunded')
                       ) THEN 'booked'
                       ELSE a.slot_type
                   END AS display_type,
                   a.block_reason
            FROM massager_availability a
            JOIN users u ON a.massager_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if ($massager_id) { $sql .= " AND a.massager_id = ?"; $params[] = $massager_id; }
        if ($filter_date) { $sql .= " AND a.available_date = ?"; $params[] = $filter_date; }
        $sql .= " ORDER BY a.available_date ASC, a.available_start ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ------------------------------------------------------------------
    // get_calendar_events
    // ------------------------------------------------------------------
    if ($action === 'get_calendar_events') {
        $massager_id = !empty($_POST['massager_id']) ? (int)$_POST['massager_id'] : null;
        $date_from   = htmlspecialchars(trim($_POST['date_from'] ?? ''), ENT_QUOTES, 'UTF-8');
        $date_to     = htmlspecialchars(trim($_POST['date_to']   ?? ''), ENT_QUOTES, 'UTF-8');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid date range.']);
            exit;
        }

        $sql = "
            SELECT a.id, a.massager_id, a.available_date, a.available_start, a.available_end, a.slot_type, a.block_reason,
                   u.username AS massager_name,
                   CASE
                       WHEN EXISTS (
                           SELECT 1 FROM bookings b
                           WHERE b.massager_id = a.massager_id
                             AND DATE(b.booking_date) = a.available_date
                             AND b.booking_time >= a.available_start
                             AND b.booking_time <  a.available_end
                             AND b.status NOT IN ('cancelled')
                             AND b.payment_status NOT IN ('failed','refunded')
                       ) THEN 'booked'
                       ELSE a.slot_type
                   END AS display_type
            FROM massager_availability a
            JOIN users u ON a.massager_id = u.id
            WHERE a.available_date >= ? AND a.available_date <= ?
        ";
        $params = [$date_from, $date_to];
        if ($massager_id) { $sql .= " AND a.massager_id = ?"; $params[] = $massager_id; }
        $sql .= " ORDER BY a.available_date, a.available_start";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $r) {
            $color = match($r['display_type']) {
                'booked'  => '#2980b9',
                'blocked' => '#c0392b',
                default   => '#27ae60',
            };
            $statusIcon = match($r['display_type']) {
                'booked'  => '📘',
                'blocked' => '🔴',
                default   => '🟢',
            };
            $timeLabel = substr($r['available_start'], 0, 5);

            $events[] = [
                'id'              => $r['id'],
                'title'           => $timeLabel . ' ' . $r['massager_name'] . ' ' . $statusIcon,
                'start'           => $r['available_date'] . 'T' . $r['available_start'],
                'end'             => $r['available_date'] . 'T' . $r['available_end'],
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'textColor'       => '#fff',
                'extendedProps'   => [
                    'massager_id'   => $r['massager_id'],
                    'massager_name' => $r['massager_name'],
                    'slot_id'       => $r['id'],
                    'display_type'  => $r['display_type'],
                    'slot_type'     => $r['slot_type'],
                    'time_label'    => $timeLabel,
                    'block_reason'  => $r['block_reason'] ?? '',
                ],
            ];
        }
        echo json_encode(['status' => 'success', 'events' => $events]);
        exit;
    }

    // ------------------------------------------------------------------
    // bulk_add
    // ------------------------------------------------------------------
    if ($action === 'bulk_add') {
        $massager_id  = filter_input(INPUT_POST, 'massager_id', FILTER_VALIDATE_INT);
        $date_from    = htmlspecialchars(trim($_POST['date_from'] ?? ''), ENT_QUOTES, 'UTF-8');
        $date_to      = htmlspecialchars(trim($_POST['date_to']   ?? ''), ENT_QUOTES, 'UTF-8');
        $slot_type    = htmlspecialchars(trim($_POST['slot_type']  ?? 'available'), ENT_QUOTES, 'UTF-8');
        $slots_raw    = $_POST['slots'] ?? [];

        if (!$massager_id) { echo json_encode(['status' => 'error', 'message' => 'Please select a massager.']); exit; }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) { echo json_encode(['status' => 'error', 'message' => 'Invalid date range.']); exit; }
        if ($date_from > $date_to) { echo json_encode(['status' => 'error', 'message' => 'Start date must be before end date.']); exit; }
        if (!in_array($slot_type, ['available', 'blocked'], true)) { echo json_encode(['status' => 'error', 'message' => 'Invalid slot type.']); exit; }

        $slot_pairs = [];
        foreach ($slots_raw as $s) {
            $parts = explode('|', $s);
            if (count($parts) === 2 && preg_match('/^\d{2}:\d{2}$/', $parts[0]) && preg_match('/^\d{2}:\d{2}$/', $parts[1])) {
                $slot_pairs[] = [$parts[0] . ':00', $parts[1] . ':00'];
            }
        }
        if (empty($slot_pairs)) { echo json_encode(['status' => 'error', 'message' => 'Please select at least one time slot.']); exit; }

        $insert = $conn->prepare("
            INSERT IGNORE INTO massager_availability (massager_id, available_date, available_start, available_end, slot_type)
            SELECT ?, ?, ?, ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM massager_availability
                WHERE massager_id = ? AND available_date = ?
                  AND available_start < ? AND available_end > ?
            )
        ");

        $added = 0;
        $skipped = 0;
        $current = new DateTime($date_from);
        $end_dt  = new DateTime($date_to);

        while ($current <= $end_dt) {
            $dateStr = $current->format('Y-m-d');
            foreach ($slot_pairs as [$s, $e]) {
                $insert->execute([$massager_id, $dateStr, $s, $e, $slot_type, $massager_id, $dateStr, $e, $s]);
                if ($insert->rowCount() > 0) { $added++; } else { $skipped++; }
            }
            $current->modify('+1 day');
        }

        echo json_encode([
            'status'  => 'success',
            'message' => "Done! Added {$added} slot(s)" . ($skipped > 0 ? ", skipped {$skipped} (overlap)" : '') . '.',
            'added'   => $added,
            'skipped' => $skipped,
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

$massagers = $conn->query("SELECT id, username FROM users WHERE role='massager' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <style>
        :root {
            /* Soft Pastel / Glassmorphism Palette */
            --gold:        #c9a84c;
            --gold-light:  #f4d03f;
            --gold-pale:   #fcfbf5; /* Soft pastel background */
            --dark:        #1a1208;
            --text:        #3d2e0e;
            --text-muted:  #8a7355;
            --border:      rgba(232, 217, 181, 0.6);
            --white:       rgba(255, 255, 255, 0.7); /* Translucent glass base */
            --glass-blur:  blur(12px);
            --green:       #2d6a4f;
            --green-light: #d8f3dc;
            --red:         #c0392b;
            --red-light:   #fdecea;
            --blue:        #2980b9;
            --blue-light:  #d6eaf8;
            --amber:       #b7791f;
            --amber-light: #fef3c7;
            --card-shadow: 0 8px 32px rgba(201,168,76,0.08);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--gold-pale); color:var(--text); display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar { width:260px; background:var(--dark); position:fixed; height:100vh; left:0; top:0; display:flex; flex-direction:column; box-shadow:4px 0 24px rgba(0,0,0,.15); z-index:100; }
        .brand { padding: 30px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; }
        .brand-name { font-family:'Playfair Display',serif; font-size:1.25rem; color:var(--gold-light); letter-spacing:2px; }
        .nav-links { display:flex; flex-direction:column; gap:6px; padding:0 16px; flex:1; }
        .nav-links a { text-decoration:none; font-size:0.95rem; font-weight:500; color:#c4b08a; padding:12px 18px; border-radius:8px; transition:all .2s; display:flex; align-items:center; gap:12px; }
        .nav-links a:hover,.nav-links a.active { color:var(--gold-light); background:rgba(244,208,63,.08); }
        .nav-links a.logout { color:#e57373; margin-top:auto; margin-bottom:24px; font-weight:600; transition:all .3s ease; }
        .nav-links a.logout:hover { background:rgba(229,115,115,.1); color:#ff8a8a; }

        /* Main Content */
        .main-content { margin-left:260px; padding:36px 44px; flex-grow:1; width:calc(100% - 260px); }
        .page-header { margin-bottom:28px; }
        .page-header h1 { font-family:'Playfair Display',serif; font-size:2rem; color:var(--dark); }
        .page-header p { font-size:0.9rem; color:var(--text-muted); margin-top:5px; }

        /* Master Tabs (The new layout structure) */
        .master-tabs { display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 2px solid var(--border); padding-bottom: 12px; overflow-x: auto; }
        .master-tab-btn {
            padding: 10px 20px; border: none; background: rgba(255,255,255,0.4); 
            font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 500; 
            color: var(--text-muted); cursor: pointer; border-radius: 8px; 
            transition: all .3s; backdrop-filter: var(--glass-blur);
            border: 1px solid transparent;
        }
        .master-tab-btn:hover { background: rgba(255,255,255,0.8); }
        .master-tab-btn.active {
            background: var(--white); color: var(--dark);
            border: 1px solid var(--gold-light);
            box-shadow: 0 4px 12px rgba(201,168,76,0.15);
        }
        .main-tab-pane { display: none; animation: fadeIn 0.3s ease; }
        .main-tab-pane.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Glassmorphism Cards */
        .card { 
            background: var(--white); 
            backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid rgba(255, 255, 255, 0.8); 
            border-radius: 16px; box-shadow: var(--card-shadow); margin-bottom:24px; overflow:hidden; 
        }
        .card-header { padding:18px 22px 14px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .card-header h2 { font-family:'Playfair Display',serif; font-size:1.1rem; color:var(--dark); }
        .card-body { padding:22px; }

        /* Form */
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-size:0.74rem; font-weight:600; text-transform:uppercase; letter-spacing:.8px; color:var(--text-muted); margin-bottom:6px; }
        .form-group input, .form-group select {
            width:100%; padding:9px 13px;
            border:1px solid var(--border); border-radius:8px;
            font-family:'DM Sans',sans-serif; font-size:0.9rem; color:var(--text);
            background: rgba(255,255,255,0.8); outline:none; transition:border-color .2s, box-shadow .2s;
        }
        .form-group input:focus, .form-group select:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(201,168,76,.15); background: #fff; }

        /* Slot type toggle */
        .type-toggle { display:flex; gap:8px; }
        .type-opt { flex:1; padding:9px; text-align:center; border-radius:8px; cursor:pointer; font-size:0.85rem; font-weight:500; border:1.5px solid var(--border); transition:all .2s; user-select:none; }
        .type-opt.avail.selected  { background:var(--green-light); border-color:var(--green); color:var(--green); }
        .type-opt.block.selected  { background:var(--red-light); border-color:var(--red); color:var(--red); }
        .type-opt:not(.selected)  { background:rgba(255,255,255,0.6); color:var(--text-muted); }

        /* Slot list */
        .slot-item { display:flex; justify-content:space-between; align-items:center; padding:13px 16px; border:1px solid var(--border); border-radius:10px; margin-bottom:8px; background:rgba(255,255,255,0.6); transition:box-shadow .2s; backdrop-filter: blur(4px); }
        .slot-item:hover { box-shadow:0 2px 10px rgba(201,168,76,.12); background:#fff; }
        .slot-left { display:flex; align-items:center; gap:12px; }
        .slot-indicator { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .indicator-available { background:var(--green); }
        .indicator-blocked   { background:var(--red); }
        .indicator-booked    { background:var(--blue); }
        .slot-time  { font-weight:600; font-size:0.9rem; color:var(--dark); }
        .slot-date  { font-size:0.78rem; color:var(--text-muted); margin-top:2px; }
        .slot-massager { font-size:0.78rem; color:var(--text-muted); }
        .slot-right { display:flex; align-items:center; gap:8px; }

        /* Badges */
        .badge { padding:4px 11px; border-radius:20px; font-size:0.74rem; font-weight:600; }
        .badge-available { background:var(--green-light); color:var(--green); }
        .badge-blocked   { background:var(--red-light); color:var(--red); }
        .badge-booked    { background:var(--blue-light); color:var(--blue); }

        /* Buttons */
        .btn { padding:9px 20px; border:none; border-radius:8px; cursor:pointer; font-family:'DM Sans',sans-serif; font-weight:500; font-size:0.88rem; transition:all .2s; }
        .btn-gold    { background:var(--gold-light); color:var(--dark); }
        .btn-gold:hover { background:var(--gold); }
        .btn-remove  { background:var(--red-light); color:var(--red); border:1px solid transparent; padding:5px 12px; border-radius:6px; font-size:0.8rem; cursor:pointer; transition:all .2s; }
        .btn-remove:hover { background:var(--red); color:#fff; }
        .btn-toggle  { background:var(--amber-light); color:var(--amber); border:1px solid transparent; padding:5px 12px; border-radius:6px; font-size:0.8rem; cursor:pointer; transition:all .2s; }
        .btn-toggle:hover { background:var(--amber); color:#fff; }
        .btn-sm      { padding:7px 14px; font-size:0.82rem; }

        /* Filter bar */
        .filter-bar { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-bottom:20px; }
        .filter-bar .form-group { margin-bottom:0; min-width:180px; }

        /* Slot date group */
        .slot-date-heading { font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.8px; color:var(--text-muted); margin:18px 0 8px; padding-left:4px; }

        /* Legend */
        .legend { display:flex; gap:18px; font-size:0.8rem; color:var(--text-muted); flex-wrap:wrap; }
        .legend-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:5px; vertical-align:middle; }

        /* Checkbox grid for bulk */
        .slot-checkboxes { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .slot-check-label { display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid var(--border); border-radius:8px; cursor:pointer; font-size:0.85rem; transition:background .2s; background: rgba(255,255,255,0.5); }
        .slot-check-label:has(input:checked) { background:rgba(244,208,63,0.15); border-color:var(--gold); }
        .slot-check-label input { cursor:pointer; }

        /* Sub-Tab strip (for All Slots section) */
        .tab-strip { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:20px; }
        .tab-btn { padding:10px 20px; border:none; background:none; font-family:'DM Sans',sans-serif; font-size:0.88rem; font-weight:500; color:var(--text-muted); cursor:pointer; border-bottom:2.5px solid transparent; margin-bottom:-2px; transition:all .2s; }
        .tab-btn.active { color:var(--dark); border-bottom-color:var(--gold); }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; }

        /* Empty state */
        .empty-state { text-align:center; padding:40px 20px; color:var(--text-muted); }
        .empty-state .icon { font-size:2.5rem; margin-bottom:10px; opacity: 0.8; }

        /* Calendar overrides */
        .fc { background: rgba(255,255,255,0.4); padding: 15px; border-radius: 12px; }
        .fc .fc-toolbar-title { font-family:'Playfair Display',serif; font-size:1.1rem; color:var(--dark); }
        .fc .fc-button-primary { background:var(--gold-light) !important; border-color:var(--gold) !important; color:var(--dark) !important; font-family:'DM Sans',sans-serif; font-size:0.82rem; }
        .fc .fc-button-primary:hover { background:var(--gold) !important; }
        .fc .fc-daygrid-event { font-size:0.72rem; }
        .fc-event-title { font-size:0.7rem; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: rgba(232, 217, 181, 0.4); }


        /* 1. The main container */
.legend {
    display: flex;
    align-items: center;      /* Vertically aligns the items in the row */
    gap: 16px;                /* Adds even spacing between Available, Blocked, and Booked */
    
    /* Choose ONE of the following to align the whole block: */
    justify-content: flex-start; /* Aligns left (Default) */
    /* justify-content: center; */   /* Centers the legend */
    /* justify-content: flex-end; */ /* Aligns right */
}

/* 2. The individual items (Available, Blocked, Booked) */
.legend > span {
    display: flex;
    align-items: center;      /* Perfectly aligns the emoji and text vertically */
    gap: 6px;                 /* Space between the emoji and the word */
    font-size: 14px;          /* Adjust to your preference */
    color: var(--text-muted, #555); /* Optional: text color */
}

/* 3. The emoji/dot wrapper */
.legend-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;              /* Standardizes the width */
    height: 24px;             /* Standardizes the height */
    border-radius: 50%;       /* Makes the background color a perfect circle */
    font-size: 12px;          /* Controls emoji size */
}


        /* Past-date warning */
        .past-warn { background:var(--amber-light); border:1px solid #d4a017; color:var(--amber); padding:8px 14px; border-radius:8px; font-size:0.82rem; margin-top:8px; display:none; }
        
        .max-w-md { max-width: 600px; }

        /* Blocked reasons panel */
        .reason-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
        .reason-table thead tr { border-bottom:2px solid var(--border); }
        .reason-table th { text-align:left; padding:8px 10px; font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.7px; color:var(--text-muted); }
        .reason-table td { padding:10px 10px; border-bottom:1px solid rgba(232,217,181,0.4); vertical-align:middle; }
        .reason-table tr:last-child td { border-bottom:none; }
        .reason-table tr:hover td { background:rgba(255,255,255,0.5); }
        .reason-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:600; background:#fff3e0; color:#b7791f; border:1px solid #f0c060; }
        .reason-none { color:var(--text-muted); font-style:italic; font-size:0.8rem; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
      <img src="../uploads/logo.png" alt="Sunflower Logo" style="height: 40px; width: 40px; object-fit: contain; border-radius: 50%;">
        <span class="brand-name">SUNFLOWER</span>
    </div>
    <nav class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="bookings.php">Manage Reservations</a>
        <a href="assign/massagers.php">Manage Massagers</a>
        <a href="service.php">Manage Services</a>
        <a href="transactions.php">Manage Payments</a>
        <a href="availability.php" class="active">Manage Availability</a>
        <a href="feedback.php">Manage Feedback</a>
        <a href="reports.php">Generate Reports</a>
        
        <a href="../auth/logout.php" class="logout"><span>🚪</span> <span>Logout</span></a>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <h1>Manage Availability</h1>
        <p>View and control all massager schedules. Changes sync immediately with customer booking.</p>
    </div>

    <div class="master-tabs">
        <button class="master-tab-btn active" onclick="switchMainTab(this, 'overview')">Schedule Overview</button>
        <button class="master-tab-btn" onclick="switchMainTab(this, 'add')">Add/Unavailable Slot</button>
        <button class="master-tab-btn" onclick="switchMainTab(this, 'bulk')">Bulk Slot Generator</button>
        <button class="master-tab-btn" onclick="switchMainTab(this, 'all')">All Slots</button>
    </div>

    <div id="main-tab-overview" class="main-tab-pane active">
        <div class="card">
            <div class="card-header">
                <h2>Schedule Overview</h2>
                <div class="legend">
                    <span><span class="legend-dot" >🟢</span>Available</span>
                    <span><span class="legend-dot" ">🔴</span>Unavailable</span>
                    <span><span class="legend-dot" >📘</span>Booked</span>
                </div>
            </div>
            <div class="card-body">
                <div class="form-group" style="max-width:260px; margin-bottom:16px;">
                    <label>Filter calendar by massager</label>
                    <select id="calMassagerFilter" onchange="reloadCalendarEvents()">
                        <option value="">All Massagers</option>
                        <?php foreach ($massagers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="calendar"></div>
            </div>
        </div>

        <!-- Blocked Slot Reasons Panel -->
        <div class="card" id="blockedReasonsCard" style="margin-top:0;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                <h2>🔴 Blocked Slots This Month</h2>
                <span id="blockedReasonsMonth" style="font-size:0.82rem;color:var(--text-muted);"></span>
            </div>
            <div class="card-body" style="padding:16px 22px;">
                <div id="blockedReasonsList">
                    <div class="empty-state"><div class="icon">⏳</div><p>Loading…</p></div>
                </div>
            </div>
        </div>
    </div>

    <div id="main-tab-add" class="main-tab-pane">
        <div class="card max-w-md">
            <div class="card-header"><h2>Add / Block Slot</h2></div>
            <div class="card-body">
                <form id="addForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="add_slot">
                    <input type="hidden" name="available_start" id="startTime">
                    <input type="hidden" name="available_end"   id="endTime">

                    <div class="form-group">
                        <label>Massager</label>
                        <select name="massager_id" id="addMassager" required>
                            <option value="">-- Select Massager --</option>
                            <?php foreach ($massagers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="available_date" id="addDate" required min="<?= date('Y-m-d') ?>">
                        <div class="past-warn" id="pastWarn">⚠️ This date is in the past.</div>
                    </div>

                    <div class="form-group">
                        <label>Time Slot</label>
                        <select id="slotOption" required>
                            <option value="">-- Select Time Slot --</option>
                            <?php foreach ($fixed_slots as $s): ?>
                                <option value="<?= $s['start'] ?>|<?= $s['end'] ?>"><?= $s['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Type</label>
                        <div class="type-toggle">
                            <div class="type-opt avail selected" data-val="available" onclick="selectType(this)">✅ Available</div>
                            <div class="type-opt block"          data-val="blocked"   onclick="selectType(this)">🚫 Unavailable</div>
                        </div>
                        <input type="hidden" name="slot_type" id="slotTypeInput" value="available">
                    </div>

                    <button type="submit" class="btn btn-gold" style="width:100%; margin-top: 10px;">Add Slot</button>
                </form>
            </div>
        </div>
    </div>

    <div id="main-tab-bulk" class="main-tab-pane">
        <div class="card max-w-md">
            <div class="card-header"><h2>⚡ Bulk Slot Generator</h2></div>
            <div class="card-body">
                <form id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="bulk_add">

                    <div class="form-group">
                        <label>Massager</label>
                        <select name="massager_id" required>
                            <option value="">-- Select Massager --</option>
                            <?php foreach ($massagers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label>From Date</label>
                            <input type="date" name="date_from" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>To Date</label>
                            <input type="date" name="date_to" required min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Time Slots to Add</label>
                        <div class="slot-checkboxes">
                            <?php foreach ($fixed_slots as $s): ?>
                                <label class="slot-check-label">
                                    <input type="checkbox" name="slots[]" value="<?= $s['start'] ?>|<?= $s['end'] ?>" checked>
                                    <?= $s['label'] ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Type</label>
                        <select name="slot_type">
                            <option value="available">Available</option>
                            <option value="blocked">Unavailable</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-gold" style="width:100%; margin-top: 10px;">Generate Slots</button>
                </form>
            </div>
        </div>
    </div>

    <div id="main-tab-all" class="main-tab-pane">
        <div class="card">
            <div class="card-header">
                <h2>All Slots</h2>
                <span class="legend" style="font-size:0.74rem;">Booked slots cannot be deleted directly</span>
            </div>
            <div class="card-body">
                <div class="filter-bar">
                    <div class="form-group" style="flex:1;">
                        <label>Massager</label>
                        <select id="massagerSelect" onchange="loadSlots()">
                            <option value="">All Massagers</option>
                            <?php foreach ($massagers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Date</label>
                        <input type="date" id="filterDate" onchange="loadSlots()">
                    </div>
                    <button class="btn btn-sm" style="background:var(--border); color:var(--text-muted); margin-bottom:1px;" onclick="clearFilters()">Clear</button>
                </div>

                <div class="tab-strip">
                    <button class="tab-btn active" onclick="switchSubTab(this,'list')">Slot List</button>
                    <button class="tab-btn" onclick="switchSubTab(this,'summary')">Summary</button>
                </div>

                <div id="sub-tab-list" class="tab-pane active">
                    <div id="slotsContainer">
                        <div class="empty-state"><div class="icon">📋</div><p>Loading slots…</p></div>
                    </div>
                </div>

                <div id="sub-tab-summary" class="tab-pane">
                    <div id="summaryContainer">
                        <div class="empty-state"><div class="icon">📊</div><p>Loading summary…</p></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</main>

<script>
const CSRF  = <?= json_encode($csrf_token) ?>;
const TODAY = '<?= date('Y-m-d') ?>';

// ── Master Tab Switching Logic ────────────────────────────
function switchMainTab(btn, tabId) {
    document.querySelectorAll('.master-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.main-tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('main-tab-' + tabId).classList.add('active');

    // CRITICAL: FullCalendar sizes incorrectly if initialized while display:none. 
    // This forces the calendar to recalculate its dimensions when its tab becomes visible.
    if (tabId === 'overview' && calendar) {
        setTimeout(() => calendar.updateSize(), 50);
    }
}

// ── Toast ────────────────────────────────────────────────
const Toast = Swal.mixin({
    toast: true, position: 'top-end',
    showConfirmButton: false, timer: 3200, timerProgressBar: true,
});

// ── Helpers ──────────────────────────────────────────────
function fmt12(t) {
    const [h, m] = t.split(':').map(Number);
    return `${String(h % 12 || 12).padStart(2,'0')}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
}
function fmtDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-MY', {weekday:'long', day:'numeric', month:'long', year:'numeric'});
}

// ── Type toggle (add form) ────────────────────────────────
function selectType(el) {
    document.querySelectorAll('.type-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('slotTypeInput').value = el.dataset.val;
}

// ── Slot option change ────────────────────────────────────
document.getElementById('slotOption').addEventListener('change', function () {
    if (!this.value) { document.getElementById('startTime').value = ''; document.getElementById('endTime').value = ''; return; }
    const [start, end] = this.value.split('|');
    document.getElementById('startTime').value = start;
    document.getElementById('endTime').value   = end;
});

// ── Past date warning ─────────────────────────────────────
document.getElementById('addDate').addEventListener('change', function () {
    document.getElementById('pastWarn').style.display = (this.value && this.value < TODAY) ? 'block' : 'none';
});

// ── Sub-Tab switch ────────────────────────────────────────
function switchSubTab(btn, id) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sub-tab-' + id).classList.add('active');
    if (id === 'summary') buildSummary();
}

// ── FullCalendar ──────────────────────────────────────────
let calendar;
function initCalendar() {
    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
        height: 520,
        eventMaxStack: 3,
        navLinks: true,
        nowIndicator: true,
        events: fetchCalendarEvents,

        datesSet: function(info) {
            // fires on every month/week/view navigation
            renderBlockedReasons(info.start, info.end);
        },

        eventContent: function (arg) {
            const p = arg.event.extendedProps;
            const icon = p.display_type === 'booked' ? '📘' : (p.display_type === 'blocked' ? '🔴' : '🟢');
            const reasonHint = (p.display_type === 'blocked' && p.block_reason)
                ? ` <span style="opacity:0.85;font-style:italic;">(${reasonLabel(p.block_reason)})</span>`
                : '';
            return { html: `<span style="padding:0 4px;font-size:0.7rem;">${icon} ${p.time_label} ${p.massager_name}${reasonHint}</span>` };
        },

        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const p  = info.event.extendedProps;
            const dt = p.display_type;
            const dateStr = info.event.startStr.substring(0, 10);
            const timeStr = p.time_label + ' – ' + (info.event.endStr ? info.event.endStr.substring(11,16) : '');

            const statusColors = { booked:'#2980b9', blocked:'#c0392b', available:'#27ae60' };
            const statusLabel  = dt.charAt(0).toUpperCase() + dt.slice(1);
            const reasonRow = (dt === 'blocked' && p.block_reason)
                ? `<div style="margin-top:6px;"><strong>Reason:</strong> <span style="background:#fff3e0;color:#b7791f;padding:2px 8px;border-radius:10px;font-size:0.85rem;">${reasonLabel(p.block_reason)}</span></div>`
                : '';

            Swal.fire({
                title: '📋 Slot Details',
                html: `
                    <div style="text-align:left;font-size:.9rem;line-height:1.8;">
                        <div><strong>Massager:</strong> ${p.massager_name}</div>
                        <div><strong>Date:</strong> ${new Date(dateStr+'T00:00:00').toLocaleDateString('en-MY',{weekday:'long',day:'numeric',month:'long',year:'numeric'})}</div>
                        <div><strong>Time:</strong> ${timeStr}</div>
                        <div style="margin-top:8px;">
                            <span style="display:inline-block;background:${statusColors[dt]};color:#fff;padding:3px 12px;border-radius:20px;font-size:.8rem;font-weight:600;">${statusLabel}</span>
                        </div>
                        ${reasonRow}
                        ${dt === 'booked' ? '<p style="margin-top:10px;font-size:.8rem;color:#888;">This slot has an active booking and cannot be deleted or toggled.</p>' : ''}
                    </div>
                `,
                showCancelButton: true,
                showDenyButton:   dt !== 'booked',
                showConfirmButton: dt !== 'booked',
                confirmButtonText: dt === 'blocked' ? '🔓 Mark Available' : '🚫 Block Slot',
                denyButtonText:    '🗑 Delete Slot',
                cancelButtonText:  'Close',
                confirmButtonColor: dt === 'blocked' ? '#27ae60' : '#e67e22',
                denyButtonColor:    '#c0392b',
            }).then(result => {
                if (result.isConfirmed) {
                    toggleSlot(p.slot_id, dt === 'blocked' ? 'available' : 'blocked');
                } else if (result.isDenied) {
                    deleteSlot(p.slot_id);
                }
            });
        },
    });
    calendar.render();
}

function fetchCalendarEvents(info, successCallback, failureCallback) {
    function toDateStr(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth()+1).padStart(2,'0') + '-' +
               String(d.getDate()).padStart(2,'0');
    }
    const endDate = new Date(info.end);
    endDate.setDate(endDate.getDate() - 1);

    const massagerId = document.getElementById('calMassagerFilter').value;
    const fd = new FormData();
    fd.append('action',      'get_calendar_events');
    fd.append('date_from',   toDateStr(info.start));
    fd.append('date_to',     toDateStr(endDate));
    fd.append('massager_id', massagerId);
    fd.append('csrf_token',  CSRF);

    fetch('availability.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'error') { failureCallback(data.message); return; }
            successCallback(data.events || []);
        })
        .catch(failureCallback);
}

function reloadCalendarEvents() {
    if (calendar) {
        calendar.refetchEvents();
        // Also refresh the blocked reasons panel for the current view
        const view = calendar.view;
        if (view) renderBlockedReasons(view.activeStart, view.activeEnd);
    }
}

// ── Blocked Reasons Panel ─────────────────────────────────
function toDateStr(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth()+1).padStart(2,'0') + '-' +
           String(d.getDate()).padStart(2,'0');
}

async function renderBlockedReasons(start, end) {
    const list = document.getElementById('blockedReasonsList');
    const monthLabel = document.getElementById('blockedReasonsMonth');
    list.innerHTML = '<div class="empty-state" style="padding:20px;"><p>Loading…</p></div>';

    const endInclusive = new Date(end);
    endInclusive.setDate(endInclusive.getDate() - 1);

    const massagerId = document.getElementById('calMassagerFilter').value;
    const fd = new FormData();
    fd.append('action',      'get_calendar_events');
    fd.append('date_from',   toDateStr(start));
    fd.append('date_to',     toDateStr(endInclusive));
    fd.append('massager_id', massagerId);
    fd.append('csrf_token',  CSRF);

    try {
        const res  = await fetch('availability.php', { method:'POST', body:fd });
        const data = await res.json();

        const blocked = (data.events || []).filter(e => e.extendedProps.display_type === 'blocked');

        // Update month label
        monthLabel.textContent = start.toLocaleDateString('en-MY', { month:'long', year:'numeric' });

        if (blocked.length === 0) {
            list.innerHTML = '<div class="empty-state" style="padding:20px 0;"><div class="icon" style="font-size:1.8rem;">✅</div><p>No blocked slots this period.</p></div>';
            return;
        }

        // Group by date
        const groups = {};
        blocked.forEach(e => {
            const date = e.start.substring(0, 10);
            if (!groups[date]) groups[date] = [];
            groups[date].push(e);
        });

        let html = `<table class="reason-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Massager</th>
                    <th>Time</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>`;

        Object.keys(groups).sort().forEach(date => {
            groups[date].forEach(e => {
                const p = e.extendedProps;
                const timeEnd = e.end ? e.end.substring(11,16) : '';
                const timeStr = p.time_label + (timeEnd ? ' – ' + timeEnd : '');
                const dateFormatted = new Date(date + 'T00:00:00').toLocaleDateString('en-MY', {
                    weekday:'short', day:'numeric', month:'short'
                });
                const reasonHtml = p.block_reason
                    ? `<span class="reason-pill">${reasonLabel(p.block_reason)}</span>`
                    : `<span class="reason-none">No reason given</span>`;

                html += `<tr>
                    <td style="font-weight:500;white-space:nowrap;">${dateFormatted}</td>
                    <td>${p.massager_name}</td>
                    <td style="white-space:nowrap;color:var(--text-muted);">${timeStr}</td>
                    <td>${reasonHtml}</td>
                </tr>`;
            });
        });

        html += '</tbody></table>';
        list.innerHTML = html;

    } catch(err) {
        list.innerHTML = '<div class="empty-state" style="padding:20px;"><p>Failed to load blocked slots.</p></div>';
    }
}

// ── Reason label map ──────────────────────────────────────
const REASON_LABELS = {
    sick:     'Medical Leave',
    family:   'Family Matter',
    other:    'Other',
};
function reasonLabel(r) {
    return r ? (REASON_LABELS[r] || ('📝 ' + r)) : '';
}

// ── Load slot list ────────────────────────────────────────
let currentSlots = [];

async function loadSlots() {
    const massagerId = document.getElementById('massagerSelect').value;
    const filterDate = document.getElementById('filterDate').value;

    const fd = new FormData();
    fd.append('action',      'get_slots');
    fd.append('massager_id', massagerId);
    fd.append('filter_date', filterDate);
    fd.append('csrf_token',  CSRF);

    const container = document.getElementById('slotsContainer');
    container.innerHTML = '<div class="empty-state"><div class="icon">⏳</div><p>Loading…</p></div>';

    const res  = await fetch('availability.php', { method:'POST', body:fd });
    const data = await res.json();
    currentSlots = data.data || [];
    renderSlots(currentSlots);
}

function renderSlots(slots) {
    const container = document.getElementById('slotsContainer');
    if (!slots || slots.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">🗓️</div><p>No slots found for the selected filter.</p></div>';
        return;
    }

    const groups = {};
    slots.forEach(s => {
        if (!groups[s.available_date]) groups[s.available_date] = [];
        groups[s.available_date].push(s);
    });

    container.innerHTML = Object.entries(groups).map(([date, items]) => `
        <div class="slot-date-group" id="group-${date}">
            <div class="slot-date-heading">${fmtDate(date)}</div>
            ${items.map(s => slotHTML(s)).join('')}
        </div>
    `).join('');
}

function slotHTML(s) {
    const dt = s.display_type;
    const badgeClass = dt === 'booked' ? 'badge-booked' : (dt === 'blocked' ? 'badge-blocked' : 'badge-available');
    const badgeText  = dt.charAt(0).toUpperCase() + dt.slice(1);
    const indClass   = 'indicator-' + (dt === 'booked' ? 'booked' : (dt === 'blocked' ? 'blocked' : 'available'));
    const canDelete  = dt !== 'booked';
    const canToggle  = dt !== 'booked';

    const toggleLabel = s.slot_type === 'blocked' ? 'unblock' : 'Blocked';
    const toggleNew   = s.slot_type === 'blocked' ? 'available' : 'blocked';
    const reasonBadge = (dt === 'blocked' && s.block_reason)
        ? `<span style="font-size:0.72rem;background:#fff3e0;color:#b7791f;padding:2px 8px;border-radius:12px;border:1px solid #f0c060;margin-left:6px;">${reasonLabel(s.block_reason)}</span>`
        : '';

    return `
        <div class="slot-item" id="slot-${s.id}">
            <div class="slot-left">
                <div class="slot-indicator ${indClass}"></div>
                <div>
                    <div class="slot-time">${fmt12(s.available_start.slice(0,5))} – ${fmt12(s.available_end.slice(0,5))}</div>
                    <div class="slot-massager">${s.massager_name}${reasonBadge}</div>
                </div>
            </div>
            <div class="slot-right">
                <span class="badge ${badgeClass}">${badgeText}</span>
                ${canToggle ? `<button class="btn-toggle" onclick="toggleSlot(${s.id},'${toggleNew}')">${toggleLabel}</button>` : ''}
                ${canDelete ? `<button class="btn-remove" onclick="deleteSlot(${s.id})">Remove</button>` : `<span style="font-size:0.74rem;color:var(--blue)">Has booking</span>`}
            </div>
        </div>`;
}

// ── Summary tab ───────────────────────────────────────────
function buildSummary() {
    const container = document.getElementById('summaryContainer');
    if (!currentSlots.length) {
        container.innerHTML = '<div class="empty-state"><div class="icon">📊</div><p>No data to summarise. Load slots first.</p></div>';
        return;
    }
    const totals = { available: 0, blocked: 0, booked: 0 };
    const byMassager = {};
    currentSlots.forEach(s => {
        totals[s.display_type] = (totals[s.display_type] || 0) + 1;
        if (!byMassager[s.massager_name]) byMassager[s.massager_name] = { available:0, blocked:0, booked:0 };
        byMassager[s.massager_name][s.display_type] = (byMassager[s.massager_name][s.display_type] || 0) + 1;
    });

    container.innerHTML = `
        <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
            <div style="flex:1;min-width:110px;background:var(--green-light);border-radius:10px;padding:14px 16px;text-align:center;">
                <div style="font-size:1.6rem;font-weight:700;color:var(--green);">${totals.available || 0}</div>
                <div style="font-size:0.75rem;color:var(--green);text-transform:uppercase;letter-spacing:.6px;">Available</div>
            </div>
            <div style="flex:1;min-width:110px;background:var(--red-light);border-radius:10px;padding:14px 16px;text-align:center;">
                <div style="font-size:1.6rem;font-weight:700;color:var(--red);">${totals.blocked || 0}</div>
                <div style="font-size:0.75rem;color:var(--red);text-transform:uppercase;letter-spacing:.6px;">Blocked</div>
            </div>
            <div style="flex:1;min-width:110px;background:var(--blue-light);border-radius:10px;padding:14px 16px;text-align:center;">
                <div style="font-size:1.6rem;font-weight:700;color:var(--blue);">${totals.booked || 0}</div>
                <div style="font-size:0.75rem;color:var(--blue);text-transform:uppercase;letter-spacing:.6px;">Booked</div>
            </div>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
            <thead>
                <tr style="border-bottom:2px solid var(--border);">
                    <th style="text-align:left;padding:8px 6px;color:var(--text-muted);font-size:0.72rem;text-transform:uppercase;">Massager</th>
                    <th style="text-align:center;padding:8px 6px;color:var(--green);font-size:0.72rem;">Available</th>
                    <th style="text-align:center;padding:8px 6px;color:var(--red);font-size:0.72rem;">Blocked</th>
                    <th style="text-align:center;padding:8px 6px;color:var(--blue);font-size:0.72rem;">Booked</th>
                </tr>
            </thead>
            <tbody>
                ${Object.entries(byMassager).map(([name, c]) => `
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:9px 6px;font-weight:500;">${name}</td>
                        <td style="text-align:center;padding:9px 6px;color:var(--green);">${c.available || 0}</td>
                        <td style="text-align:center;padding:9px 6px;color:var(--red);">${c.blocked || 0}</td>
                        <td style="text-align:center;padding:9px 6px;color:var(--blue);">${c.booked || 0}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>`;
}

// ── Add form submit ───────────────────────────────────────
document.getElementById('addForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!document.getElementById('startTime').value || !document.getElementById('endTime').value) {
        Toast.fire({ icon: 'warning', title: 'Please select a time slot.' });
        return;
    }

    const fd = new FormData(e.target);
    const res  = await fetch('availability.php', { method:'POST', body:fd });
    const data = await res.json();

    if (data.status === 'success') {
        Toast.fire({ icon: data.is_past ? 'warning' : 'success', title: data.message });
        e.target.reset();
        document.querySelectorAll('.type-opt').forEach(o => o.classList.remove('selected'));
        document.querySelector('.type-opt.avail').classList.add('selected');
        document.getElementById('slotTypeInput').value = 'available';
        document.getElementById('pastWarn').style.display = 'none';
        loadSlots();
        reloadCalendarEvents();
    } else {
        Swal.fire({ icon:'error', title:'Error', text: data.message });
    }
});

// ── Bulk form submit ──────────────────────────────────────
document.getElementById('bulkForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const checked = e.target.querySelectorAll('input[name="slots[]"]:checked');
    if (checked.length === 0) {
        Toast.fire({ icon:'warning', title:'Select at least one time slot.' });
        return;
    }

    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Generating…';

    const fd = new FormData(e.target);
    const res  = await fetch('availability.php', { method:'POST', body:fd });
    const data = await res.json();

    btn.disabled = false; btn.textContent = 'Generate Slots';

    if (data.status === 'success') {
        Toast.fire({ icon:'success', title: data.message });
        loadSlots();
        reloadCalendarEvents();
    } else {
        Swal.fire({ icon:'error', title:'Error', text: data.message });
    }
});

// ── Delete slot ───────────────────────────────────────────
function deleteSlot(id) {
    Swal.fire({
        title: 'Remove this slot?',
        text: 'Active bookings will block deletion.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c0392b',
        cancelButtonColor: '#8a7355',
        confirmButtonText: 'Yes, remove',
    }).then(async result => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete_slot');
        fd.append('id', id);
        fd.append('csrf_token', CSRF);
        const res  = await fetch('availability.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.status === 'success') {
            Toast.fire({ icon:'success', title: data.message });
            loadSlots();
            reloadCalendarEvents();
        } else {
            Swal.fire({ icon:'error', title:'Cannot delete', text: data.message });
        }
    });
}

// ── Toggle slot type ──────────────────────────────────────
function toggleSlot(id, newType) {
    const fd = new FormData();
    fd.append('action',     'toggle_slot_type');
    fd.append('id',         id);
    fd.append('new_type',   newType);
    fd.append('csrf_token', CSRF);
    fetch('availability.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                Toast.fire({ icon:'success', title: data.message });
                loadSlots();
                reloadCalendarEvents();
            } else {
                Swal.fire({ icon:'error', title:'Error', text: data.message });
            }
        });
}

// ── Clear filters ─────────────────────────────────────────
function clearFilters() {
    document.getElementById('massagerSelect').value = '';
    document.getElementById('filterDate').value = '';
    loadSlots();
}

// ── Sync "add massager" dropdown with list filter ─────────
document.getElementById('addMassager').addEventListener('change', function () {
    document.getElementById('massagerSelect').value = this.value;
    loadSlots();
});

// ── Init ──────────────────────────────────────────────────
window.onload = () => {
    initCalendar();
    loadSlots();
};
</script>
</body>
</html>