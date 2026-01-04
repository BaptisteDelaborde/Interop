<?php
$context = stream_context_create([
  'http' => [
    'proxy' => 'tcp://www-cache:3128',
    'request_fulluri' => true
  ],
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false
  ]
]);

$url = "https://data.grandest.fr/api/records/1.0/search/?dataset=perturbations-circulation-grand-est&rows=5";

header('Content-Type: application/json; charset=utf-8');
echo file_get_contents($url, false, $context);
