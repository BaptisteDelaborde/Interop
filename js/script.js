const state = {
    lat: 48.6921,
    lon: 6.1844,
    ville: 'Nancy',
    rain: 0,
    temp: 0,
    airQual: 'Inconnue',
    airCode: 0
};

let map;
document.addEventListener('DOMContentLoaded', init);
async function init() {
    console.log("Démarrage de l'application...");

    // Géolocalisation
    await fetchGeolocation();

    // Initialiser la carte
    initMap();

    // Lancer les appels en parallèle
    await Promise.all([
        fetchWeather(),
        fetchAirQuality(),
        fetchVelos()
    ]);

    makeDecision();
}

/* Géolocalisation */

async function fetchGeolocation() {
    const url = 'http://ip-api.com/json/';
    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.status === 'success') {
            state.lat = data.lat;
            state.lon = data.lon;
            state.ville = data.city;
        }
    } catch (error) {
        console.warn("Géolocalisation échouée.");
    }

    document.getElementById('geo-ville').textContent = state.ville;
    document.getElementById('geo-lat').textContent = state.lat;
    document.getElementById('geo-lon').textContent = state.lon;
}

/* Carte */

function initMap() {
    map = L.map('map').setView([state.lat, state.lon], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker([state.lat, state.lon]).addTo(map)
        .bindPopup(`<b>Vous êtes ici</b><br>${state.ville}`)
        .openPopup();
}

/* Météo */

async function fetchWeather() {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${state.lat}&longitude=${state.lon}&current_weather=true`;

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.current_weather) {
            state.temp = data.current_weather.temperature;
            const wCode = data.current_weather.weathercode;
            const isRaining = wCode >= 50;
            state.rain = isRaining ? 1 : 0;

            let icon = "☀️";
            if(wCode > 3) icon = "☁️";
            if(isRaining) icon = "🌧️";
            if(wCode >= 71) icon = "❄️";

            document.getElementById('meteo-data').innerHTML = `
                <div class="info-row"><span class="icon-large">${icon}</span> <strong>${state.temp}°C</strong></div>
                <div>Vent: ${data.current_weather.windspeed} km/h</div>
            `;

            const now = new Date();
            document.getElementById('date-meteo').textContent = now.toLocaleTimeString('fr-FR');
        }
    } catch (error) {
        console.error("Erreur Météo:", error);
    }
}

/* Qualité de l'air */

async function fetchAirQuality() {
    const url = "https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27Nancy%27&outFields=*&f=json";

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.features && data.features.length > 0) {

            const today = new Date();
            today.setHours(0,0,0,0);

            let forecast = data.features.find(item => {
                const itemDate = new Date(item.attributes.date_ech);
                itemDate.setHours(0,0,0,0);
                return itemDate.getTime() === today.getTime();
            });

            if (!forecast) {
                data.features.sort((a, b) => a.attributes.date_ech - b.attributes.date_ech);
                forecast = data.features[data.features.length - 1];
            }

            const attr = forecast.attributes;

            state.airQual = attr.lib_qual;
            state.airCode = attr.code_qual;

            const el = document.getElementById('air-index');
            el.textContent = state.airQual;

            if (attr.coul_qual) {
                el.style.color = attr.coul_qual;
            } else {
                el.style.color = state.airCode > 3 ? "red" : "green";
            }

        } else {
            document.getElementById('air-index').textContent = "Données indisponibles";
        }
    } catch (error) {
        console.error("Erreur Air:", error);
        document.getElementById('air-index').textContent = "Erreur API";
    }
}

/* Vélo */

async function fetchVelos() {
    const urlInfo = "https://api.cyclocity.fr/contracts/nancy/gbfs/v3/station_information.json";
    const urlStatus = "https://api.cyclocity.fr/contracts/nancy/gbfs/v3/station_status.json";

    try {
        const [resInfo, resStatus] = await Promise.all([
            fetch(urlInfo),
            fetch(urlStatus)
        ]);

        const dataInfo = await resInfo.json();
        const dataStatus = await resStatus.json();

        const statusMap = {};
        dataStatus.data.stations.forEach(stat => {
            statusMap[stat.station_id] = stat;
        });

        dataInfo.data.stations.forEach(station => {
            const stat = statusMap[station.station_id];

            if (stat) {
                const lat = station.lat;
                const lon = station.lon;
                const name = station.name[0].text;

                const velosDispo = stat.num_vehicles_available;
                const placesDispo = stat.num_docks_available;

                const color = velosDispo > 0 ? '#27ae60' : '#c0392b';

                const marker = L.circleMarker([lat, lon], {
                    radius: 6,
                    fillColor: color,
                    color: "#fff",
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.9
                });

                marker.bindPopup(`
                    <strong>${name}</strong><br>
                    🚲 Vélos : ${velosDispo}<br>
                    🅿️ Places : ${placesDispo}
                `);

                marker.addTo(map);
            }
        });

    } catch (error) {
        console.error("Erreur Vélos:", error);
    }
}


/* Changement de couleur */
function makeDecision() {
    document.getElementById('decision-loading').style.display = 'none';
    document.getElementById('decision-content').style.display = 'block';

    const txt = document.getElementById('conseil-texte');
    const det = document.getElementById('conseil-details');

    let score = 10;
    let reasons = [];

    if (state.rain > 0) { score -= 5; reasons.push("Pluie"); }
    if (state.temp < 5) { score -= 2; reasons.push("Froid"); }
    if (state.airCode > 3) { score -= 3; reasons.push("Pollution"); }

    if (score >= 7) {
        txt.textContent = "🚲 OUI, c'est bon !";
        txt.style.color = "#27ae60";
    } else if (score >= 4) {
        txt.textContent = "🤔 Pourquoi pas...";
        txt.style.color = "#f39c12";
    } else {
        txt.textContent = "🚌 Privilégiez le bus.";
        txt.style.color = "#c0392b";
    }
    det.textContent = reasons.length > 0 ? "Info : " + reasons.join(", ") : "Conditions idéales.";
}