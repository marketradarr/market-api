<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$searchTerm = $_GET['search'] ?? '';

if (empty($searchTerm)) {
    echo json_encode(['error' => 'Arama terimi gerekli']);
    exit;
}

$ch = curl_init('https://api.marketfiyati.org.tr/api/v2/search');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode([
        'keywords' => $searchTerm,
        'pages' => 0,
        'size' => 24,
        'depots' => ['sok', 'a101', 'bim', 'migros']
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Origin: https://marketfiyati.org.tr',
        'Referer: https://marketfiyati.org.tr/'
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
