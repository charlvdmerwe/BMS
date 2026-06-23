<?php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dbTheStyleBay';
$dbTable = 'tblbookings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;

    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }
  // Handle add (create) action which doesn't require an ID
  if ($action === 'add') {
    // split name into first/last
    $full = trim($name);
    $parts = preg_split('/\s+/', $full);
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

    // map service to price server-side (fallback 0)
    $prices = [
      'Cut & Trim' => 160,
      'Braids & Cornrows' => 300,
      'Weave / Extensions' => 550,
      'Colour & Highlights' => 400,
      'Wash, Treat & Blow-dry' => 180,
      'Beard Shape-up' => 90,
    ];
    if (empty($price) && !empty($_POST['service']) && isset($prices[$_POST['service']])) {
      $price = $prices[$_POST['service']];
    }

    $service = $_POST['service'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $extra = "Service: {$service} | Notes: {$notes}";

    $stmt = $mysqli->prepare("INSERT INTO `{$dbTable}` (`Name`,`Surname`,`ContactNum`,`Date`,`Time`,`Price`,`Complete`,`ExtraInfo`) VALUES (?,?,?,?,?,?,'0',?)");
    if (!$stmt) {
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'Prepare failed.']);
      exit;
    }
    $stmt->bind_param('sssssds', $first, $last, $phone, $date, $time, $price, $extra);
    if ($stmt->execute()) {
      $newId = $mysqli->insert_id;
      echo json_encode(['success' => true, 'booking' => [
        'bookingId' => intval($newId),
        'name' => trim($first . ' ' . $last),
        'phone' => $phone,
        'service' => $service,
        'date' => $date,
        'time' => $time,
        'price' => floatval($price),
        'complete' => false,
        'status' => 'booked',
        'notes' => $notes
      ]]);
      exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Insert failed.']);
    exit;
  }

  $bookingId = isset($_POST['id']) ? intval($_POST['id']) : 0;
  if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
    exit;
  }

  if ($action === 'complete') {
        $stmt = $mysqli->prepare("UPDATE `{$dbTable}` SET `Complete` = 1 WHERE `BookingID` = ? LIMIT 1");
        $stmt->bind_param('i', $bookingId);
    } elseif ($action === 'delete') {
        $stmt = $mysqli->prepare("DELETE FROM `{$dbTable}` WHERE `BookingID` = ? LIMIT 1");
        $stmt->bind_param('i', $bookingId);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
    }

    if ($stmt && $stmt->execute()) {
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    exit;
}

// AJAX: return bookings list for polling
if (isset($_GET['ajax']) && ($_GET['action'] ?? '') === 'list') {
  header('Content-Type: application/json; charset=utf-8');
  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
  }

  $out = [];
  $sql = "SELECT `BookingID`,`Name`,`Surname`,`ContactNum`,`Date`,`Time`,`Price`,`Complete`,`ExtraInfo` FROM `{$dbTable}` ORDER BY `Date`,`Time`";
  if ($result = $mysqli->query($sql)) {
    $id = 1;
    while ($row = $result->fetch_assoc()) {
      $service = '';
      $notes = '';
      $price = isset($row['Price']) ? floatval($row['Price']) : 0;
      $complete = !empty($row['Complete']) && in_array($row['Complete'], [1, '1', true, 'true', 'yes'], true);
      if (!empty($row['ExtraInfo'])) {
        if (preg_match('/Service:\s*([^|]+)/i', $row['ExtraInfo'], $match)) {
          $service = trim($match[1]);
        }
        if (preg_match('/Notes:\s*(.*)$/i', $row['ExtraInfo'], $match)) {
          $notes = trim($match[1]);
        }
        if (!$service) {
          $service = trim(preg_replace('/\|.*$/', '', $row['ExtraInfo']));
        }
      }
      $out[] = [
        'id' => $id++,
        'bookingId' => intval($row['BookingID']),
        'name' => trim($row['Name'] . ' ' . $row['Surname']),
        'phone' => $row['ContactNum'],
        'service' => $service,
        'date' => $row['Date'],
        'time' => $row['Time'],
        'price' => $price,
        'complete' => $complete,
        'status' => $complete ? 'complete' : 'booked',
        'notes' => $notes,
      ];
    }
    $result->free();
  }

  echo json_encode(['success' => true, 'bookings' => $out]);
  exit;
}

// AJAX: Get available slots for a given date
if (isset($_GET['action']) && $_GET['action'] === 'getSlots') {
  header('Content-Type: application/json; charset=utf-8');
  
  $date = trim($_GET['date'] ?? '');
  
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
  }
  
  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
  }
  
  // Get all booked slots for this date (excluding completed bookings for now, as per original index.php behavior)
  $stmt = $mysqli->prepare("SELECT `Time` FROM `{$dbTable}` WHERE `Date` = ? AND `Complete` = 0");
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
  }
  
  $stmt->bind_param('s', $date);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $bookedSlots = [];
  while ($row = $result->fetch_assoc()) {
    $bookedSlots[] = $row['Time'];
  }
  
  $stmt->close();
  $mysqli->close();
  
  // All available time slots
  $allSlots = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
  $isFullyBooked = count($bookedSlots) === count($allSlots);
  
  echo json_encode([
    'success' => true,
    'date' => $date,
    'bookedSlots' => $bookedSlots,
    'isFullyBooked' => $isFullyBooked,
    'availableSlots' => array_values(array_diff($allSlots, $bookedSlots))
  ]);
  exit;
}

