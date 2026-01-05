<?php
/**************************************************
 * atmosphere.php  (WEBETU)
 * Interopérabilité – DWM
 *
 * Objectifs :
 * - Géolocalisation IP CLIENT (XML)
 * - Fallback IUT Charlemagne si ≠ Nancy
 * - Météo (XML + XSL)
 * - Trafic (Open Data Grand Est + Leaflet)
 * - Covid (SRAS – eaux usées SUMEAU)
 * - Qualité de l’air (Atmo Grand Est)
 **************************************************/

/* =========================================
   1) Proxy WEBETU
   ========================================= */
$opts = [
  'http' => [
    'proxy' => 'tcp://www-cache:3128',
    'request_fulluri' => true,
    'header' => "User-Agent: Interop-DWM-Charlemagne\r\n"
  ],
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false
  ]
];
$context = stream_context_create($opts);

/* =========================================
   2) IP CLIENT
   ========================================= */
function getClientIp(): string {
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/* =========================================
   3) APIs
   ========================================= */
$API_GEOIP  = "http://ip-api.com/xml/";
$API_IUT    = "https://nominatim.openstreetmap.org/search?format=json&q=IUT+Nancy+Charlemagne&limit=1";

/* METEO */
$INFOCLIMAT_AUTH = "ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D";
$INFOCLIMAT_C = "19f3aa7d766b6ba91191c8be71dd1ab2";

/* TRAFIC */
$API_TRAFFIC = "https://services.data.gouv.fr/api/datasets/perturbations-circulation-grand-est/records";

/* COVID – SUMEAU */
$API_COVID = "https://tabular-api.data.gouv.fr/api/resources/2963ccb5-344d-4978-bdd3-08aaf9efe514/data/?commune__contains=Maxe";

/* AIR */
$API_AIR = "https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27Nancy%27&outFields=*&f=pjson";

/* =========================================
   4) Géolocalisation IP + fallback IUT
   ========================================= */
$clientIp = getClientIp();
$lat = $lon = null;
$ville = null;
$sourceLoc = "ip";

$geoRaw = @file_get_contents($API_GEOIP . $clientIp, false, $context);
if ($geoRaw) {
  $geoXml = @simplexml_load_string($geoRaw);
  if ($geoXml && (string)$geoXml->status === "success") {
    $lat = (float)$geoXml->lat;
    $lon = (float)$geoXml->lon;
    $ville = (string)$geoXml->city;
  }
}

if (!$lat || !$lon || strtolower($ville) !== "nancy") {
  $iutRaw = @file_get_contents($API_IUT, false, $context);
  if ($iutRaw) {
    $iut = json_decode($iutRaw, true);
    $lat = (float)$iut[0]['lat'];
    $lon = (float)$iut[0]['lon'];
    $ville = "Nancy";
    $sourceLoc = "iut";
  }
}

/* =========================================
   5) METEO (XML → XSL)
   ========================================= */
$meteoHtml = "<p>Météo indisponible.</p>";
$API_METEO = "https://www.infoclimat.fr/public-api/gfs/xml?_ll=$lat,$lon&_auth=$INFOCLIMAT_AUTH&_c=$INFOCLIMAT_C";

$meteoRaw = @file_get_contents($API_METEO, false, $context);
if ($meteoRaw) {
  $xml = new DOMDocument();
  if ($xml->loadXML($meteoRaw)) {
    $xsl = new DOMDocument();
    if ($xsl->load("meteo.xsl")) {
      $proc = new XSLTProcessor();
      $proc->importStylesheet($xsl);
      $proc->setParameter('', 'ville', $ville);
      $proc->setParameter('', 'sourceLoc', $sourceLoc);
      $meteoHtml = $proc->transformToXML($xml);
    }
  }
}

/* =========================================
   6) TRAFIC (laissé tel quel)
   ========================================= */
$traficHtml = "<p>Aucune donnée trafic.</p>";
$trafficRaw = @file_get_contents($API_TRAFFIC, false, $context);

$trafficXml = new SimpleXMLElement("<traffic/>");

if ($trafficRaw) {
  $json = json_decode($trafficRaw, true);
  foreach (($json['records'] ?? []) as $r) {
    $f = $r['fields'] ?? [];
    if (!isset($f['geo_point_2d'][0], $f['geo_point_2d'][1])) continue;

    $i = $trafficXml->addChild("incident");
    $i->addChild("lat", (string)$f['geo_point_2d'][0]);
    $i->addChild("lon", (string)$f['geo_point_2d'][1]);
    $i->addChild("type", htmlspecialchars($f['type'] ?? "Incident"));
    $i->addChild("debut", htmlspecialchars($f['date_debut'] ?? ""));
    $i->addChild("fin", htmlspecialchars($f['date_fin'] ?? ""));
  }
}

$xmlTraffic = new DOMDocument();
if (@$xmlTraffic->loadXML($trafficXml->asXML())) {
  $xslTraffic = new DOMDocument();
  if (@$xslTraffic->load("trafic.xsl")) {
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xslTraffic);
    $tmp = $proc->transformToXML($xmlTraffic);
    if ($tmp) $traficHtml = $tmp;
  }
}


