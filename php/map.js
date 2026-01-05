document.addEventListener("DOMContentLoaded", () => {

  // Config Carte
  const lat = window.APP_LAT || 48.6921;
  const lon = window.APP_LON || 6.1844;

  const map = L.map("map").setView([lat, lon], 12);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap"
  }).addTo(map);

  // Marqueur position
  L.marker([lat, lon]).addTo(map).bindPopup("Vous êtes ici").openPopup();

  // Affichage Trafic
  if (window.trafficData && Array.isArray(window.trafficData)) {

    window.trafficData.forEach(inc => {
      // Choix de couleur selon le type
      let color = 'blue';
      if (inc.type === 'CONSTRUCTION') color = 'brown';
      if (inc.type === 'ACCIDENT') color = 'red';
      if (inc.type === 'JAM') color = 'purple';

      // Création du marqueur
      const circle = L.circleMarker([inc.lat, inc.lon], {
        color: color,
        fillColor: color,
        fillOpacity: 0.5,
        radius: 8
      }).addTo(map);

      let fin = inc.fin;
      try { fin = new Date(inc.fin).toLocaleDateString(); } catch(e){}

      circle.bindPopup(`
        <strong>${inc.type}</strong><br>
        ${inc.desc}<br>
        <small>Jusqu'au : ${fin}</small>
      `);
    });

  } else {
    console.log("Aucune donnée trafic reçue.");
  }
});