$bookings = [];
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if (!$mysqli->connect_error) {
    $sql = "SELECT `BookingID`,`Name`,`Surname`,`ContactNum`,`Date`,`Time`,`Price`,`Complete`,`ExtraInfo` FROM `{$dbTable}` ORDER BY `Date`,`Time`";
    if ($result = $mysqli->query($sql)) {
        $id = 1;
        while ($row = $result->fetch_assoc()) {
            $service = '';
            $notes = '';
            $price = isset($row['Price']) ? floatval($row['Price']) : 0;
            $complete = !empty($row['Complete']) && in_array($row['Complete'], [1, '1', true, 'true', 'yes'], true);
            if (!empty($row['ExtraInfo'])) {
                if (preg_match('/Service:\s*([^|]+)/i', $row['ExtraInfo'], $match)) {
                    $service = trim($match[1]);
                }
                if (preg_match('/Notes:\s*(.*)$/i', $row['ExtraInfo'], $match)) {
                    $notes = trim($match[1]);
                }
                if (!$service) {
                    $service = trim(preg_replace('/\|.*$/', '', $row['ExtraInfo']));
                }
            }
            $bookings[] = [
                'id' => $id++,
                'bookingId' => intval($row['BookingID']),
                'name' => trim($row['Name'] . ' ' . $row['Surname']),
                'phone' => $row['ContactNum'],
                'service' => $service,
                'date' => $row['Date'],
                'time' => $row['Time'],
                'price' => $price,
                'complete' => $complete,
                'status' => $complete ? 'complete' : 'booked',
                'notes' => $notes,
            ];
        }
        $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>The Style Bay — Booking Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root {
    --ink: #0E1722;
    --bay: #152537;
    --bay-light: #23405C;
    --gold: #CFA052;
    --gold-soft: #E8C988;
    --clay: #B5562F;
    --clay-dark: #8f4224;
    --sand: #F1E8D8;
    --sand-deep: #E3D4B7;
    --cream: #FAF6EC;
    --ink-warm: #241B12;
    --muted: #7A6644;
    --line-dark: rgba(232,201,136,0.18);
    --line-light: rgba(36,27,18,0.14);
    --green: #3a7d5a;
    --green-bg: #eaf5ef;
    --red-bg: #fdf0ed;
    --amber-bg: #fdf6e3;
    --sidebar-w: 220px;
    --radius: 3px;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Work Sans', sans-serif;
    background: #f5f0e8;
    color: var(--ink-warm);
    font-size: 15px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    display: flex;
    min-height: 100vh;
  }

  /* ===== SIDEBAR ===== */
  .sidebar {
    width: var(--sidebar-w);
    background: var(--ink);
    color: var(--cream);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 30;
    border-right: 1px solid var(--line-dark);
  }

  .sidebar-brand {
    padding: 22px 20px 18px;
    border-bottom: 1px solid var(--line-dark);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .sidebar-brand svg { width: 0px; height: 0px; flex-shrink: 0; }

  .brand-text { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--cream); line-height: 1.2; }
  .brand-sub { font-family: 'Space Mono', monospace; font-size: 0.58rem; letter-spacing: 0.16em; text-transform: uppercase; color: rgba(250,246,236,0.4); display: block; margin-top: 2px; }

  nav.sidebar-nav {
    padding: 18px 0;
    flex: 1;
  }

  .nav-section-label {
    font-family: 'Space Mono', monospace;
    font-size: 0.58rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(250,246,236,0.3);
    padding: 14px 20px 6px;
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    font-size: 0.88rem;
    color: rgba(250,246,236,0.65);
    cursor: pointer;
    border-left: 2px solid transparent;
    transition: color 0.15s, background 0.15s, border-color 0.15s;
    text-decoration: none;
    user-select: none;
  }

  .nav-item:hover { color: var(--cream); background: rgba(255,255,255,0.04); }
  .nav-item.active { color: var(--gold-soft); border-left-color: var(--gold); background: rgba(207,160,82,0.08); }

  .nav-item .nav-icon { width: 16px; height: 16px; opacity: 0.7; flex-shrink: 0; }
  .nav-item.active .nav-icon { opacity: 1; }

  .nav-badge {
    margin-left: auto;
    background: var(--clay);
    color: var(--cream);
    font-family: 'Space Mono', monospace;
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 20px;
  }

  .sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--line-dark);
    font-size: 0.78rem;
    color: rgba(250,246,236,0.35);
    font-family: 'Space Mono', monospace;
  }

  .status-dot {
    display: inline-block;
    width: 6px; height: 6px;
    background: #4caf7d;
    border-radius: 50%;
    margin-right: 6px;
  }

  /* ===== MAIN ===== */
  .main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }

  /* ===== TOPBAR ===== */
  .topbar {
    background: var(--cream);
    border-bottom: 1px solid var(--line-light);
    padding: 0 32px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 20;
  }

  .topbar-left h1 {
    font-family: 'Fraunces', serif;
    font-size: 1.22rem;
    font-weight: 600;
    color: var(--ink);
  }

  .topbar-left .topbar-sub {
    font-family: 'Space Mono', monospace;
    font-size: 0.66rem;
    color: var(--muted);
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  .topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .topbar-date {
    font-family: 'Space Mono', monospace;
    font-size: 0.72rem;
    color: var(--muted);
    letter-spacing: 0.06em;
  }

  .btn {
    font-family: 'Work Sans', sans-serif;
    font-weight: 600;
    font-size: 0.82rem;
    padding: 9px 16px;
    border-radius: var(--radius);
    border: 1px solid transparent;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    transition: background 0.15s, color 0.15s, border-color 0.15s, transform 0.1s;
  }

  .btn:active { transform: translateY(1px); }
  .btn-gold { background: var(--gold); color: var(--ink); }
  .btn-gold:hover { background: var(--gold-soft); }
  .btn-outline { background: transparent; border-color: var(--line-light); color: var(--ink-warm); }
  .btn-outline:hover { border-color: var(--clay); color: var(--clay); }
  .btn-clay { background: var(--clay); color: var(--cream); border-color: var(--clay); }
  .btn-clay:hover { background: var(--clay-dark); }
  .btn-ghost-sm { background: transparent; border: 1px solid var(--line-light); color: var(--muted); font-size: 0.78rem; padding: 5px 10px; }
  .btn-ghost-sm:hover { border-color: var(--clay); color: var(--clay); }

  /* ===== CONTENT ===== */
  .content {
    padding: 28px 32px 48px;
    flex: 1;
  }

  /* ===== PANELS ===== */
  .panel { display: none; }
  .panel.active { display: block; }

  /* ===== STAT STRIP ===== */
  .stat-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: var(--line-light);
    border: 1px solid var(--line-light);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 24px;
  }

  .stat-tile {
    background: var(--cream);
    padding: 20px 22px;
  }

  /* Centered, stretched stat tile (used on Services panel) */
  .stat-tile--center {
    grid-column: 1 / -1;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  .stat-label {
    font-family: 'Space Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
  }

  .stat-value {
    font-family: 'Fraunces', serif;
    font-size: 1.9rem;
    font-weight: 600;
    color: var(--ink);
    line-height: 1;
  }

  .stat-value .currency {
    font-size: 1rem;
    font-weight: 400;
    color: var(--muted);
    margin-right: 2px;
  }

  .stat-meta {
    font-size: 0.76rem;
    color: var(--muted);
    margin-top: 5px;
  }

  .stat-up { color: var(--green); }
  .stat-down { color: var(--clay); }

  /* ===== TWO-COL LAYOUT ===== */
  .two-col {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 20px;
    align-items: start;
  }

  /* ===== SECTION CARD ===== */
  .card {
    background: var(--cream);
    border: 1px solid var(--line-light);
    border-radius: var(--radius);
    overflow: hidden;
  }

  .card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--line-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .card-title {
    font-family: 'Fraunces', serif;
    font-size: 1rem;
    font-weight: 600;
    color: var(--ink);
  }

  .card-sub {
    font-family: 'Space Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--muted);
    margin-top: 2px;
  }

  /* ===== BOOKING TABLE ===== */
  .booking-table {
    width: 100%;
    border-collapse: collapse;
  }

  .booking-table th {
    font-family: 'Space Mono', monospace;
    font-size: 0.6rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: var(--muted);
    padding: 10px 16px;
    text-align: left;
    border-bottom: 1px solid var(--line-light);
    background: #f7f2e8;
    white-space: nowrap;
  }

  .booking-table td {
    padding: 13px 16px;
    border-bottom: 1px solid var(--line-light);
    font-size: 0.88rem;
    vertical-align: middle;
  }

  .booking-table tr:last-child td { border-bottom: none; }
  .booking-table tbody tr:hover { background: #faf7f0; }

  .client-name { font-weight: 600; color: var(--ink); }
  .client-phone { font-family: 'Space Mono', monospace; font-size: 0.75rem; color: var(--muted); margin-top: 2px; }

  .service-tag {
    display: inline-block;
    font-family: 'Space Mono', monospace;
    font-size: 0.65rem;
    letter-spacing: 0.06em;
    padding: 3px 8px;
    border-radius: 2px;
    background: var(--sand-deep);
    color: var(--muted);
  }

  .time-badge {
    font-family: 'Space Mono', monospace;
    font-size: 0.78rem;
    color: var(--ink-warm);
    white-space: nowrap;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: 'Space Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 4px 8px;
    border-radius: 2px;
    white-space: nowrap;
  }

  .status-badge::before {
    content: "";
    width: 5px; height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .status-new { background: var(--amber-bg); color: #8a6800; }
  .status-new::before { background: #d4a017; }
  .status-confirmed { background: var(--green-bg); color: #2a5e41; }
  .status-confirmed::before { background: var(--green); }
  .status-booked { background: var(--green-bg); color: #2a5e41; }
  .status-booked::before { background: var(--green); }
  .status-complete { background: #f0f0f0; color: #666; }
  .status-complete::before { background: #aaa; }
  .status-noshow { background: var(--red-bg); color: #7a2010; }
  .status-noshow::before { background: var(--clay); }

  .row-actions {
    display: flex;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.15s;
  }

  .booking-table tbody tr:hover .row-actions { opacity: 1; }

  /* ===== QUICK ACTIONS SIDEBAR ===== */
  .quick-slot {
    padding: 14px 18px;
    border-bottom: 1px solid var(--line-light);
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: background 0.1s;
  }

  .quick-slot:last-child { border-bottom: none; }
  .quick-slot:hover { background: #f7f2e8; }

  .qs-time {
    font-family: 'Space Mono', monospace;
    font-size: 0.72rem;
    color: var(--muted);
    width: 42px;
    flex-shrink: 0;
  }

  .qs-bar {
    width: 3px;
    border-radius: 2px;
    align-self: stretch;
    flex-shrink: 0;
    min-height: 36px;
  }

  .qs-info { flex: 1; min-width: 0; }
  .qs-name { font-weight: 600; font-size: 0.85rem; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .qs-service { font-size: 0.76rem; color: var(--muted); }

  .qs-status { flex-shrink: 0; }

  /* slot colours */
  .qs-bar.new { background: #d4a017; }
  .qs-bar.confirmed { background: var(--green); }
  .qs-bar.complete { background: #ccc; }

  /* ===== MINI CALENDAR ===== */
  .mini-cal {
    padding: 16px;
  }

  .cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }

  .cal-month {
    font-family: 'Fraunces', serif;
    font-size: 0.96rem;
    font-weight: 600;
    color: var(--ink);
  }

  .cal-nav {
    display: flex;
    gap: 6px;
  }

  .cal-nav button {
    background: none;
    border: 1px solid var(--line-light);
    border-radius: var(--radius);
    cursor: pointer;
    padding: 4px 8px;
    font-size: 0.78rem;
    color: var(--muted);
    transition: border-color 0.15s, color 0.15s;
  }

  .cal-nav button:hover { border-color: var(--clay); color: var(--clay); }

  .cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
    text-align: center;
  }

  .cal-dow {
    font-family: 'Space Mono', monospace;
    font-size: 0.58rem;
    letter-spacing: 0.08em;
    color: var(--muted);
    text-transform: uppercase;
    padding: 4px 0;
  }

  .cal-day {
    padding: 5px 2px;
    font-size: 0.82rem;
    border-radius: 2px;
    cursor: pointer;
    position: relative;
    transition: background 0.1s, color 0.1s;
    color: var(--ink-warm);
  }

  .cal-day:hover { background: var(--sand-deep); }
  .cal-day.today { background: var(--ink); color: var(--cream); font-weight: 700; }
  .cal-day.selected { background: var(--clay); color: var(--cream); }
  .cal-day.has-booking::after {
    content: "";
    width: 4px; height: 4px;
    border-radius: 50%;
    background: var(--gold);
    position: absolute;
    bottom: 2px; left: 50%; transform: translateX(-50%);
  }

  .cal-day.today.has-booking::after,
  .cal-day.selected.has-booking::after { background: var(--gold-soft); }

  .cal-day.other-month { color: rgba(36,27,18,0.25); }
  .cal-day.closed { color: rgba(36,27,18,0.2); pointer-events: none; }

  /* ===== NOTES ===== */
  .notes-item {
    padding: 14px 18px;
    border-bottom: 1px solid var(--line-light);
    font-size: 0.84rem;
  }

  .notes-item:last-child { border-bottom: none; }
  .notes-label {
    font-family: 'Space Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 4px;
  }

  /* ===== SERVICE BREAKDOWN ===== */
  .svc-row {
    padding: 12px 18px;
    border-bottom: 1px solid var(--line-light);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .svc-row:last-child { border-bottom: none; }

  .svc-name {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--ink);
    flex: 1;
  }

  .svc-count {
    font-family: 'Space Mono', monospace;
    font-size: 0.72rem;
    color: var(--muted);
    width: 30px;
    text-align: right;
  }

  .svc-bar-wrap {
    width: 80px;
    height: 5px;
    background: var(--sand-deep);
    border-radius: 3px;
    overflow: hidden;
  }

  .svc-bar {
    height: 100%;
    background: var(--gold);
    border-radius: 3px;
    transition: width 0.6s cubic-bezier(.4,0,.2,1);
  }

  .svc-revenue {
    font-family: 'Space Mono', monospace;
    font-size: 0.72rem;
    color: var(--ink-warm);
    width: 54px;
    text-align: right;
  }

  /* ===== MODAL ===== */
  .modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(14,23,34,0.6);
    z-index: 100;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
  }

  .modal-backdrop.open { display: flex; }

  .modal {
    background: var(--cream);
    border-radius: var(--radius);
    width: 100%;
    max-width: 520px;
    box-shadow: 0 20px 60px rgba(14,23,34,0.35);
    overflow: hidden;
  }

  .modal-head {
    background: var(--ink);
    color: var(--cream);
    padding: 20px 24px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }

  .modal-head h3 { font-family: 'Fraunces', serif; font-size: 1.1rem; }
  .modal-head .modal-sub { font-family: 'Space Mono', monospace; font-size: 0.62rem; letter-spacing: 0.1em; color: rgba(250,246,236,0.5); text-transform: uppercase; margin-top: 3px; }

  .modal-close {
    background: none;
    border: none;
    color: rgba(250,246,236,0.5);
    font-size: 1.2rem;
    cursor: pointer;
    line-height: 1;
    padding: 2px;
    flex-shrink: 0;
  }

  .modal-close:hover { color: var(--cream); }

  .modal-body { padding: 24px; }

  .modal-row {
    display: flex;
    gap: 18px;
    margin-bottom: 14px;
  }

  .modal-field {
    flex: 1;
  }

  .modal-field label {
    display: block;
    font-family: 'Space Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
  }

  .modal-field input,
  .modal-field select,
  .modal-field textarea {
    width: 100%;
    font-family: 'Work Sans', sans-serif;
    font-size: 0.9rem;
    padding: 10px 12px;
    border: 1px solid var(--line-light);
    background: var(--sand);
    color: var(--ink-warm);
    border-radius: var(--radius);
  }

  .modal-field textarea { min-height: 60px; resize: vertical; }

  .modal-field input:focus,
  .modal-field select:focus,
  .modal-field textarea:focus { outline: 2px solid var(--clay); outline-offset: 1px; }

  .modal-foot {
    padding: 16px 24px;
    border-top: 1px solid var(--line-light);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }

  /* Detail modal */
  .detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }

  .detail-item .detail-label {
    font-family: 'Space Mono', monospace;
    font-size: 0.6rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 3px;
  }

  .detail-item .detail-value {
    font-size: 0.9rem;
    color: var(--ink);
    font-weight: 500;
  }

  .detail-note {
    background: var(--sand);
    border-left: 3px solid var(--gold);
    padding: 10px 14px;
    font-size: 0.84rem;
    color: var(--ink-warm);
    margin-top: 14px;
    border-radius: 0 2px 2px 0;
    line-height: 1.5;
  }

  .detail-actions {
    margin-top: 18px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  /* ===== TOAST ===== */
  .toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 200;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .toast {
    background: var(--ink);
    color: var(--cream);
    padding: 12px 18px;
    border-radius: var(--radius);
    font-family: 'Space Mono', monospace;
    font-size: 0.76rem;
    box-shadow: 0 4px 16px rgba(14,23,34,0.25);
    border-left: 3px solid var(--gold);
    opacity: 0;
    transform: translateY(8px);
    transition: opacity 0.25s, transform 0.25s;
  }

  .toast.show { opacity: 1; transform: translateY(0); }

  /* ===== TABS ===== */
  .tab-bar {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--line-light);
    background: var(--cream);
    padding: 0 20px;
  }

  .tab-btn {
    font-family: 'Space Mono', monospace;
    font-size: 0.65rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    padding: 12px 16px;
    border: none;
    background: none;
    color: var(--muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
  }

  .tab-btn:hover { color: var(--ink); }
  .tab-btn.active { color: var(--clay); border-bottom-color: var(--clay); }

  /* ===== FILTER BAR ===== */
  .filter-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--line-light);
    flex-wrap: wrap;
  }

  .filter-bar input[type="date"],
  .filter-bar select {
    font-family: 'Work Sans', sans-serif;
    font-size: 0.82rem;
    padding: 7px 10px;
    border: 1px solid var(--line-light);
    border-radius: var(--radius);
    background: var(--sand);
    color: var(--ink-warm);
  }

  .filter-label {
    font-family: 'Space Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--muted);
  }

  /* ===== EMPTY STATE ===== */
  .empty-state {
    padding: 44px 24px;
    text-align: center;
    color: var(--muted);
    font-size: 0.88rem;
  }

  .empty-state svg { opacity: 0.2; margin-bottom: 12px; }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 900px) {
    .two-col { grid-template-columns: 1fr; }
    .stat-strip { grid-template-columns: 1fr 1fr; }
  }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M3 22c2.5-3 4.5-3 7 0s4.5 3 7 0 4.5-3 7 0 4.5 3 5 0" stroke="#CFA052" stroke-width="1.6" stroke-linecap="round"/>
      <path d="M5 18 9 7l4 8 3-9 3 9 4-8 4 11" stroke="#E8C988" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    </svg>
    <div>
      <div class="brand-text">The Style Bay</div>
      <span class="brand-sub">Dashboard</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Manage</div>
    <div class="nav-item active" data-panel="bookings">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="2" width="14" height="12" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M4 6h8M4 9h5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      All Bookings
      <span class="nav-badge" id="newBadge">2</span>
    </div>
    <div class="nav-item" data-panel="new">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 5v6M5 8h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      Add Booking
    </div>

    <div class="nav-section-label" style="margin-top:8px;">Insights</div>
    <div class="nav-item" data-panel="services">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><path d="M2 13V9M6 13V6M10 13V4M14 13V2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      Services &amp; Revenue
    </div>

    <div class="nav-section-label" style="margin-top:8px;">Salon</div>
    <div class="nav-item" id="waLink" data-panel="settings">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="6" r="3" stroke="currentColor" stroke-width="1.3"/><path d="M2 14c0-3 2.7-5 6-5s6 2 6 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      Salon Info
    </div>
  </nav>

  <div class="sidebar-footer">
    <span class="status-dot"></span>Open today · Tue–Sat
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <h1 id="topbarTitle">All Bookings</h1>
      <div class="topbar-sub" id="topbarSub">Old Oak Centre, Belair</div>
    </div>
    <div class="topbar-right">
      <div class="topbar-date" id="topbarDate"></div>
      <button class="btn btn-gold" onclick="openNewBookingModal()">+ New booking</button>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <!-- ======== PANEL: ALL BOOKINGS ======== -->
    <div class="panel" id="panel-bookings">
      <div class="stat-strip">
        <div class="stat-tile">
          <div class="stat-label">Total this month</div>
          <div class="stat-value" id="allBookingsTotal">0</div>
          <div class="stat-meta" id="allBookingsMeta">No bookings yet</div>
        </div>
        <div class="stat-tile">
          <div class="stat-label">Total bookings</div>
          <div class="stat-value" id="allBookingsConfirmed">0</div>
          <div class="stat-meta" id="allBookingsConfirmedMeta">No data</div>
        </div>
        <div class="stat-tile">
          <div class="stat-label">Booked days</div>
          <div class="stat-value stat-down" style="color:var(--clay)" id="allBookingsNoShows">0</div>
          <div class="stat-meta stat-down" id="allBookingsNoShowsMeta">No data</div>
        </div>
        <div class="stat-tile">
          <div class="stat-label">Month revenue</div>
          <div class="stat-value"><span class="currency">R</span><span id="allBookingsRevenue">0</span></div>
          <div class="stat-meta stat-up" id="allBookingsRevenueMeta">No revenue</div>
        </div>
      </div>

      <div class="card">
        <div class="tab-bar">
          <button class="tab-btn active" data-tab="all">All Bookings</button>
        </div>
        <div class="filter-bar">
          <span class="filter-label">Filter:</span>
          <input type="date" id="filterDate">
          <select id="filterService">
            <option value="">All services</option>
            <option>Cut &amp; Trim</option>
            <option>Braids &amp; Cornrows</option>
            <option>Weave / Extensions</option>
            <option>Colour &amp; Highlights</option>
            <option>Wash, Treat &amp; Blow-dry</option>
            <option>Beard Shape-up</option>
          </select>
          <button class="btn btn-ghost-sm" onclick="clearFilters()">Clear</button>
        </div>
        <div style="overflow-x:auto;">
          <table class="booking-table">
            <thead>
              <tr>
                <th>Client</th>
                <th>Service</th>
                <th>Price</th>
                <th>Date &amp; Time</th>
                <th>Status</th>
                <th>Notes</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="bookingsTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ======== PANEL: NEW BOOKING ======== -->
    <div class="panel" id="panel-new">
      <div class="card" style="max-width:580px;">
        <div class="card-header">
          <div>
            <div class="card-title">Add a booking manually</div>
            <div class="card-sub">Walk-in or phone booking</div>
          </div>
        </div>
        <div style="padding:24px;">
          <div class="modal-row">
            <div class="modal-field">
              <label>Client name</label>
              <input type="text" id="nb-name" placeholder="e.g. Nomvula Dlamini">
            </div>
            <div class="modal-field">
              <label>Phone</label>
              <input type="tel" id="nb-phone" placeholder="082 000 0000">
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Service</label>
              <select id="nb-service">
                <option value="" disabled selected>Choose</option>
                <option>Cut &amp; Trim</option>
                <option>Braids &amp; Cornrows</option>
                <option>Weave / Extensions</option>
                <option>Colour &amp; Highlights</option>
                <option>Wash, Treat &amp; Blow-dry</option>
                <option>Beard Shape-up</option>
              </select>
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Date</label>
              <input type="date" id="nb-date">
            </div>
            <div class="modal-field">
              <label>Time</label>
              <select id="nb-time">
                <option>09:00</option><option>10:00</option><option>11:00</option>
                <option>12:00</option><option>13:00</option><option>14:00</option>
                <option>15:00</option><option>16:00</option><option>17:00</option>
              </select>
            </div>
          </div>
          <div class="modal-field" style="margin-bottom:24px;">
            <label>Notes (optional)</label>
            <textarea id="nb-notes" placeholder="Hair length, preferences, special requests…"></textarea>
          </div>
          <div style="display:flex; gap:10px;">
            <button class="btn btn-clay" onclick="saveNewBooking()">Save booking</button>
            <button class="btn btn-outline" onclick="switchPanel('bookings')">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ======== PANEL: SERVICES ======== -->
    <div class="panel" id="panel-services">
      <div class="stat-strip">
        <div class="stat-tile stat-tile--center">
          <div class="stat-label">Best seller</div>
          <div class="stat-value" style="font-size:1.2rem; font-family:'Fraunces',serif; font-weight:600; padding-top:4px;" id="servicesBestSeller">—</div>
          <div class="stat-meta" id="servicesBestSellerMeta">No data yet</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">Services breakdown — this month</div>
        </div>
        <div id="serviceBreakdown"></div>
      </div>
    </div>

    <!-- ======== PANEL: SETTINGS ======== -->
    <div class="panel" id="panel-settings">
      <div class="card" style="max-width:540px;">
        <div class="card-header">
          <div class="card-title">Salon info</div>
        </div>
        <div style="padding:24px;">
          <div class="modal-row">
            <div class="modal-field">
              <label>Salon name</label>
              <input type="text" value="The Style Bay Unisex Hair Salon">
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Address</label>
              <input type="text" value="39 Meerlust St, Old Oak Centre, Belair, Cape Town">
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Phone</label>
              <input type="tel" value="021 910 0519">
            </div>
            <div class="modal-field">
              <label>WhatsApp number</label>
              <input type="tel" value="27219100519">
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Open days</label>
              <input type="text" value="Tuesday – Saturday">
            </div>
            <div class="modal-field">
              <label>Hours</label>
              <input type="text" value="09:00 – 18:00">
            </div>
          </div>
          <button class="btn btn-clay" onclick="showToast('Salon info saved.')">Save changes</button>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ===== DETAIL MODAL ===== -->
<div class="modal-backdrop" id="detailModal">
  <div class="modal">
    <div class="modal-head">
      <div>
        <h3 id="dm-name">Client Name</h3>
        <div class="modal-sub" id="dm-sub">Service · Date · Time</div>
      </div>
      <button class="modal-close" onclick="closeModal('detailModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="detail-grid" id="dm-grid"></div>
      <div class="detail-note" id="dm-note" style="display:none;"></div>
      <div class="detail-actions" id="dm-actions"></div>
    </div>
  </div>
</div>

<!-- ===== NEW BOOKING MODAL ===== -->
<div class="modal-backdrop" id="newModal">
  <div class="modal">
    <div class="modal-head">
      <div>
        <h3>New booking</h3>
        <div class="modal-sub">Walk-in or phone booking</div>
      </div>
      <button class="modal-close" onclick="closeModal('newModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-row">
        <div class="modal-field">
          <label>Client name</label>
          <input type="text" id="mnb-name" placeholder="e.g. Nomvula Dlamini">
        </div>
        <div class="modal-field">
          <label>Phone</label>
          <input type="tel" id="mnb-phone" placeholder="082 000 0000">
        </div>
      </div>
      <div class="modal-row">
        <div class="modal-field">
          <label>Service</label>
          <select id="mnb-service">
            <option value="" disabled selected>Choose</option>
            <option>Cut &amp; Trim</option>
            <option>Braids &amp; Cornrows</option>
            <option>Weave / Extensions</option>
            <option>Colour &amp; Highlights</option>
            <option>Wash, Treat &amp; Blow-dry</option>
            <option>Beard Shape-up</option>
          </select>
        </div>
      </div>
      <div class="modal-row">
        <div class="modal-field">
          <label>Date</label>
          <input type="date" id="mnb-date">
        </div>
        <div class="modal-field">
          <label>Time</label>
          <select id="mnb-time">
            <option>09:00</option><option>10:00</option><option>11:00</option>
            <option>12:00</option><option>13:00</option><option>14:00</option>
            <option>15:00</option><option>16:00</option><option>17:00</option>
          </select>
        </div>
      </div>
      <div class="modal-field">
        <label>Notes (optional)</label>
        <textarea id="mnb-notes" placeholder="Hair length, preferences, special requests…"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('newModal')">Cancel</button>
      <button class="btn btn-clay" onclick="saveModalBooking()">Save booking</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast-container">
  <div class="toast" id="mainToast"></div>
</div>

<script>
// ===== DATA =====
const SERVICES = [
  { name: 'Weave / Extensions', count: 0, revenue: 0 },
  { name: 'Braids & Cornrows', count: 0, revenue: 0 },
  { name: 'Cut & Trim', count: 0, revenue: 0 },
  { name: 'Colour & Highlights', count: 0, revenue: 0 },
  { name: 'Wash, Treat & Blow-dry', count: 0, revenue: 0 },
  { name: 'Beard Shape-up', count: 0, revenue: 0 },
];

const today = new Date();
const todayStr = today.toISOString().split('T')[0];
const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

const bookings = <?php echo json_encode($bookings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let nextId = <?php echo count($bookings) + 1; ?>;

function offsetDate(days) {
  const d = new Date(today);
  d.setDate(d.getDate() + days);
  return d.toISOString().split('T')[0];
}

const PRICES = {
  'Cut & Trim': 160,
  'Braids & Cornrows': 300,
  'Weave / Extensions': 550,
  'Colour & Highlights': 400,
  'Wash, Treat & Blow-dry': 180,
  'Beard Shape-up': 90,
};

function prettyDate(d) {
  if (!d) return '';
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('en-ZA', { weekday:'short', day:'numeric', month:'short' });
}

// ===== NAV =====
const panels = ['bookings','new','services','settings'];
const panelTitles = {
  bookings: "All Bookings",
  new: "Add Booking",
  services: "Services & Revenue",
  settings: "Salon Info"
};

function switchPanel(name) {
  panels.forEach(p => {
    document.getElementById('panel-' + p).classList.toggle('active', p === name);
  });
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.panel === name);
  });
  document.getElementById('topbarTitle').textContent = panelTitles[name];
  if (name === 'bookings') renderBookingsTable();
  if (name === 'services') { updateServicesData(); renderServices(); }
}

document.querySelectorAll('.nav-item[data-panel]').forEach(el => {
  el.addEventListener('click', () => switchPanel(el.dataset.panel));
});

// ===== TOPBAR DATE =====
document.getElementById('topbarDate').textContent =
  today.toLocaleDateString('en-ZA', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
document.getElementById('topbarSub').textContent =
  DAYS[today.getDay()] + ' · Old Oak Centre, Belair';

function statusLabel(s) {
  if (s === 'complete') return 'Complete';
  return 'Booked';
}

// ===== BOOKINGS TABLE =====
let currentTab = 'all';

document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentTab = btn.dataset.tab;
    renderBookingsTable();
  });
});

document.getElementById('filterDate').addEventListener('change', renderBookingsTable);
document.getElementById('filterService').addEventListener('change', renderBookingsTable);

function clearFilters() {
  document.getElementById('filterDate').value = '';
  document.getElementById('filterService').value = '';
  renderBookingsTable();
}

function renderBookingsTable() {
  const filterDate = document.getElementById('filterDate').value;
  const filterService = document.getElementById('filterService').value;
  const tbody = document.getElementById('bookingsTableBody');

  let filtered = [...bookings];
  if (filterDate) filtered = filtered.filter(b => b.date === filterDate);
  if (filterService) filtered = filtered.filter(b => b.service === filterService);

  filtered.sort((a,b) => (a.date + a.time).localeCompare(b.date + b.time));

  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">No bookings match this filter.</div></td></tr>';
  } else {
    tbody.innerHTML = filtered.map(b => `
      <tr>
        <td>
          <div class="client-name">${b.name}</div>
          <div class="client-phone">${b.phone}</div>
        </td>
        <td><span class="service-tag">${b.service}</span></td>
        <td>R ${Number(b.price || PRICES[b.service] || 0).toLocaleString('en-ZA')}</td>
        <td><div class="time-badge">${prettyDate(b.date)}<br><span style="color:var(--gold);">${b.time}</span></div></td>
        <td><span class="status-badge status-${b.status}">${statusLabel(b.status)}</span></td>
        <td style="font-size:0.82rem; color:var(--muted); max-width:140px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${b.notes || '—'}</td>
        <td>
          <div class="row-actions">
            <button class="btn btn-ghost-sm" onclick="openDetailModal(${b.id})">View</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  updateBookingStats();
}

function updateBookingStats() {
  const monthKey = todayStr.slice(0, 7);
  const monthBookings = bookings.filter(b => b.date.startsWith(monthKey));
  const totalBookings = bookings.length;
  const monthRevenue = monthBookings.reduce((sum, b) => {
    const priceVal = Number(b.price || PRICES[b.service] || 0);
    return sum + (b.complete ? priceVal : 0);
  }, 0);
  const bookedDays = new Set(monthBookings.map(b => b.date)).size;

  document.getElementById('allBookingsTotal').textContent = monthBookings.length;
  document.getElementById('allBookingsMeta').textContent = monthBookings.length
    ? `${monthBookings.length} bookings this month`
    : 'No bookings this month';

  document.getElementById('allBookingsConfirmed').textContent = totalBookings;
  document.getElementById('allBookingsConfirmedMeta').textContent = totalBookings
    ? 'Total bookings in system'
    : 'No saved bookings';

  document.getElementById('allBookingsNoShows').textContent = bookedDays;
  document.getElementById('allBookingsNoShowsMeta').textContent = bookedDays
    ? `${bookedDays} booked day${bookedDays === 1 ? '' : 's'} this month`
    : 'No booked days';

  document.getElementById('allBookingsRevenue').textContent = monthRevenue.toLocaleString('en-ZA');
  document.getElementById('allBookingsRevenueMeta').textContent = monthRevenue
    ? 'Current month revenue'
    : 'No revenue yet';
  document.getElementById('newBadge').textContent = totalBookings;
  // keep services data in sync
  if (typeof updateServicesData === 'function') updateServicesData();
}

// ===== DETAIL MODAL =====
function openDetailModal(id) {
  const b = bookings.find(x => x.id === id);
  if (!b) return;

  document.getElementById('dm-name').textContent = b.name;
  document.getElementById('dm-sub').textContent = `${b.service} · ${prettyDate(b.date)} · ${b.time}`;

  const grid = document.getElementById('dm-grid');
  grid.innerHTML = [
    ['Phone', b.phone],
    ['Service', b.service],
    ['Price', `R ${Number(b.price || PRICES[b.service] || 0).toLocaleString('en-ZA')}`],
    ['Date', prettyDate(b.date)],
    ['Time', b.time],
    ['Status', `<span class="status-badge status-${b.status}">${statusLabel(b.status)}</span>`],
  ].map(([label, val]) => `
    <div class="detail-item">
      <div class="detail-label">${label}</div>
      <div class="detail-value">${val}</div>
    </div>`).join('');

  const noteEl = document.getElementById('dm-note');
  if (b.notes) { noteEl.style.display = ''; noteEl.textContent = b.notes; }
  else { noteEl.style.display = 'none'; }

  const actions = document.getElementById('dm-actions');
  actions.innerHTML = '';

  const addBtn = (label, cls, onclick) => {
    const btn = document.createElement('button');
    btn.className = 'btn ' + cls;
    btn.textContent = label;
    btn.onclick = onclick;
    actions.appendChild(btn);
  };

  if (!b.complete) {
    addBtn('Mark complete', 'btn btn-gold', () => markCompleteBooking(b.id));
  }
  addBtn('Delete booking', 'btn btn-outline', () => deleteBooking(b.id));

  const waBtn = document.createElement('a');
  waBtn.className = 'btn btn-outline';
  waBtn.style.color = '#128C7E';
  waBtn.style.borderColor = '#128C7E';
  waBtn.textContent = 'WhatsApp';
  waBtn.href = `https://wa.me/${b.phone.replace(/\D/g,'')}?text=${encodeURIComponent(`Hi ${b.name.split(' ')[0]}, your booking at The Style Bay is set for ${b.service} on ${prettyDate(b.date)} at ${b.time}. See you then! 👑`)}`;
  waBtn.target = '_blank';
  waBtn.rel = 'noopener';
  actions.appendChild(waBtn);

  openModal('detailModal');
}

// ===== SERVICES =====
function renderServices() {
  const max = Math.max(...SERVICES.map(s => s.count));
  const container = document.getElementById('serviceBreakdown');
  container.innerHTML = SERVICES.map(s => {
    const width = max ? (s.count / max * 100) : 0;
    return `
    <div class="svc-row">
      <div class="svc-name">${s.name}</div>
      <div class="svc-bar-wrap"><div class="svc-bar" style="width:${width}%"></div></div>
      <div class="svc-count">${s.count}</div>
      <div class="svc-revenue">R ${s.revenue.toLocaleString()}</div>
    </div>`;
  }).join('');
}

// Build SERVICES counts and revenue from current bookings
function updateServicesData() {
  // reset
  SERVICES.forEach(s => { s.count = 0; s.revenue = 0; });

  bookings.forEach(b => {
    const idx = SERVICES.findIndex(s => s.name === b.service);
    const priceVal = Number(b.price || PRICES[b.service] || 0);
    if (idx === -1) return;
    SERVICES[idx].count += 1;
    // Only include revenue for completed bookings
    if (b.complete) SERVICES[idx].revenue += priceVal;
  });

  // update best seller label
  const best = SERVICES.reduce((a, b) => (b.count > (a.count||0) ? b : a), { count: 0 });
  document.getElementById('servicesBestSeller').textContent = best && best.count ? best.name : '—';
  document.getElementById('servicesBestSellerMeta').textContent = best && best.count ? `${best.count} bookings` : 'No data yet';
}

// ===== NEW BOOKING =====
function openNewBookingModal(time) {
  const d = new Date(); d.setDate(d.getDate());
  document.getElementById('mnb-date').value = todayStr;
  document.getElementById('mnb-date').min = todayStr;
  if (time) document.getElementById('mnb-time').value = time;
  openModal('newModal');
}

function saveModalBooking() {
  const name = document.getElementById('mnb-name').value.trim();
  const phone = document.getElementById('mnb-phone').value.trim();
  const service = document.getElementById('mnb-service').value;
  const date = document.getElementById('mnb-date').value;
  const time = document.getElementById('mnb-time').value;
  const notes = document.getElementById('mnb-notes').value.trim();

  if (!name || !phone || !service || !date) {
    showToast('Please fill in all required fields.'); return;
  }

  const form = new FormData();
  form.set('ajax', '1');
  form.set('action', 'add');
  form.set('name', name);
  form.set('phone', phone);
  form.set('service', service);
  form.set('date', date);
  form.set('time', time);
  form.set('notes', notes);

  fetch(window.location.href, { method: 'POST', body: form })
    .then(r => r.json())
    .then(data => {
      if (data && data.success && data.booking) {
        const b = data.booking;
        b.id = nextId++;
        bookings.push(b);
        closeModal('newModal');
        renderBookingsTable();
        updateBookingStats();
        showToast(`Booking added for ${name}.`);
        document.getElementById('mnb-name').value = '';
        document.getElementById('mnb-phone').value = '';
        document.getElementById('mnb-service').value = '';
        document.getElementById('mnb-notes').value = '';
        if (typeof pollBookings === 'function') pollBookings();
      } else {
        alert(data.message || 'Could not save booking.');
      }
    }).catch(() => alert('Could not reach server.'));
}

function saveNewBooking() {
  const name = document.getElementById('nb-name').value.trim();
  const phone = document.getElementById('nb-phone').value.trim();
  const service = document.getElementById('nb-service').value;
  const date = document.getElementById('nb-date').value;
  const time = document.getElementById('nb-time').value;
  const notes = document.getElementById('nb-notes').value.trim();
  if (!name || !phone || !service || !date) { showToast('Fill in all required fields.'); return; }

  const form = new FormData();
  form.set('ajax', '1');
  form.set('action', 'add');
  form.set('name', name);
  form.set('phone', phone);
  form.set('service', service);
  form.set('date', date);
  form.set('time', time);
  form.set('notes', notes);

  fetch(window.location.href, { method: 'POST', body: form })
    .then(r => r.json())
    .then(data => {
      if (data && data.success && data.booking) {
        const b = data.booking;
        b.id = nextId++;
        bookings.push(b);
        showToast(`Booking for ${name} saved.`);
        switchPanel('bookings');
        renderBookingsTable();
        if (typeof pollBookings === 'function') pollBookings();
      } else {
        alert(data.message || 'Could not save booking.');
      }
    }).catch(() => alert('Could not reach server.'));
}

// ===== CALENDAR =====
let calOffset = 0;

function renderCal() {
  const d = new Date(today.getFullYear(), today.getMonth() + calOffset, 1);
  document.getElementById('calMonthLabel').textContent = MONTHS[d.getMonth()] + ' ' + d.getFullYear();
  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

  const dows = ['Su','Mo','Tu','We','Th','Fr','Sa'];
  dows.forEach(dow => {
    const el = document.createElement('div');
    el.className = 'cal-dow';
    el.textContent = dow;
    grid.appendChild(el);
  });

  const firstDay = new Date(d.getFullYear(), d.getMonth(), 1).getDay();
  const daysInMonth = new Date(d.getFullYear(), d.getMonth()+1, 0).getDate();
  const prevDays = new Date(d.getFullYear(), d.getMonth(), 0).getDate();

  // Prev month padding
  for (let i = firstDay - 1; i >= 0; i--) {
    const el = document.createElement('div');
    el.className = 'cal-day other-month';
    el.textContent = prevDays - i;
    grid.appendChild(el);
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const el = document.createElement('div');
    const dateStr = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    const dow = new Date(dateStr + 'T00:00:00').getDay();
    el.className = 'cal-day';
    el.textContent = day;

    if (dow === 0 || dow === 1) el.classList.add('closed'); // Sun/Mon
    if (dateStr === todayStr) el.classList.add('today');
    if (bookings.some(b => b.date === dateStr)) el.classList.add('has-booking');

    el.addEventListener('click', () => {
      document.getElementById('filterDate').value = dateStr;
      switchPanel('bookings');
      renderBookingsTable();
    });

    grid.appendChild(el);
  }
}

function shiftCal(dir) { calOffset += dir; renderCal(); }

// ===== MODAL HELPERS =====
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop').forEach(el => {
  el.addEventListener('click', (e) => { if (e.target === el) el.classList.remove('open'); });
});

// ===== TOAST =====
let toastTimer;
function showToast(msg) {
  const t = document.getElementById('mainToast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

function splitName(fullName) {
  const parts = fullName.trim().split(/\s+/);
  return [parts[0] || '', parts.slice(1).join(' ')];
}

async function postDashboardAction(action, booking) {
  if (!booking.bookingId) {
    throw new Error('Missing booking ID');
  }

  const form = new FormData();
  form.set('ajax', '1');
  form.set('action', action);
  form.set('id', booking.bookingId);

  const response = await fetch(window.location.href, {
    method: 'POST',
    body: form,
  });
  return response.json();
}

// Poll server for latest bookings and update UI if changed
let _polling = false;
async function pollBookings() {
  if (_polling) return;
  _polling = true;
  try {
    const res = await fetch(window.location.pathname + '?ajax=1&action=list');
    const data = await res.json();
    if (data && data.success && Array.isArray(data.bookings)) {
      // compare by bookingId list
      const remoteIds = data.bookings.map(b => b.bookingId).join(',');
      const localIds = bookings.map(b => b.bookingId).join(',');
      if (remoteIds !== localIds) {
        // Replace local bookings with fresh data
        bookings.length = 0;
        data.bookings.forEach(b => bookings.push(b));
        renderBookingsTable();
        updateBookingStats();
      }
    }
  } catch (e) {
    // silently ignore network errors
    console.error('Polling error', e);
  } finally {
    _polling = false;
  }
}

// Start polling when on All Bookings panel
setInterval(() => {
  const active = document.getElementById('panel-bookings').classList.contains('active');
  if (active) pollBookings();
}, 3000);

async function markCompleteBooking(id) {
  const booking = bookings.find(x => x.id === id);
  if (!booking || booking.complete) return;
  if (!confirm('Mark this booking as complete?')) return;

  const result = await postDashboardAction('complete', booking);
  if (result.success) {
    // mark booking complete locally so it updates in the table
    booking.complete = true;
    booking.status = 'complete';
    renderBookingsTable();
    updateBookingStats();
    // trigger immediate poll so other open pages refresh
    if (typeof pollBookings === 'function') pollBookings();
    showToast('Booking marked as complete.');
    closeModal('detailModal');
  } else {
    alert(result.message || 'Could not update booking.');
  }
}

async function deleteBooking(id) {
  const booking = bookings.find(x => x.id === id);
  if (!booking) return;
  if (!confirm('Delete this booking? This cannot be undone.')) return;
  // remove locally immediately so it disappears from the table
  const idx = bookings.indexOf(booking);
  let removed = null;
  if (idx !== -1) {
    removed = bookings.splice(idx, 1)[0];
    renderBookingsTable();
    updateBookingStats();
    showToast('Booking deleted.');
    closeModal('detailModal');
  }

  // If booking exists on server, request deletion; otherwise we're done
  if (!booking.bookingId) return;

  try {
    const result = await postDashboardAction('delete', booking);
    if (result && result.success) {
      // notify other tabs
      if (typeof pollBookings === 'function') pollBookings();
      return;
    }
    // server failed - refresh from server to restore state
    alert(result.message || 'Could not delete booking on server.');
    if (typeof pollBookings === 'function') pollBookings();
  } catch (e) {
    // network error - refresh to keep UI in sync
    console.error('Delete error', e);
    if (typeof pollBookings === 'function') pollBookings();
  }
}

// ===== INIT =====
renderBookingsTable();
updateBookingStats();
renderCal();

// Set today as min for new booking date fields
document.getElementById('nb-date').min = todayStr;
document.getElementById('nb-date').value = todayStr;
document.getElementById('mnb-date').value = todayStr;
</script>
</body>
</html>
