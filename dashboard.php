<?php
require __DIR__ . '/config.php';

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
    if (empty($price) && !empty($_POST['service']) && isset($services[$_POST['service']])) {
      $price = $services[$_POST['service']];
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
  
  $stmt = $mysqli->prepare("SELECT `Time` FROM `{$dbTable}` WHERE `Date` = ?");
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
    $bookedSlots[] = substr($row['Time'], 0, 5);
  }

  $stmt->close();
  $mysqli->close();

  $isFullyBooked = count($bookedSlots) === count($timeSlots);

  echo json_encode([
    'success' => true,
    'date' => $date,
    'bookedSlots' => $bookedSlots,
    'isFullyBooked' => $isFullyBooked,
    'availableSlots' => array_values(array_diff($timeSlots, $bookedSlots))
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
<title><?php echo htmlspecialchars($businessName); ?> — Booking Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="stylesheets/dashboard.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div>
      <div class="brand-text"><?php echo htmlspecialchars($businessName); ?></div>
      <span class="brand-sub">Dashboard</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Manage</div>
    <div class="nav-item active" data-panel="bookings">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="1" y="2" width="14" height="12" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M4 6h8M4 9h5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      All Bookings
      <span class="nav-badge" id="newBadge">0</span>
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

    <div class="nav-section-label" style="margin-top:8px;">Settings</div>
    <div class="nav-item" id="waLink" data-panel="settings">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="6" r="3" stroke="currentColor" stroke-width="1.3"/><path d="M2 14c0-3 2.7-5 6-5s6 2 6 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      Business Info
    </div>
  </nav>

  <div class="sidebar-footer">
    <span class="status-dot"></span>Open <?php echo htmlspecialchars($businessOpenDays); ?>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <h1 id="topbarTitle">All Bookings</h1>
      <div class="topbar-sub" id="topbarSub"><?php echo htmlspecialchars($businessAddress); ?></div>
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
            <?php foreach ($services as $serviceName => $servicePrice): ?>
            <option><?php echo htmlspecialchars($serviceName); ?></option>
            <?php endforeach; ?>
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
              <input type="text" id="nb-name" placeholder="e.g. Jane Smith">
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
                <?php foreach ($services as $serviceName => $servicePrice): ?>
                <option><?php echo htmlspecialchars($serviceName); ?></option>
                <?php endforeach; ?>
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
                <?php foreach ($timeSlots as $slot): ?>
                <option><?php echo htmlspecialchars($slot); ?></option>
                <?php endforeach; ?>
              </select>
              <div id="nb-booked-msg" style="display:none; margin-top:6px; font-family:'Inter',sans-serif; font-size:0.72rem; color:var(--clay);">This day is fully booked.</div>
            </div>
          </div>
          <div class="modal-field" style="margin-bottom:24px;">
            <label>Notes (optional)</label>
            <textarea id="nb-notes" placeholder="Notes, preferences, special requests…"></textarea>
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
          <div class="stat-value" style="font-size:1.2rem; font-family:'Inter',sans-serif; font-weight:600; padding-top:4px;" id="servicesBestSeller">—</div>
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
          <div class="card-title">Business info</div>
        </div>
        <div style="padding:24px;">
          <div class="modal-row">
            <div class="modal-field">
              <label>Business name</label>
              <input type="text" value="<?php echo htmlspecialchars($businessName); ?>">
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Address</label>
              <input type="text" value="<?php echo htmlspecialchars($businessAddress); ?>">
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Phone</label>
              <input type="tel" value="<?php echo htmlspecialchars($businessPhone); ?>">
            </div>
            <div class="modal-field">
              <label>WhatsApp number</label>
              <input type="tel" value="<?php echo htmlspecialchars($businessPhoneIntl); ?>">
            </div>
          </div>
          <div class="modal-row">
            <div class="modal-field">
              <label>Open days</label>
              <input type="text" value="<?php echo htmlspecialchars($businessOpenDays); ?>">
            </div>
            <div class="modal-field">
              <label>Hours</label>
              <input type="text" value="<?php echo htmlspecialchars($businessHours); ?>">
            </div>
          </div>
          <div class="form-note" style="margin:-8px 0 14px; font-size:0.78rem; color:var(--muted);">Edit these values in config.php — this panel is a read-only summary.</div>
          <button class="btn btn-clay" onclick="showToast('Business info saved.')">Save changes</button>
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
          <input type="text" id="mnb-name" placeholder="e.g. Jane Smith">
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
            <?php foreach ($services as $serviceName => $servicePrice): ?>
            <option><?php echo htmlspecialchars($serviceName); ?></option>
            <?php endforeach; ?>
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
            <?php foreach ($timeSlots as $slot): ?>
            <option><?php echo htmlspecialchars($slot); ?></option>
            <?php endforeach; ?>
          </select>
          <div id="mnb-booked-msg" style="display:none; margin-top:6px; font-family:'Inter',sans-serif; font-size:0.72rem; color:var(--clay);">This day is fully booked.</div>
        </div>
      </div>
      <div class="modal-field">
        <label>Notes (optional)</label>
        <textarea id="mnb-notes" placeholder="Notes, preferences, special requests…"></textarea>
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
const BUSINESS = <?php echo json_encode(["name" => $businessName, "phone" => $businessPhone, "phoneIntl" => $businessPhoneIntl], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

const SERVICES = <?php echo json_encode(array_map(fn($name) => ["name" => $name, "count" => 0, "revenue" => 0], array_keys($services)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

const today = new Date();
const todayStr = today.toISOString().split("T")[0];
const DAYS = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];

const bookings = <?php echo json_encode($bookings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let nextId = <?php echo count($bookings) + 1; ?>;

const PRICES = <?php echo json_encode($services, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="scripts/dashboard.js"></script>
</body>
</html>
