<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$searchTerm = $_GET['search'] ?? '';

if (empty($searchTerm)) {
    echo json_encode(['error' => 'Arama terimi gerekli']);
    exit;
}

// URL'yi oluştur
$url = 'https://api.marketfiyati.org.tr/api/v2/search?keywords=' . urlencode($searchTerm);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,  // GET metodu kullan
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0',
        'Origin: https://marketfiyati.org.tr',
        'Referer: https://marketfiyati.org.tr/'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        'error' => 'API hatası', 
        'code' => $httpCode,
        'response' => $response
    ]);
    exit;
}

echo $response;
