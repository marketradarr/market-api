<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// GET parametrelerini al
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
$size = isset($_GET['size']) ? (int)$_GET['size'] : 24;

// Zorunlu parametre kontrolü
if (empty($searchTerm)) {
    echo json_encode(['error' => 'Arama terimi gerekli']);
    exit;
}

// Search API'ye istek at
$searchUrl = 'https://api.marketfiyati.org.tr/api/v2/search';
$searchData = [
    'keywords' => $searchTerm,
    'pages' => $page,
    'size' => $size
];

$ch = curl_init($searchUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Content-Type: application/json',
        'Accept: application/json',
        'Accept-Encoding: gzip, deflate',
        'Origin: https://marketfiyati.org.tr',
        'Referer: https://marketfiyati.org.tr/',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Connection: keep-alive'
    ],
    CURLOPT_POSTFIELDS => json_encode($searchData)
]);

$searchResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Debug bilgisi ekle
$debug = [
    'url' => $searchUrl,
    'data' => $searchData,
    'httpCode' => $httpCode,
    'response' => $searchResponse
];

curl_close($ch);

// Search API hata kontrolü
if ($httpCode !== 200) {
    echo json_encode([
        'error' => 'Search API Hatası',
        'status' => $httpCode,
        'debug' => $debug
    ]);
    exit;
}

// API yanıtını kontrol et
$response = json_decode($searchResponse, true);
if (!$response || !isset($response['content'])) {
    echo json_encode([
        'error' => 'Geçersiz API yanıtı',
        'debug' => $debug
    ]);
    exit;
}

// Basit yanıt formatı
$formattedResponse = [
    'total' => $response['numberOfFound'],
    'products' => array_map(function($product) {
        $lowestPrice = PHP_FLOAT_MAX;
        $selectedStore = null;
        
        foreach ($product['productDepotInfoList'] as $store) {
            if ($store['price'] < $lowestPrice) {
                $lowestPrice = $store['price'];
                $selectedStore = $store;
            }
        }

        return [
            'id' => $product['id'],
            'name' => $product['title'],
            'brand' => $product['brand'],
            'image' => $product['imageUrl'],
            'price' => $lowestPrice,
            'market' => $selectedStore['marketAdi'],
            'store' => $selectedStore['depotName']
        ];
    }, $response['content'])
];

echo json_encode($formattedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
