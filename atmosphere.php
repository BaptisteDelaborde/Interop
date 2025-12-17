<?php
// API géolocalisation IP (XML)
$API_GEOIP = "http://ip-api.com/xml/";

// API géocodage IUT (JSON)
$API_IUT = "https://nominatim.openstreetmap.org/search?format=json&q=IUT+Nancy+Charlemagne&limit=1";

// API météo Infoclimat (XML)
// ⚠️ Clé API non disponible pour le moment (maintenance Infoclimat)
$INFOCLIMAT_AUTH = "CLE_INFOCLIMAT_NON_DISPONIBLE";
$INFOCLIMAT_C    = "SIGNATURE_NON_DISPONIBLE";


$lat   = null;
$lon   = null;
$ville = null;

// récupère ip client
function getClientIp(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// récupère coordonnées de l'iut via une api
function getIutCoordinates(string $apiUrl): array {
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: InteropDWM/1.0\r\n"
        ]
    ]);

    $json = @file_get_contents($apiUrl, false, $context);
    if ($json === false) {
        return [null, null];
    }

    $data = json_decode($json, true);

    if (is_array($data) && isset($data[0]['lat'], $data[0]['lon'])) {
        return [(float)$data[0]['lat'], (float)$data[0]['lon']];
    }

    return [null, null];
}


/*****************************
 * GÉOLOCALISATION
 *****************************/

// récupération IP client
$clientIp = getClientIp();

// géolocalisation ip client (uniquement si ce n'est pas en local)
if ($clientIp !== "127.0.0.1" && $clientIp !== "::1") {

    $geoXml = @simplexml_load_file($API_GEOIP . $clientIp);

    if ($geoXml && (string)$geoXml->status === "success") {
        $lat   = (float)$geoXml->lat;
        $lon   = (float)$geoXml->lon;
        $ville = (string)$geoXml->city;
    }
}

// coordonnées de l'iut si la géolocalisation ip échoue ou si on est en local
$source = "ip";

if ($lat === null || $lon === null) {
    [$lat, $lon] = getIutCoordinates($API_IUT);
    $source = "iut";
    $ville  = "IUT Nancy-Charlemagne";
}

if ($lat === null || $lon === null) {
    echo "<p>Impossible de déterminer une localisation.</p>";
}


/*****************************
 * MÉTÉO (INFCLIMAT)
 *****************************/

// construction url météo
$meteoUrl = "https://www.infoclimat.fr/public-api/gfs/xml"
          . "?_ll=$lat,$lon"
          . "&_auth=$INFOCLIMAT_AUTH"
          . "&_c=$INFOCLIMAT_C";

// récupération xml météo
$meteoXml = @simplexml_load_file($meteoUrl);

if ($meteoXml === false) {
    echo "<p>Données météo indisponibles</p>";
} else {
    // chargement du xml météo
    $xml = new DOMDocument();
    $xml->loadXML($meteoXml->asXML());

    // chargement de la feuille xsl
    $xsl = new DOMDocument();
    $xsl->load("meteo.xsl");

    // transformation xsl
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);

    $meteoHtml = $proc->transformToXML($xml);

    // affichage du fragment html généré
    echo "<h2>Météo</h2>";
    echo $meteoHtml;
}
