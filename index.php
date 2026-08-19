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
<style>
  :root{
    --ink:#0F172A;
    --bay:#1E293B;
    --bay-light:#334155;
    --gold:#2563EB;
    --gold-soft:#93C5FD;
    --clay:#334155;
    --sand:#F8FAFC;
    --sand-deep:#E2E8F0;
    --ink-warm:#1E293B;
    --cream:#FFFFFF;
    --line-dark: rgba(255,255,255,0.14);
    --line-light: rgba(15,23,42,0.10);
    --radius: 6px;
    --maxw: 1180px;
  }

  *{box-sizing:border-box;}
  html{scroll-behavior:smooth;}
  @media (prefers-reduced-motion: reduce){
    html{scroll-behavior:auto;}
    *{animation-duration:0.001ms !important; animation-iteration-count:1 !important; transition-duration:0.001ms !important;}
  }

  body{
    margin:0;
    background:var(--sand);
    color:var(--ink-warm);
    font-family:'Inter', sans-serif;
    font-size:16px;
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
  }

  h1,h2,h3{
    font-family:'Inter', sans-serif;
    font-weight:600;
    margin:0;
    letter-spacing:-0.01em;
  }

  a{color:inherit;}

  .eyebrow{
    font-family:'Inter', sans-serif;
    font-size:0.72rem;
    letter-spacing:0.22em;
    text-transform:uppercase;
    display:inline-flex;
    align-items:center;
    gap:0.6em;
  }
  .eyebrow::before{
    content:"";
    width:18px;height:1px;
    background:currentColor;
    opacity:0.6;
  }

  .wrap{
    max-width:var(--maxw);
    margin:0 auto;
    padding:0 28px;
  }

  /* ---------- HEADER ---------- */
  header{
    position:fixed; top:0; left:0; right:0;
    z-index:50;
    padding:18px 0;
    transition:background 0.4s ease, padding 0.4s ease, box-shadow 0.4s ease;
  }
  header.scrolled{
    background:rgba(14,23,34,0.92);
    backdrop-filter:blur(6px);
    padding:12px 0;
    box-shadow:0 1px 0 var(--line-dark);
  }
  .header-row{
    display:flex; align-items:center; justify-content:space-between;
  }
  .brand{
    display:flex; align-items:center; gap:10px;
    color:var(--cream);
    text-decoration:none;
  }
  .brand-name{
    font-family:'Inter', sans-serif;
    font-weight:600;
    font-size:1.18rem;
    letter-spacing:0.01em;
    white-space:nowrap;
  }
  nav.main-nav{
    display:flex; align-items:center; gap:34px;
  }
  nav.main-nav a{
    color:var(--cream);
    text-decoration:none;
    font-size:0.92rem;
    letter-spacing:0.02em;
    opacity:0.85;
    position:relative;
    padding-bottom:4px;
  }
  nav.main-nav a:hover{opacity:1;}
  nav.main-nav a.cta{
    border:1px solid var(--gold);
    color:var(--gold-soft);
    padding:8px 18px;
    border-radius:var(--radius);
    opacity:1;
  }
  nav.main-nav a.cta:hover{
    background:var(--gold);
    color:var(--ink);
  }
  .nav-toggle{display:none;}
  .menu-btn{
    display:none;
    background:none; border:1px solid var(--line-dark);
    color:var(--cream); padding:8px 12px; border-radius:var(--radius);
    font-family:'Inter', sans-serif; font-size:0.8rem;
    cursor:pointer;
  }

  @media (max-width:820px){
    nav.main-nav{
      position:absolute; top:100%; left:0; right:0;
      background:var(--ink);
      flex-direction:column;
      align-items:flex-start;
      padding:18px 28px 26px;
      gap:18px;
      display:none;
      border-bottom:1px solid var(--line-dark);
    }
    .nav-toggle:checked ~ nav.main-nav{display:flex;}
    .menu-btn{display:inline-block;}
    nav.main-nav a.cta{margin-top:6px;}
  }

  /* ---------- HERO ---------- */
  .hero{
    position:relative;
    background:
      radial-gradient(ellipse at 75% 0%, rgba(207,160,82,0.16), transparent 55%),
      linear-gradient(165deg, var(--ink) 0%, var(--bay) 58%, var(--bay-light) 100%);
    color:var(--cream);
    padding:170px 0 0;
    overflow:hidden;
  }
  .hero-inner{
    position:relative;
    padding-bottom:150px;
  }
  .hero .eyebrow{color:var(--gold-soft);}
  .hero h1{
    font-size:clamp(2.6rem, 6vw, 4.6rem);
    font-weight:600;
    line-height:1.04;
    margin:22px 0 26px;
    max-width:14ch;
  }
  .hero h1 em{
    font-style:italic;
    color:var(--gold-soft);
    font-weight:500;
  }
  .hero p.lede{
    font-size:1.12rem;
    max-width:46ch;
    color:rgba(250,246,236,0.82);
    margin-bottom:38px;
  }
  .hero-actions{
    display:flex; gap:16px; flex-wrap:wrap;
    margin-bottom:54px;
  }
  .btn{
    font-family:'Inter', sans-serif;
    font-weight:600;
    font-size:0.95rem;
    padding:14px 28px;
    border-radius:var(--radius);
    text-decoration:none;
    display:inline-flex; align-items:center; gap:10px;
    border:1px solid transparent;
    cursor:pointer;
    transition:transform 0.15s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
  }
  .btn:active{transform:translateY(1px);}
  .btn-gold{background:var(--gold); color:var(--ink);}
  .btn-gold:hover{background:var(--gold-soft);}
  .btn-ghost{border-color:rgba(250,246,236,0.4); color:var(--cream);}
  .btn-ghost:hover{border-color:var(--gold-soft); color:var(--gold-soft);}
  .btn-clay{background:var(--clay); color:var(--cream);}
  .btn-clay:hover{background:#9c4926;}

  .hero-meta{
    display:flex; gap:38px; flex-wrap:wrap;
    font-family:'Inter', sans-serif;
    font-size:0.8rem;
    color:rgba(250,246,236,0.65);
    border-top:1px solid var(--line-dark);
    padding-top:22px;
  }
  .hero-meta strong{color:var(--cream); font-weight:400;}

  .wave{
    position:absolute; left:0; right:0; bottom:-1px;
    width:100%; display:block; line-height:0;
  }
  .wave svg{width:100%; height:90px; display:block;}

  /* ---------- SECTION GENERIC ---------- */
  section{padding:108px 0;}
  .section-dark{background:var(--ink); color:var(--cream);}
  .section-bay{background:var(--bay); color:var(--cream);}
  .section-head{
    max-width:640px;
    margin-bottom:56px;
  }
  .section-head .eyebrow{margin-bottom:18px; color:var(--clay);}
  .section-dark .section-head .eyebrow, .section-bay .section-head .eyebrow{color:var(--gold-soft);}
  .section-head h2{font-size:clamp(1.9rem,3.6vw,2.7rem); line-height:1.12;}

  /* ---------- ABOUT ---------- */
  .about-grid{
    display:grid;
    grid-template-columns:1.1fr 0.9fr;
    gap:60px;
    align-items:start;
  }
  .about-grid p{font-size:1.04rem; color:#473521; max-width:50ch;}
  .stat-cards{
    display:grid; gap:1px;
    background:var(--line-light);
    border:1px solid var(--line-light);
  }
  .stat-card{
    background:var(--sand);
    padding:22px 24px;
    display:flex; justify-content:space-between; gap:18px;
  }
  .stat-card .label{
    font-family:'Inter', sans-serif;
    font-size:0.72rem; letter-spacing:0.14em; text-transform:uppercase;
    color:#7A6644; padding-top:3px;
  }
  .stat-card .value{
    font-family:'Inter', sans-serif; font-size:1.05rem; font-weight:500;
    text-align:right;
  }
  .stat-card .value small{
    display:block; font-family:'Inter'; font-weight:400;
    font-size:0.82rem; color:#7A6644; margin-top:2px;
  }

  @media (max-width:880px){
    .about-grid{grid-template-columns:1fr;}
  }

  /* ---------- SERVICES ---------- */
  .service-grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:1px;
    background:var(--line-dark);
    border:1px solid var(--line-dark);
  }
  .service-card{
    background:var(--ink);
    padding:34px 28px;
    display:flex; flex-direction:column; gap:14px;
    min-height:200px;
  }
  .service-card .num{
    font-family:'Inter', sans-serif;
    font-size:0.72rem; color:var(--clay);
    letter-spacing:0.1em;
  }
  .service-card h3{
    font-size:1.28rem; font-weight:500; color:var(--cream);
  }
  .service-card p{
    font-size:0.92rem; color:rgba(250,246,236,0.66);
    margin:0; flex-grow:1;
  }
  .service-card .price{
    font-family:'Inter', sans-serif;
    font-size:0.85rem; color:var(--gold-soft);
    border-top:1px solid var(--line-dark);
    padding-top:14px;
  }
  @media (max-width:880px){
    .service-grid{grid-template-columns:1fr;}
  }
  .services-note{
    margin-top:24px;
    font-size:0.82rem;
    color:rgba(250,246,236,0.5);
    font-family:'Inter', sans-serif;
  }

  /* ---------- BOOKING ---------- */
  .booking-shell{
    position:relative;
    background:var(--sand-deep);
    border:1px solid var(--line-light);
    border-radius:var(--radius);
    overflow:hidden;
  }
  .booking-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
  }
  .booking-info{
    background:var(--ink);
    color:var(--cream);
    padding:48px 42px;
    position:relative;
  }
  .booking-info h3{font-size:1.7rem; margin-bottom:18px; position:relative;}
  .booking-info p{color:rgba(250,246,236,0.75); position:relative; max-width:34ch;}
  .booking-info ul{
    list-style:none; padding:0; margin:30px 0 0;
    font-family:'Inter', sans-serif; font-size:0.82rem;
    color:rgba(250,246,236,0.85);
    display:flex; flex-direction:column; gap:12px;
    position:relative;
  }
  .booking-info li{display:flex; gap:10px; align-items:baseline;}
  .booking-info li::before{content:"·"; color:var(--gold-soft);}

  .booking-form{
    padding:48px 42px;
    background:var(--sand);
  }
  .field{margin-bottom:20px;}
  .field label{
    display:block; font-size:0.78rem; letter-spacing:0.06em;
    text-transform:uppercase; font-family:'Inter', sans-serif;
    color:#5F4E32; margin-bottom:8px;
  }
  .field input, .field select, .field textarea{
    width:100%;
    font-family:'Inter', sans-serif;
    font-size:0.96rem;
    padding:12px 14px;
    border:1px solid var(--line-light);
    background:var(--cream);
    color:var(--ink-warm);
    border-radius:var(--radius);
  }
  .field textarea{resize:vertical; min-height:70px;}
  .field input:focus, .field select:focus, .field textarea:focus{
    outline:2px solid var(--clay); outline-offset:1px;
  }
  .two-col{display:grid; grid-template-columns:1fr 1fr; gap:18px;}

  .slot-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:8px;
  }
  .slot{
    font-family:'Inter', sans-serif;
    font-size:0.82rem;
    padding:10px 6px;
    text-align:center;
    background:var(--cream);
    border:1px solid var(--line-light);
    border-radius:var(--radius);
    cursor:pointer;
    transition:background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
  }
  .slot:hover:not(:disabled){border-color:var(--clay);}
  .slot.selected{
    background:var(--clay); color:var(--cream); border-color:var(--clay);
  }
  .slot:focus-visible{outline:2px solid var(--clay); outline-offset:2px;}
  .slot:disabled{
    opacity:0.5;
    cursor:not-allowed;
    background:var(--sand-deep);
    color:#999;
  }
  .slot.booked{
    opacity:0.4;
    cursor:not-allowed;
    background:#e0e0e0;
    color:#666;
  }

  .form-actions{margin-top:28px;}
  .form-note{
    font-size:0.78rem; color:#7A6644; margin-top:14px;
  }

  .confirm-panel{
    display:none;
    margin-top:26px;
    padding:22px;
    border:1px dashed var(--clay);
    border-radius:var(--radius);
    background:var(--cream);
  }
  .confirm-panel.show{display:block;}
  .confirm-panel h4{
    font-family:'Inter', sans-serif; font-size:1.1rem; margin-bottom:10px;
  }
  .confirm-panel .summary{
    font-family:'Inter', sans-serif; font-size:0.82rem;
    color:#473521; white-space:pre-line; margin-bottom:18px;
    line-height:1.7;
  }
  .confirm-actions{display:flex; gap:10px; flex-wrap:wrap;}
  .confirm-actions .btn{padding:10px 18px; font-size:0.85rem;}
  .toast{
    font-family:'Inter', sans-serif;
    font-size:0.76rem;
    color:var(--clay);
    margin-top:10px;
    opacity:0;
    transition:opacity 0.3s ease;
  }
  .toast.show{opacity:1;}

  @media (max-width:880px){
    .booking-grid{grid-template-columns:1fr;}
    .booking-info, .booking-form{padding:36px 26px;}
    .two-col{grid-template-columns:1fr;}
    .slot-grid{grid-template-columns:repeat(2,1fr);}
  }

  /* ---------- VISIT / LOCATION ---------- */
  .visit-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:1px;
    background:var(--line-dark);
    border:1px solid var(--line-dark);
  }
  .visit-card{
    background:var(--ink);
    padding:40px;
    color:var(--cream);
  }
  .visit-card .eyebrow{color:var(--gold-soft); margin-bottom:18px;}
  .visit-card h3{font-size:1.5rem; margin-bottom:16px;}
  .visit-card a.line, .visit-card .line{
    display:block; text-decoration:none; color:var(--cream);
    font-size:0.98rem; margin-bottom:10px;
  }
  .visit-card a.line:hover{color:var(--gold-soft);}
  .map-card iframe{width:100%; height:100%; min-height:380px; border:0; display:block; filter:grayscale(0.15) contrast(1.05);}
  @media (max-width:880px){
    .visit-grid{grid-template-columns:1fr;}
  }

  /* ---------- FOOTER ---------- */
  footer{
    background:var(--ink);
    color:rgba(250,246,236,0.55);
    padding:44px 0 32px;
    border-top:1px solid var(--line-dark);
  }
  .footer-row{
    display:flex; justify-content:space-between; align-items:center;
    flex-wrap:wrap; gap:18px;
    font-size:0.85rem;
  }
  .footer-row .brand-name{color:var(--cream); font-size:1rem;}
  footer a{color:rgba(250,246,236,0.55); text-decoration:none;}
  footer a:hover{color:var(--gold-soft);}
  .footer-links{display:flex; gap:22px;}

  .sr-only{
    position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0);
  }
