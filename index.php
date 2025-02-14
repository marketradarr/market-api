<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Market listesi
$marketList = [
    'a101' => 'A101',
    'bim' => 'BİM',
    'sok' => 'ŞOK',
    'migros' => 'Migros',
    'carrefour' => 'CarrefourSA',
    'hakmar' => 'Hakmar',
    'tarim_kredi' => 'Tarım Kredi',
    'metro' => 'Metro',
    'onur' => 'Onur Market',
    'happy' => 'Happy Center',
    'kim' => 'Kim Market',
    'macro' => 'Macro Center',
    'mopaş' => 'Mopaş',
    'altunbilekler' => 'Altunbilekler'
];

// Cache fonksiyonları
function getCache($key) {
    $cacheFile = "cache/{$key}.json";
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) { // 5 dakika
        return file_get_contents($cacheFile);
    }
    return false;
}

function setCache($key, $data) {
    if (!is_dir('cache')) {
        mkdir('cache');
    }
    $cacheFile = "cache/{$key}.json";
    file_put_contents($cacheFile, $data);
}

// GET parametrelerini al
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
$size = isset($_GET['size']) ? (int)$_GET['size'] : 24;
$sort = isset($_GET['sort']) ? $_GET['sort'] : ''; // price_asc, price_desc
$market = isset($_GET['market']) ? strtolower($_GET['market']) : ''; // market filtresi

// API Bilgileri
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET)) {
    $apiInfo = [
        'status' => 'success',
        'version' => '1.0',
        'endpoints' => [
            'search' => [
                'url' => '/?search={query}',
                'parameters' => [
                    'search' => 'Arama terimi (zorunlu)',
                    'page' => 'Sayfa numarası (varsayılan: 0)',
                    'size' => 'Sayfa başına ürün (varsayılan: 24)',
                    'sort' => 'Sıralama (price_asc, price_desc)',
                    'market' => 'Market filtresi (' . implode(', ', array_keys($marketList)) . ')'
                ],
                'example' => 'https://market-api-814938584526.us-central1.run.app/?search=ekmek&sort=price_asc&market=a101'
            ]
        ],
        'markets' => $marketList,
        'cache_time' => '5 minutes'
    ];
    echo json_encode($apiInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Zorunlu parametre kontrolü
if (empty($searchTerm)) {
    echo json_encode(['error' => 'Arama terimi gerekli']);
    exit;
}

// Market kontrolü
if (!empty($market) && !array_key_exists($market, $marketList)) {
    echo json_encode(['error' => 'Geçersiz market adı. Geçerli marketler: ' . implode(', ', array_keys($marketList))]);
    exit;
}

// Cache kontrolü
$cacheKey = md5($searchTerm . $page . $size . $sort . $market);
$cachedData = getCache($cacheKey);

if ($cachedData) {
    echo $cachedData;
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
curl_close($ch);

// API yanıtını kontrol et
$response = json_decode($searchResponse, true);
if (!$response || !isset($response['content'])) {
    echo json_encode([
        'error' => 'Geçersiz API yanıtı',
        'debug' => [
            'url' => $searchUrl,
            'data' => $searchData,
            'httpCode' => $httpCode,
            'response' => $searchResponse
        ]
    ]);
    exit;
}

// Ürünleri formatla
$products = array_map(function($product) {
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
        'store' => $selectedStore['depotName'],
        'location' => [
            'lat' => $selectedStore['latitude'],
            'lng' => $selectedStore['longitude']
        ],
        'lastUpdate' => $selectedStore['indexTime']
    ];
}, $response['content']);

// Market filtresi
if (!empty($market)) {
    $products = array_filter($products, function($product) use ($market) {
        return strtolower($product['market']) === $market;
    });
    $products = array_values($products); // Dizin numaralarını sıfırla
}

// Fiyat sıralaması
if ($sort === 'price_asc') {
    usort($products, function($a, $b) {
        return $a['price'] <=> $b['price'];
    });
} elseif ($sort === 'price_desc') {
    usort($products, function($a, $b) {
        return $b['price'] <=> $a['price'];
    });
}

$formattedResponse = [
    'status' => 'success',
    'total' => count($products),
    'page' => $page,
    'size' => $size,
    'filters' => [
        'markets' => array_map(function($market) use ($response) {
            return [
                'id' => strtolower($market['name']),
                'name' => $marketList[strtolower($market['name'])] ?? $market['name'],
                'count' => $market['count']
            ];
        }, $response['facetMap']['market_names'])
    ],
    'products' => $products
];

// Yanıtı cache'e kaydet
setCache($cacheKey, json_encode($formattedResponse));

echo json_encode($formattedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
