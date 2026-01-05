<?php

/* proxy pour webetu */

$host = $_SERVER['HTTP_HOST'] ?? '';
$isWebetu = (strpos($host, 'webetu') !== false);

$opts = [
    'http' => [
        'timeout' => 10,
        'header' => "User-Agent: ProjetInterop/1.0\r\n"
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];

if ($isWebetu) {
    $opts['http']['proxy'] = 'tcp://www-cache:3128';
    $opts['http']['request_fulluri'] = true;
}

$context = stream_context_create($opts);

/* ip client */

function getClientIp(): string {
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/* toutes nos API */

$API_GEOIP  = "http://ip-api.com/xml/";
$API_IUT    = "https://nominatim.openstreetmap.org/search?format=json&q=IUT+Nancy+Charlemagne&limit=1";

/* météo */
$INFOCLIMAT_AUTH = "ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D";
$INFOCLIMAT_C = "19f3aa7d766b6ba91191c8be71dd1ab2";

/* trafic */
$API_TRAFFIC = "https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json";

/* Covid  */
$API_COVID = "https://odisse.santepubliquefrance.fr/api/explore/v2.1/catalog/datasets/sum-eau-indicateurs/records?where=commune%3D%22NANCY%22&limit=50";

/* Air */
$API_AIR = "https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27Nancy%27&outFields=*&f=pjson";

/* Géolocalisation IP ou IUT */

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

/* Météo */

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

/* Trafic */
$traficHtml = "<p>Aucune donnée trafic.</p>";

$trafficRaw = @file_get_contents($API_TRAFFIC, false, $context);

$trafficXml = new SimpleXMLElement("<traffic/>");
$trafficData = [];

if ($trafficRaw) {
    $json = json_decode($trafficRaw, true);

    foreach (($json['incidents'] ?? []) as $inc) {
        if (isset($inc['location']['polyline'])) {
            $coords = explode(' ', $inc['location']['polyline']);
            if (count($coords) >= 2) {
                $lat = $coords[0];
                $lon = $coords[1];

                $type = htmlspecialchars($inc['type'] ?? 'INCIDENT');

                $desc = htmlspecialchars($inc['short_description'] ?? $inc['description'] ?? '');
                $debut = htmlspecialchars($inc['starttime'] ?? '');
                $fin = htmlspecialchars($inc['endtime'] ?? '');

                $node = $trafficXml->addChild("incident");
                $node->addChild("lat", $lat);
                $node->addChild("lon", $lon);
                $node->addChild("type", $type);
                $node->addChild("description", $desc);
                $node->addChild("debut", $debut);
                $node->addChild("fin", $fin);

                $trafficData[] = [
                    'lat' => (float)$lat,
                    'lon' => (float)$lon,
                    'type' => $type,
                    'desc' => $desc,
                    'debut' => $debut,
                    'fin' => $fin
                ];
            }
        }
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

/* Covid */

$covidData = [];
$covidRaw = @file_get_contents($API_COVID, false, $context);

if ($covidRaw) {
    $json = json_decode($covidRaw, true);
    $rows = $json['results'] ?? [];

    usort($rows, function($a, $b) {
        return strtotime($a['date_complet']) - strtotime($b['date_complet']);
    });

    foreach ($rows as $row) {
        if (!empty($row['date_complet'])) {
            $covidData[] = [
                'date' => $row['date_complet'],
                'value' => (float)($row['mesure'] ?? 0)
            ];
        }
    }
}
/* Qualité de l'air */

$airLabel = "Donnée indisponible";
$airColor = "#333333";

$airRaw = @file_get_contents($API_AIR, false, $context);

if ($airRaw !== false) {
    $airData = json_decode($airRaw, true);

    if (isset($airData['features']) && count($airData['features']) > 0) {

        usort($airData['features'], function($a, $b) {
            return $a['attributes']['date_ech'] <=> $b['attributes']['date_ech'];
        });

        $forecast = null;
        $todayYmd = date('Y-m-d');

        foreach ($airData['features'] as $feature) {
            $timestamp = $feature['attributes']['date_ech'] / 1000;
            $dateFeature = date('Y-m-d', $timestamp);

            if ($dateFeature === $todayYmd) {
                $forecast = $feature;
                break;
            }
        }

        if (!$forecast) {
            $forecast = end($airData['features']);
        }

        if ($forecast) {
            $airLabel = $forecast['attributes']['lib_qual'];
            $airColor = $forecast['attributes']['coul_qual'];
        }
    }
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
        <li><strong>Covid :</strong> <?= $API_COVID ?></li>
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
        <p class="air-quality" style="color: <?= htmlspecialchars($airColor) ?>;">
            <span style="font-size: 2rem;">🍃</span><br>
            <strong><?= htmlspecialchars($airLabel) ?></strong>
        </p>
        <p style="font-size: 0.9em; color: #666;">Source : Atmo Grand Est</p>
    </section>

</div>
<script>
const covidData = <?= json_encode($covidData) ?>;
window.trafficData = <?= json_encode($trafficData) ?>;
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