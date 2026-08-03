(function () {
  var points = window.pbHomeVenues || [];
  var el = document.getElementById('homeVenueMap');
  if (!el || !window.L) return;
  var map = L.map(el).setView([9.6508, 123.8534], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);
  var bounds = [];
  points.forEach(function (p) {
    var marker = L.marker([p.lat, p.lng]).addTo(map);
    marker.bindPopup('<strong>' + (p.name || 'Venue') + '</strong><br><a href="' + p.url + '">View & book →</a>');
    bounds.push([p.lat, p.lng]);
  });
  if (bounds.length) {
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
  }
  setTimeout(function () { map.invalidateSize(); }, 250);
})();
