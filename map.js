document.addEventListener("DOMContentLoaded", () => {

  // Coordonnées envoyées depuis PHP (fallback Nancy)
  const lat = window.APP_LAT ?? 48.6921;
  const lon = window.APP_LON ?? 6.1844;

  const map = L.map("map").setView([lat, lon], 12);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap"
  }).addTo(map);

  // Marqueur de position (IUT / Nancy)
  L.marker([lat, lon])
    .addTo(map)
    .bindPopup("Position de référence")
    .openPopup();
});