/* =========================================
   7) COVID – SRAS
   ========================================= */
$covidData = [];
$covidRaw = @file_get_contents($API_COVID, false, $context);
if ($covidRaw) {
  $json = json_decode($covidRaw, true);
  foreach ($json['data'] ?? [] as $row) {
    if (!empty($row['date'])) {
      $covidData[] = [
        'date' => $row['date'],
        'value' => (float)($row['taux_sars_cov_2'] ?? 0)
      ];
    }
  }
}

/* =========================================
   8) QUALITÉ AIR
   ========================================= */
$airLabel = "Donnée indisponible";
$airRaw = @file_get_contents($API_AIR, false, $context);
if ($airRaw) {
  $air = json_decode($airRaw, true);
  $airLabel = $air['features'][0]['attributes']['lib_qual'] ?? $airLabel;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Atmosphère – Interopérabilité</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div class="page-container">
  <header class="page-header">
    <h1>Atmosphère – Interopérabilité</h1>

    <p class="location">
      <strong>Localisation :</strong> <?= $ville ?> (<?= $sourceLoc ?>)
    </p>

    <div class="resources">
      <h3>Ressources utilisées</h3>
      <ul>
        <li><strong>Géolocalisation IP :</strong> <?= $API_GEOIP ?></li>
        <li><strong>Météo :</strong> https://www.infoclimat.fr/public-api/gfs/xml</li>
        <li><strong>Trafic :</strong> <?= $API_TRAFFIC ?></li>
        <li><strong>Covid (SUMEAU) :</strong> <?= $API_COVID ?></li>
        <li><strong>Qualité de l’air :</strong> <?= $API_AIR ?></li>
      </ul>

      <p class="git-link">
        Git :
        <a href="https://github.com/BaptisteDelaborde/Interop" target="_blank">
          https://github.com/BaptisteDelaborde/Interop
        </a>
      </p>
    </div>
  </header>
  <section class="section meteo">
    <?= $meteoHtml ?>
  </section>

  <section class="section trafic">
    <?= $traficHtml ?>
  </section>

  <section class="section covid">
    <h2>État de la pandémie (SRAS – eaux usées)</h2>
    <div class="chart-container">
      <canvas id="covidChart"></canvas>
    </div>
  </section>

  <section class="section air">
    <h2>Qualité de l’air du jour</h2>
    <p class="air-quality">
      <strong><?= htmlspecialchars($airLabel) ?></strong> – Source : Atmo Grand Est
    </p>
  </section>

</div>
<script>
const covidData = <?= json_encode($covidData) ?>;

new Chart(document.getElementById('covidChart'), {
  type: 'line',
  data: {
    labels: covidData.map(d => d.date),
    datasets: [{
      label: 'Taux SRAS-CoV-2 (Maxéville)',
      data: covidData.map(d => d.value),
      borderColor: '#e74c3c',
      tension: 0.3
    }]
  }
});
</script>

<script src="map.js" defer></script>

</body>
</html>
