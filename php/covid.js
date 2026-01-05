/**
 * covid.js
 * Interopérabilité – DWM
 *
 * Affichage de la dynamique Covid (SRAS-CoV-2)
 * à partir des données des eaux usées (SUMEAU)
 * via Chart.js
 *
 * Les données sont injectées par atmosphere.php
 * sous la forme :
 *   const covidData = [{ date: "...", value: ... }, ...]
 */

document.addEventListener("DOMContentLoaded", () => {

  if (typeof covidData === "undefined" || covidData.length === 0) {
    console.warn("Aucune donnée Covid disponible");
    return;
  }

  const ctx = document.getElementById("covidChart");
  if (!ctx) {
    console.error("Canvas covidChart introuvable");
    return;
  }

  const labels = covidData.map(d => d.date);
  const values = covidData.map(d => d.value);

  new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [{
        label: "Taux SRAS-CoV-2 (eaux usées – Maxéville)",
        data: values,
        borderColor: "rgb(220, 53, 69)",
        backgroundColor: "rgba(220, 53, 69, 0.1)",
        tension: 0.3,
        fill: true,
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: true
        },
        tooltip: {
          mode: "index",
          intersect: false
        }
      },
      scales: {
        x: {
          title: {
            display: true,
            text: "Date de mesure"
          }
        },
        y: {
          title: {
            display: true,
            text: "Concentration SRAS-CoV-2"
          },
          beginAtZero: true
        }
      }
    }
  });

});
