let map = L.map('map').setView([41.3, 69.2], 7);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 18,
  attribution: '© OpenStreetMap'
}).addTo(map);

function loadEvents(type = '') {
  fetch(`api/get_events.php?type=${type}`)
    .then(res => res.json())
    .then(data => {
      data.forEach(ev => {
        let iconColor = ev.type === 'jinoyat' ? 'red' :
                        ev.type === 'yth' ? 'orange' :
                        ev.type === 'favqulodda' ? 'purple' :
                        ev.type === 'ekologiya' ? 'green' : 'blue';
        L.circleMarker([ev.lat, ev.lng], {
          color: iconColor,
          radius: 6
        }).bindPopup(`<b>${ev.type.toUpperCase()}</b><br>${ev.desc}<br><small>${ev.date}</small>`).addTo(map);
      });
    });
}

document.getElementById('filterBtn').addEventListener('click', () => {
  map.eachLayer((layer) => { if (layer instanceof L.CircleMarker) map.removeLayer(layer); });
  loadEvents(document.getElementById('eventType').value);
});

loadEvents();
