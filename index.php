<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $price = $services[$service] ?? 0;

    if (!$name || !$phone || !$service || !$date || !$time) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields including preferred time.']);
        exit;
    }

    $nameParts = preg_split('/\s+/', $name, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';
    $extraInfo = "Service: {$service}";
    if ($notes !== '') {
        $extraInfo .= " | Notes: {$notes}";
    }

    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO `{$dbTable}` (`Name`, `Surname`, `ContactNum`, `Date`, `Time`, `Price`, `Complete`, `ExtraInfo`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }

    $complete = 0;
    $stmt->bind_param('sssssiis', $firstName, $lastName, $phone, $date, $time, $price, $complete, $extraInfo);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save booking.']);
    exit;
}

// AJAX: Get available slots for a given date
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getSlots') {
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
    
    // Get all booked slots for this date
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($businessName); ?> — Book an Appointment</title>
<meta name="description" content="<?php echo htmlspecialchars($businessName . '. ' . $businessTagline); ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%85%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="stylesheets/index.css">
</head>
<body>

<?php
$telDigits = preg_replace('/\D/', '', $businessPhoneIntl);
$telHref = 'tel:+' . $telDigits;
$waHref = 'https://wa.me/' . $telDigits;
$mapQuery = urlencode($businessAddress);
?>
<header id="siteHeader">
  <div class="wrap header-row">
    <a href="#home" class="brand">
      <span class="brand-name"><?php echo htmlspecialchars($businessName); ?></span>
    </a>
    <input type="checkbox" id="navToggle" class="nav-toggle">
    <label for="navToggle" class="menu-btn">MENU</label>
    <nav class="main-nav">
      <a href="#about">About</a>
      <a href="#services">Services</a>
      <a href="#booking">Book</a>
      <a href="#visit">Location</a>
      <a href="#booking" class="cta">Book Now</a>
    </nav>
  </div>
</header>

<section class="hero" id="home">
  <div class="wrap hero-inner">
    <span class="eyebrow">Online Booking</span>
    <h1><?php echo htmlspecialchars($businessTagline); ?></h1>
    <p class="lede"><?php echo htmlspecialchars($businessBlurb); ?></p>
    <div class="hero-actions">
      <a href="#booking" class="btn btn-gold">Book Now →</a>
      <a href="<?php echo htmlspecialchars($telHref); ?>" class="btn btn-ghost">Call <?php echo htmlspecialchars($businessPhone); ?></a>
    </div>
    <div class="hero-meta">
      <div><strong>Address</strong><br><?php echo htmlspecialchars($businessAddress); ?></div>
      <div><strong>Hours</strong><br><?php echo htmlspecialchars($businessOpenDays); ?> · <?php echo htmlspecialchars($businessHours); ?></div>
      <div><strong>Contact</strong><br><?php echo htmlspecialchars($businessPhone); ?></div>
    </div>
  </div>
  <div class="wave" aria-hidden="true">
    <svg viewBox="0 0 1200 90" preserveAspectRatio="none">
      <path d="M0,55 C150,20 300,80 450,50 C600,20 750,80 900,50 C1050,20 1150,55 1200,45 L1200,90 L0,90 Z" fill="#F8FAFC"/>
    </svg>
  </div>
</section>

<section id="about">
  <div class="wrap about-grid">
    <div>
      <div class="section-head">
        <span class="eyebrow">About</span>
        <h2>About <?php echo htmlspecialchars($businessName); ?></h2>
      </div>
      <p><?php echo htmlspecialchars($businessBlurb); ?></p>
      <p style="margin-top:16px;">Book ahead online below, and we'll confirm your appointment.</p>
    </div>
    <div class="stat-cards">
      <div class="stat-card">
        <div class="label">Address</div>
        <div class="value"><?php echo htmlspecialchars($businessAddress); ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Phone</div>
        <div class="value"><a href="<?php echo htmlspecialchars($telHref); ?>"><?php echo htmlspecialchars($businessPhone); ?></a><small>Call or WhatsApp</small></div>
      </div>
      <div class="stat-card">
        <div class="label">Hours</div>
        <div class="value"><?php echo htmlspecialchars($businessOpenDays); ?><small><?php echo htmlspecialchars($businessHours); ?></small></div>
      </div>
    </div>
  </div>
</section>

