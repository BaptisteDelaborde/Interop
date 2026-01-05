/**************************************************************
 * CONFIGURATION & ETAT GLOBAL
 **************************************************************/
const state = {
    lat: 48.6921,
    lon: 6.1844,
    ville: 'Nancy (Défaut)',
    rain: 0,
    temp: 0,
    airQual: 'Inconnue',
    airCode: 0
};

let map;

document.addEventListener('DOMContentLoaded', init);

async function init() {
    console.log("Démarrage de l'application...");

    // 1. Géolocalisation
    await fetchGeolocation();

    // 2. Initialiser la carte
    initMap();

    // 3. Lancer les appels en parallèle
    await Promise.all([
        fetchWeather(),
        fetchAirQuality(), // <--- Version corrigée
        fetchVelos()       // <--- Version JCDecaux
    ]);

    // 4. Prendre la décision
    makeDecision();
}


/**************************************************************
 * 1. GÉOLOCALISATION (IP-API)
 **************************************************************/
async function fetchGeolocation() {
    // Utiliser http:// en local. Si hébergé en https, le navigateur bloquera (Mixed Content).
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
        console.warn("Géo échouée (probablement bloqueur pub ou https), fallback Nancy.");
    }

    document.getElementById('geo-ville').textContent = state.ville;
    document.getElementById('geo-lat').textContent = state.lat;
    document.getElementById('geo-lon').textContent = state.lon;
}


/**************************************************************
 * 2. CARTE LEAFLET
 **************************************************************/
function initMap() {
    map = L.map('map').setView([state.lat, state.lon], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker([state.lat, state.lon]).addTo(map)
        .bindPopup(`<b>Vous êtes ici</b><br>${state.ville}`)
        .openPopup();
}


/**************************************************************
 * 3. MÉTÉO (Open-Meteo)
 **************************************************************/
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


/**************************************************************
 * 4. QUALITÉ AIR (Atmo Grand Est - ArcGIS)
 **************************************************************/
async function fetchAirQuality() {
    const url = "https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27Nancy%27&outFields=*&f=json";

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (data.features && data.features.length > 0) {

            // 1. Trouver la date d'aujourd'hui (sans les heures/minutes)
            const today = new Date();
            today.setHours(0,0,0,0);

            // 2. Chercher dans la liste l'élément qui correspond à aujourd'hui
            // date_ech est un timestamp en millisecondes
            let forecast = data.features.find(item => {
                const itemDate = new Date(item.attributes.date_ech);
                itemDate.setHours(0,0,0,0);
                return itemDate.getTime() === today.getTime();
            });

            // 3. Si on ne trouve pas "aujourd'hui" (ex: bug date), on prend le dernier élément dispo (le plus récent)
            if (!forecast) {
                // On trie par date pour être sûr d'avoir le dernier
                data.features.sort((a, b) => a.attributes.date_ech - b.attributes.date_ech);
                forecast = data.features[data.features.length - 1];
            }

            const attr = forecast.attributes;

            // Mise à jour des variables globales
            state.airQual = attr.lib_qual; // Ex: "Moyen"
            state.airCode = attr.code_qual; // Ex: 2

            // Mise à jour affichage
            const el = document.getElementById('air-index');
            el.textContent = state.airQual;

            // On utilise la couleur fournie par l'API (coul_qual)
            if (attr.coul_qual) {
                el.style.color = attr.coul_qual;
            } else {
                // Fallback si pas de couleur
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


/**************************************************************
 * 5. VÉLOS (API JCDecaux / Cyclocity GBFS)
 **************************************************************/
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

        // Mapping ID -> Status pour accès rapide
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


/**************************************************************
 * 6. DÉCISION
 **************************************************************/
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