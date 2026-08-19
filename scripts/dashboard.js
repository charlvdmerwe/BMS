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
  settings: "Business Info"
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
  DAYS[today.getDay()] + ' · ' + BUSINESS.name;

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
  waBtn.href = `https://wa.me/${b.phone.replace(/\D/g,'')}?text=${encodeURIComponent(`Hi ${b.name.split(' ')[0]}, your booking at ${BUSINESS.name} is set for ${b.service} on ${prettyDate(b.date)} at ${b.time}. See you then!`)}`;
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
  document.getElementById('mnb-date').value = todayStr;
  document.getElementById('mnb-date').min = todayStr;
  fetchAndUpdateTimeSelect(todayStr, 'mnb-time', 'mnb-booked-msg').then(() => {
    if (time) document.getElementById('mnb-time').value = time;
  });
  openModal('newModal');
}

async function saveModalBooking() {
  const name = document.getElementById('mnb-name').value.trim();
  const phone = document.getElementById('mnb-phone').value.trim();
  const service = document.getElementById('mnb-service').value;
  const date = document.getElementById('mnb-date').value;
  const time = document.getElementById('mnb-time').value;
  const notes = document.getElementById('mnb-notes').value.trim();

  if (!name || !phone || !service || !date) {
    showToast('Please fill in all required fields.'); return;
  }

  try {
    const res = await fetch('?action=getSlots&date=' + encodeURIComponent(date));
    const slotData = await res.json();
    if (slotData.success && slotData.bookedSlots.includes(time)) {
      showToast('That time slot is already booked. Please choose another.');
      fetchAndUpdateTimeSelect(date, 'mnb-time', 'mnb-booked-msg');
      return;
    }
  } catch (e) { /* proceed */ }

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

async function saveNewBooking() {
  const name = document.getElementById('nb-name').value.trim();
  const phone = document.getElementById('nb-phone').value.trim();
  const service = document.getElementById('nb-service').value;
  const date = document.getElementById('nb-date').value;
  const time = document.getElementById('nb-time').value;
  const notes = document.getElementById('nb-notes').value.trim();
  if (!name || !phone || !service || !date) { showToast('Fill in all required fields.'); return; }

  try {
    const res = await fetch('?action=getSlots&date=' + encodeURIComponent(date));
    const slotData = await res.json();
    if (slotData.success && slotData.bookedSlots.includes(time)) {
      showToast('That time slot is already booked. Please choose another.');
      fetchAndUpdateTimeSelect(date, 'nb-time', 'nb-booked-msg');
      return;
    }
  } catch (e) { /* proceed */ }

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

// ===== SLOT AVAILABILITY =====
async function fetchAndUpdateTimeSelect(dateValue, timeSelectId, msgId) {
  const select = document.getElementById(timeSelectId);
  const msg = document.getElementById(msgId);
  if (!dateValue) {
    Array.from(select.options).forEach(opt => { opt.disabled = false; });
    msg.style.display = 'none';
    return;
  }
  try {
    const res = await fetch('?action=getSlots&date=' + encodeURIComponent(dateValue));
    const data = await res.json();
    if (data.success) {
      Array.from(select.options).forEach(opt => {
        opt.disabled = data.bookedSlots.includes(opt.value);
      });
      if (data.bookedSlots.includes(select.value)) {
        const first = Array.from(select.options).find(o => !o.disabled);
        if (first) select.value = first.value;
      }
      msg.style.display = data.isFullyBooked ? 'block' : 'none';
    }
  } catch (e) {
    console.error('Could not fetch slots:', e);
  }
}

document.getElementById('nb-date').addEventListener('change', function() {
  fetchAndUpdateTimeSelect(this.value, 'nb-time', 'nb-booked-msg');
});

document.getElementById('mnb-date').addEventListener('change', function() {
  fetchAndUpdateTimeSelect(this.value, 'mnb-time', 'mnb-booked-msg');
});

// ===== INIT =====
renderBookingsTable();
updateBookingStats();

// Set today as min for new booking date fields
document.getElementById('nb-date').min = todayStr;
document.getElementById('nb-date').value = todayStr;
document.getElementById('mnb-date').value = todayStr;

// Check slot availability for today on the Add Booking panel
fetchAndUpdateTimeSelect(todayStr, 'nb-time', 'nb-booked-msg');
