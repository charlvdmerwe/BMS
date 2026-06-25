<?php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dbTheStyleBay';
$dbTable = 'tblbookings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $prices = [
        'Cut & Trim' => 160,
        'Braids & Cornrows' => 300,
        'Weave / Extensions' => 550,
        'Colour & Highlights' => 400,
        'Wash, Treat & Blow-dry' => 180,
        'Beard Shape-up' => 90,
        'Not sure — advise me' => 0,
    ];
    $price = $prices[$service] ?? 0;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>The Style Bay — Unisex Hair Salon, Belair</title>
<meta name="description" content="The Style Bay Unisex Hair Salon, Old Oak Centre, Belair, Cape Town. Cuts, braids, weaves, colour and grooming. Come as a stranger, leave as family. Book your chair online.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%91%91%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/index.css">
</head>
<body>

<header id="siteHeader">
  <div class="wrap header-row">
    <a href="#home" class="brand">
      <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M3 22c2.5-3 4.5-3 7 0s4.5 3 7 0 4.5-3 7 0 4.5 3 5 0" stroke="#CFA052" stroke-width="1.6" stroke-linecap="round"/>
        <path d="M5 18 9 7l4 8 3-9 3 9 4-8 4 11" stroke="#E8C988" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
      <span class="brand-name">The&nbsp;Style&nbsp;Bay</span>
    </a>
    <input type="checkbox" id="navToggle" class="nav-toggle">
    <label for="navToggle" class="menu-btn">MENU</label>
    <nav class="main-nav">
      <a href="#about">Our Bay</a>
      <a href="#services">Services</a>
      <a href="#booking">Book</a>
      <a href="#visit">Visit</a>
      <a href="#booking" class="cta">Book your chair</a>
    </nav>
  </div>
</header>

<section class="hero" id="home">
  <div class="wrap hero-inner">
    <span class="eyebrow">Unisex hair salon · Old Oak Centre, Belair</span>
    <h1>Come as a stranger,<br>leave as <em>family.</em></h1>
    <p class="lede">Cuts, braids, weaves and colour for every crown in the family — men, women and kids. Take a walk with us, sit in the chair, and let us take care of the rest.</p>
    <div class="hero-actions">
      <a href="#booking" class="btn btn-gold">Book your chair →</a>
      <a href="tel:+27219100519" class="btn btn-ghost">Call 021 910 0519</a>
    </div>
    <div class="hero-meta">
      <div><strong>Address</strong><br>39 Meerlust St, Old Oak Centre, Belair, Cape Town</div>
      <div><strong>Hours</strong><br>Tue – Sat · 09:00 – 18:00</div>
      <div><strong>Specialty</strong><br>Hair extensions &amp; weaves</div>
    </div>
  </div>
  <div class="wave" aria-hidden="true">
    <svg viewBox="0 0 1200 90" preserveAspectRatio="none">
      <path d="M0,55 C150,20 300,80 450,50 C600,20 750,80 900,50 C1050,20 1150,55 1200,45 L1200,90 L0,90 Z" fill="#F1E8D8"/>
    </svg>
  </div>
</section>

<section id="about">
  <div class="wrap about-grid">
    <div>
      <div class="section-head">
        <span class="eyebrow">Our Bay</span>
        <h2>A chair for everyone, a story for every crown</h2>
      </div>
      <p>The Style Bay sits in the Old Oak Centre in Belair — a small, easy-to-find salon with a big family feel. We work on every hair type and every age, from a quick kid's trim to a full set of braids or extensions. No appointment ever feels rushed, and nobody leaves without a mirror check they're happy with.</p>
      <p style="margin-top:16px;">Walk in, or book ahead online below — either way, you'll be looked after.</p>
    </div>
    <div class="stat-cards">
      <div class="stat-card">
        <div class="label">Address</div>
        <div class="value">Old Oak Centre<small>39 Meerlust St, Belair, Cape Town</small></div>
      </div>
      <div class="stat-card">
        <div class="label">Phone</div>
        <div class="value"><a href="tel:+27219100519">021 910 0519</a><small>Call or WhatsApp</small></div>
      </div>
      <div class="stat-card">
        <div class="label">Hours</div>
        <div class="value">Tue – Sat<small>09:00 – 18:00, closed Sun &amp; Mon</small></div>
      </div>
      <div class="stat-card">
        <div class="label">For</div>
        <div class="value">Men · Women · Kids<small>unisex, all hair types</small></div>
      </div>
    </div>
  </div>
</section>

