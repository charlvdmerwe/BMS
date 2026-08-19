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
