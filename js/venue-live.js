(function () {
  var cfg = window.pbVenue || {};
  var mapEl = document.getElementById('venueMap');
  if (mapEl && window.L) {
    var map = L.map(mapEl).setView([cfg.lat || 9.65, cfg.lng || 123.85], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    L.marker([cfg.lat || 9.65, cfg.lng || 123.85]).addTo(map);
    setTimeout(function () { map.invalidateSize(); }, 200);
  }

  var liveDot = document.getElementById('liveDot');
  var liveLabel = document.getElementById('liveLabel');
  var slotGrid = document.getElementById('slotGrid');

  function refreshSlots() {
    if (!cfg.courtId || !slotGrid || !window.jQuery) return;
    jQuery.post('process.php', {
      function: 'GetCourtSlots',
      court_id: cfg.courtId,
      date: cfg.date
    }, function (data) {
      if (!data || data.validation_status !== 'T' || !data.slots) return;
      if (!data.slots.length) {
        slotGrid.innerHTML = '<p>No open slots on this date.</p>';
        return;
      }
      var html = '';
      data.slots.forEach(function (slot) {
        var url = 'book.php?court_id=' + encodeURIComponent(cfg.courtId) +
          '&start=' + encodeURIComponent(slot.start) +
          '&end=' + encodeURIComponent(slot.end);
        html += '<a class="pb-slot-btn" href="' + url + '">' +
          (slot.label || '') +
          '<span class="price">₱' + Number(slot.price).toFixed(2) + '</span></a>';
      });
      slotGrid.innerHTML = html;
    }, 'json');
  }

  function setLive(on, text) {
    if (liveDot) liveDot.classList.toggle('is-on', !!on);
    if (liveLabel) liveLabel.textContent = text;
  }

  // Polling fallback every 45s
  setInterval(refreshSlots, 45000);

  if (!window.io || !cfg.realtimeUrl) {
    setLive(false, 'Live updates offline (refreshing periodically)');
    return;
  }

  try {
    var socket = io(cfg.realtimeUrl, { transports: ['websocket', 'polling'], timeout: 2500 });
    socket.on('connect', function () {
      setLive(true, 'Live availability on');
      socket.emit('join', ['biz:' + cfg.businessId, 'court:' + cfg.courtId]);
    });
    socket.on('disconnect', function () {
      setLive(false, 'Reconnecting…');
    });
    socket.on('connect_error', function () {
      setLive(false, 'Live updates offline (refreshing periodically)');
    });
    socket.on('booking_updated', function (payload) {
      if (!payload) return;
      if (payload.business_id && Number(payload.business_id) !== Number(cfg.businessId)) return;
      refreshSlots();
    });
  } catch (e) {
    setLive(false, 'Live updates offline (refreshing periodically)');
  }
})();
