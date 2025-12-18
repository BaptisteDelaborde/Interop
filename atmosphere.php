<?php
/**************************************************
 * Interop - atmosphere.php (webetu)
 * - Géolocalisation IP (XML)
 * - Fallback IUT (JSON)
 * - Météo Infoclimat (XML) + XSLT
 **************************************************/

/*****************************
 * API
 *****************************/

// géolocalisation IP (XML)
$API_GEOIP = "http://ip-api.com/xml/";

// géocodage IUT (JSON)
$API_IUT = "https://nominatim.openstreetmap.org/search?format=json&q=IUT+Nancy+Charlemagne&limit=1";

// météo Infoclimat (XML) - URL fournie par l'enseignant
$API_METEO = "https://www.infoclimat.fr/public-api/gfs/xml?_ll=48.67103,6.15083"
    . "&_auth=ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D"
    . "&_c=19f3aa7d766b6ba91191c8be71dd1ab2";


/*****************************
 * PROXY WEBETU
 *****************************/

$context = stream_context_create([
    'http' => [
        'proxy' => 'tcp://www-cache:3128',
        'request_fulluri' => true,
        'timeout' => 10
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);


/*****************************
 * FONCTIONS
 *****************************/

// récupère ip client
function getClientIp(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}


/*****************************
 * GÉOLOCALISATION
 *****************************/

$clientIp = getClientIp();

$lat = null;
$lon = null;
$ville = null;
$sourceLoc = "ip";

// géolocalisation IP (XML)
$geoContent = @file_get_contents($API_GEOIP . $clientIp, false, $context);
if ($geoContent !== false) {
    $geoXml = @simplexml_load_string($geoContent);
    if ($geoXml && (string)$geoXml->status === "success") {
        $lat = (float)$geoXml->lat;
        $lon = (float)$geoXml->lon;
        $ville = (string)$geoXml->city;
    }
}

// fallback IUT (JSON)
if ($lat === null || $lon === null) {
    $sourceLoc = "iut";
    $json = @file_get_contents($API_IUT, false, $context);
    if ($json !== false) {
        $data = json_decode($json, true);
        if (is_array($data) && isset($data[0]['lat'], $data[0]['lon'])) {
            $lat = (float)$data[0]['lat'];
            $lon = (float)$data[0]['lon'];
            $ville = "IUT Nancy-Charlemagne";
        }
    }
}

// dernier fallback (ne doit pas planter la page)
if ($lat === null || $lon === null) {
    $sourceLoc = "fallback";
    $lat = 48.6921;
    $lon = 6.1844;
    $ville = "Nancy";
}


/*****************************
 * MÉTÉO (INFCLIMAT)
 *****************************/

$meteoContent = @file_get_contents($API_METEO, false, $context);
if ($meteoContent === false) {
    echo "<p>Données météo indisponibles</p>";
    exit;
}


/*****************************
 * XSLT
 *****************************/

$xml = new DOMDocument();
$xml->loadXML($meteoContent);

$xsl = new DOMDocument();
$xsl->load("meteo.xsl");

$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);

// petit contexte utile (optionnel mais propre)
$proc->setParameter('', 'ville', $ville);
$proc->setParameter('', 'sourceLoc', $sourceLoc);
$proc->setParameter('', 'lat', (string)$lat);
$proc->setParameter('', 'lon', (string)$lon);

// affichage du fragment HTML
echo $proc->transformToXML($xml);