<section class="section-dark" id="services">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">What We Do</span>
      <h2>Our Services</h2>
    </div>
    <div class="service-grid">
      <?php $num = 1; foreach ($services as $serviceName => $servicePrice): ?>
      <div class="service-card">
        <span class="num"><?php echo str_pad($num++, 2, '0', STR_PAD_LEFT); ?></span>
        <h3><?php echo htmlspecialchars($serviceName); ?></h3>
        <p>Describe this service here — what it includes and what makes it worth booking.</p>
        <div class="price">from R<?php echo htmlspecialchars($servicePrice); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="services-note">Prices are starting estimates — final price may vary. Ask us for an exact quote.</div>
  </div>
</section>

<section id="booking">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Book an Appointment</span>
      <h2>Pick a service, a day, a time</h2>
    </div>

    <div class="booking-shell">
      <div class="booking-grid">
        <div class="booking-info">
          <h3>How booking works</h3>
          <p>Fill in your details and pick a slot. We don't take payment online — your request is sent straight to us to confirm.</p>
          <ul>
            <li>Choose your service &amp; preferred time</li>
            <li>Please give us a call to cancel</li>
          </ul>
        </div>
        <form class="booking-form" id="bookingForm">
          <div class="field">
            <label for="bf-name">Full name</label>
            <input type="text" id="bf-name" name="name" required placeholder="e.g. Jane Smith">
          </div>
          <div class="two-col">
            <div class="field">
              <label for="bf-phone">Phone number</label>
              <input type="tel" id="bf-phone" name="phone" required placeholder="082 000 0000">
            </div>
            <div class="field">
              <label for="bf-service">Service</label>
              <select id="bf-service" name="service" required>
                <option value="" disabled selected>Choose a service</option>
                <?php foreach ($services as $serviceName => $servicePrice): ?>
                <option><?php echo htmlspecialchars($serviceName); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="bf-date">Preferred date</label>
            <input type="date" id="bf-date" name="date" required>
          </div>
          <div class="field">
            <label>Preferred time</label>
            <div class="slot-grid" id="slotGrid" role="group" aria-label="Preferred time">
              <!-- slots injected by JS -->
            </div>
            <div id="slotMessage" style="display:none; margin-top:10px; font-family:'Inter',sans-serif; font-size:0.78rem; color:var(--clay);">This day is fully booked — please choose another date.</div>
            <input type="hidden" id="bf-time" name="time">
          </div>
          <div class="field">
            <label for="bf-notes">Anything we should know? (optional)</label>
            <textarea id="bf-notes" name="notes" placeholder="Notes, preferences, special requests…"></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-clay">Submit booking</button>
            <div class="form-note">Your booking will be saved. If you want to verify your booking, please call <?php echo htmlspecialchars($businessPhone); ?>.</div>
          </div>
          <div class="toast" id="bookingToast" style="margin-top:12px; opacity:0; transition:opacity 0.25s;">Booking saved successfully.</div>
        </form>
      </div>
    </div>
  </div>
</section>

<section class="section-bay" id="visit">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Find Us</span>
      <h2>Our Location</h2>
    </div>
    <div class="visit-grid">
      <div class="visit-card">
        <span class="eyebrow">Details</span>
        <h3><?php echo htmlspecialchars($businessName); ?></h3>
        <span class="line"><?php echo htmlspecialchars($businessAddress); ?></span>
        <a class="line" href="<?php echo htmlspecialchars($telHref); ?>"><?php echo htmlspecialchars($businessPhone); ?></a>
        <span class="line"><?php echo htmlspecialchars($businessOpenDays); ?> · <?php echo htmlspecialchars($businessHours); ?></span>
      </div>
      <div class="visit-card map-card" style="padding:0;">
        <iframe
          src="https://www.google.com/maps?q=<?php echo $mapQuery; ?>&output=embed"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="Map to <?php echo htmlspecialchars($businessName); ?>"></iframe>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="wrap footer-row">
    <span class="brand-name" style="font-family:'Inter',sans-serif;"><?php echo htmlspecialchars($businessName); ?></span>
    <div class="footer-links">
      <a href="#about">About</a>
      <a href="#services">Services</a>
      <a href="#booking">Book</a>
    </div>
    <span>© <span id="year"></span> <?php echo htmlspecialchars($businessName); ?></span>
  </div>
</footer>

<script>
  const BUSINESS_PHONE = <?php echo json_encode($businessPhone); ?>;
  const TIME_SLOTS = <?php echo json_encode($timeSlots); ?>;
</script>
<script src="scripts/index.js"></script>

</body>
</html>