<section class="section-dark" id="services">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">What We Do</span>
      <h2>Every service, one chair</h2>
    </div>
    <div class="service-grid">
      <div class="service-card">
        <span class="num">01</span>
        <h3>Cuts &amp; Trims</h3>
        <p>Men's, women's and kids' cuts — sharp lines, soft fades or a simple tidy-up.</p>
        <div class="price">from R120</div>
      </div>
      <div class="service-card">
        <span class="num">02</span>
        <h3>Braids &amp; Cornrows</h3>
        <p>Classic cornrows, box braids and protective styles, sized to your hair and your day.</p>
        <div class="price">from R250</div>
      </div>
      <div class="service-card">
        <span class="num">03</span>
        <h3>Weaves &amp; Extensions</h3>
        <p>Our specialty. Sew-ins, closures and full installs, fitted and blended to last.</p>
        <div class="price">from R450</div>
      </div>
      <div class="service-card">
        <span class="num">04</span>
        <h3>Colour &amp; Highlights</h3>
        <p>Full colour, highlights and toning — done with a patch test and a plan first.</p>
        <div class="price">from R350</div>
      </div>
      <div class="service-card">
        <span class="num">05</span>
        <h3>Wash, Treat &amp; Blow-dry</h3>
        <p>Deep treatment, scalp care and a finish you can walk straight out the door with.</p>
        <div class="price">from R150</div>
      </div>
      <div class="service-card">
        <span class="num">06</span>
        <h3>Beard Shape-up</h3>
        <p>Lined, trimmed and tidied — usually booked together with a cut.</p>
        <div class="price">from R80</div>
      </div>
    </div>
    <div class="services-note">Prices are starting estimates — final price depends on hair length and style. Ask in salon for an exact quote.</div>
  </div>
</section>

<section id="booking">
  <div class="wrap">
    <div class="section-head">
      <span class="eyebrow">Reserve Your Chair</span>
      <h2>Pick a service, a day, a time</h2>
    </div>

    <div class="booking-shell">
      <div class="booking-grid">
        <div class="booking-info">
          <svg class="tide-bg" viewBox="0 0 400 400" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0,80 C100,40 200,120 300,80 C350,60 380,90 400,80" stroke="#CFA052" stroke-width="2" fill="none"/>
            <path d="M0,180 C100,140 200,220 300,180 C350,160 380,190 400,180" stroke="#CFA052" stroke-width="2" fill="none"/>
            <path d="M0,280 C100,240 200,320 300,280 C350,260 380,290 400,280" stroke="#CFA052" stroke-width="2" fill="none"/>
          </svg>
          <h3>How booking works</h3>
          <p>Fill in your details and pick a slot. We don't take payment online — your request is sent straight to the salon to confirm.</p>
          <ul>
            <li>Choose your service &amp; preferred time</li>
            <li>Please give us a call to cancel</li>
          </ul>
        </div>
        <form class="booking-form" id="bookingForm">
          <div class="field">
            <label for="bf-name">Full name</label>
            <input type="text" id="bf-name" name="name" required placeholder="e.g. Thandi Joubert">
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
                <option>Cut &amp; Trim</option>
                <option>Braids &amp; Cornrows</option>
                <option>Weave / Extensions</option>
                <option>Colour &amp; Highlights</option>
                <option>Wash, Treat &amp; Blow-dry</option>
                <option>Beard Shape-up</option>
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
            <div id="slotMessage" style="display:none; margin-top:10px; font-family:'Space Mono',monospace; font-size:0.78rem; color:var(--clay);">This day is fully booked — please choose another date.</div>
            <input type="hidden" id="bf-time" name="time">
          </div>
          <div class="field">
            <label for="bf-notes">Anything we should know? (optional)</label>
            <textarea id="bf-notes" name="notes" placeholder="Hair length, reference photo, stylist preference…"></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-clay">Submit booking</button>
            <div class="form-note">Your booking will be saved. If you want to verify your booking, please call 021 910 0519.</div>
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
      <h2>Old Oak Centre, Belair</h2>
    </div>
    <div class="visit-grid">
      <div class="visit-card">
        <span class="eyebrow">Details</span>
        <h3>The Style Bay Unisex Hair Salon</h3>
        <span class="line">39 Meerlust St, Old Oak Centre<br>Belair, Cape Town, South Africa</span>
        <a class="line" href="tel:+27219100519">021 910 0519</a>
        <a class="line" href="https://www.facebook.com/thestylebayunisexhairsalon/" target="_blank" rel="noopener">Facebook — @thestylebayunisexhairsalon</a>
        <span class="line">Tue – Sat · 09:00 – 18:00 · Closed Sun &amp; Mon</span>
      </div>
      <div class="visit-card map-card" style="padding:0;">
        <iframe
          src="https://www.google.com/maps?q=Old+Oak+Centre,+39+Meerlust+St,+Belair,+Cape+Town&output=embed"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="Map to The Style Bay Unisex Hair Salon, Old Oak Centre, Belair, Cape Town"></iframe>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="wrap footer-row">
    <span class="brand-name" style="font-family:'Fraunces',serif;">The Style Bay</span>
    <div class="footer-links">
      <a href="#about">Our Bay</a>
      <a href="#services">Services</a>
      <a href="#booking">Book</a>
      <a href="https://www.facebook.com/thestylebayunisexhairsalon/" target="_blank" rel="noopener">Facebook</a>
    </div>
    <span>© <span id="year"></span> The Style Bay Unisex Hair Salon</span>
  </div>
</footer>

<script>
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
  const allSlots = ['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00'];
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

    bookingToast.textContent = 'Your booking has been saved. Call 021 910 0519 if you want to verify it.';
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