</style>
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

  // header scroll state
  const header = document.getElementById('siteHeader');
  window.addEventListener('scroll', () => {
    header.classList.toggle('scrolled', window.scrollY > 12);
  });

  document.getElementById('year').textContent = new Date().getFullYear();

  // close mobile nav after clicking a link
  document.querySelectorAll('nav.main-nav a').forEach(a => {
    a.addEventListener('click', () => { document.getElementById('navToggle').checked = false; });
  });

  // date min = today
  const dateInput = document.getElementById('bf-date');
  const today = new Date();
  const todayStr = today.toISOString().split('T')[0];
  dateInput.min = todayStr;

  // time slots
  const slotGrid = document.getElementById('slotGrid');
  const allSlots = TIME_SLOTS;
  const slotButtons = {};
  let selectedSlot = null;
  let bookedSlotsForDate = [];
  
  // Initialize all slot buttons
  allSlots.forEach(time => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'slot';
    btn.textContent = time;
    btn.dataset.time = time;
    btn.addEventListener('click', () => {
      if (btn.disabled || btn.classList.contains('booked')) return;
      document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
      btn.classList.add('selected');
      selectedSlot = time;
    });
    slotButtons[time] = btn;
    slotGrid.appendChild(btn);
  });

  // Function to update available slots based on date
  async function updateAvailableSlots(dateStr) {
    if (!dateStr) {
      allSlots.forEach(time => {
        slotButtons[time].disabled = false;
        slotButtons[time].classList.remove('booked', 'selected');
      });
      document.getElementById('slotMessage').style.display = 'none';
      return;
    }

    try {
      const response = await fetch('?action=getSlots&date=' + encodeURIComponent(dateStr));
      const data = await response.json();
      
      if (data.success) {
        bookedSlotsForDate = data.bookedSlots;
        
        // Update slot states
        allSlots.forEach(time => {
          const btn = slotButtons[time];
          const isBooked = data.bookedSlots.includes(time);
          
          if (isBooked) {
            btn.disabled = true;
            btn.classList.add('booked');
            btn.classList.remove('selected');
            btn.title = 'This time slot is booked';
          } else {
            btn.disabled = false;
            btn.classList.remove('booked');
            btn.title = '';
          }
        });
        
        const slotMsg = document.getElementById('slotMessage');
        slotMsg.style.display = data.isFullyBooked ? 'block' : 'none';

        // Clear selected slot if it was in the booked list
        if (selectedSlot && bookedSlotsForDate.includes(selectedSlot)) {
          selectedSlot = null;
          document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
        }
      }
    } catch (err) {
      console.error('Could not fetch available slots:', err);
    }
  }

  // Event listener for date changes
  dateInput.addEventListener('change', (e) => {
    updateAvailableSlots(e.target.value);
  });

  // booking form submit -> save booking to database
  const form = document.getElementById('bookingForm');
  const bookingToast = document.getElementById('bookingToast');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!selectedSlot) {
      alert('Please choose a preferred time slot.');
      return;
    }

    // Double-check that the slot is not booked
    const selectedDate = dateInput.value;
    const response = await fetch('?action=getSlots&date=' + encodeURIComponent(selectedDate));
    const data = await response.json();
    
    if (data.success && data.bookedSlots.includes(selectedSlot)) {
      alert('This time slot has just been booked. Please select another slot.');
      updateAvailableSlots(selectedDate);
      return;
    }

    document.getElementById('bf-time').value = selectedSlot;
    const formData = new FormData(form);
    formData.set('ajax', '1');

    let result;
    try {
      const response = await fetch(window.location.href, {
        method: 'POST',
        body: formData,
      });
      result = await response.json();
    } catch (err) {
      alert('Could not save booking. Please try again.');
      return;
    }

    if (!result.success) {
      alert(result.message || 'Could not save booking. Please try again.');
      return;
    }

    bookingToast.textContent = 'Your booking has been saved. Call ' + BUSINESS_PHONE + ' if you want to verify it.';
    bookingToast.style.opacity = '1';
    setTimeout(() => {
      bookingToast.style.opacity = '0';
    }, 5000);

    form.reset();
    selectedSlot = null;
    document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
  });
</script>

</body>
</html>