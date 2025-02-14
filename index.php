<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// GET parametrelerini al
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
$size = isset($_GET['size']) ? (int)$_GET['size'] : 24;
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'price_asc'; // price_asc, price_desc, distance
$market = isset($_GET['market']) ? $_GET['market'] : ''; // a101, bim, sok, migros
$userLat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$userLng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

// API'den veriyi al
// ... mevcut kod ...

// Yanıtı Android için düzenle
$formattedResponse = [
    'status' => 'success',
    'total' => $response['numberOfFound'],
    'page' => $page,
    'size' => $size,
    'filters' => [
        'markets' => array_map(function($market) {
            return [
                'id' => $market['name'],
                'name' => ucfirst($market['name']),
                'count' => $market['count']
            ];
        }, $response['facetMap']['market_names']),
        'brands' => array_map(function($brand) {
            return [
                'name' => $brand['name'],
                'count' => $brand['count']
            ];
        }, $response['facetMap']['brand']),
        'weights' => array_map(function($weight) {
            return [
                'value' => $weight['name'],
                'count' => $weight['count']
            ];
        }, $response['facetMap']['refined_volume_weight']),
        'categories' => array_map(function($cat) {
            return [
                'name' => $cat['name'],
                'count' => $cat['count']
            ];
        }, $response['facetMap']['main_category'])
    ],
    'products' => []
];

foreach ($response['content'] as $product) {
    // Ağırlık/Hacim bilgisini çıkar
    preg_match('/(\d+(?:\.\d+)?)\s*(gr|kg|ml|lt)/', $product['title'], $matches);
    $weight = [
        'value' => $matches[1] ?? null,
        'unit' => $matches[2] ?? null
    ];

    // Her ürün için en ucuz fiyatı ve diğer mağazaları bul
    $lowestPrice = PHP_FLOAT_MAX;
    $selectedStore = null;
    $otherStores = [];
    
    foreach ($product['productDepotInfoList'] as $store) {
        $storeInfo = [
            'market' => [
                'id' => $store['marketAdi'],
                'name' => ucfirst($store['marketAdi']),
                'logo' => getMarketLogo($store['marketAdi'])
            ],
            'store' => [
                'id' => $store['depotId'],
                'name' => $store['depotName'],
                'location' => [
                    'lat' => $store['latitude'],
                    'lng' => $store['longitude']
                ]
            ],
            'price' => $store['price'],
            'distance' => $userLat && $userLng ? 
                calculateDistance($userLat, $userLng, $store['latitude'], $store['longitude']) : null,
            'lastUpdate' => $store['indexTime']
        ];

        if ($store['price'] < $lowestPrice) {
            $lowestPrice = $store['price'];
            $selectedStore = $storeInfo;
        }
        $otherStores[] = $storeInfo;
    }

    // Ürün bilgilerini düzenle
    $formattedProduct = [
        'id' => $product['id'],
        'name' => $product['title'],
        'brand' => [
            'name' => $product['brand'],
            'logo' => getBrandLogo($product['brand'])
        ],
        'image' => $product['imageUrl'],
        'weight' => $weight,
        'categories' => $product['categories'],
        'price' => [
            'current' => $lowestPrice,
            'currency' => 'TL'
        ],
        'bestOffer' => $selectedStore,
        'allStores' => $otherStores
    ];

    $formattedResponse['products'][] = $formattedProduct;
}

// Yardımcı fonksiyonlar
function getMarketLogo($marketId) {
    $logos = [
        'a101' => 'https://market-api-814938584526.us-central1.run.app/assets/logos/a101.png',
        'bim' => 'https://market-api-814938584526.us-central1.run.app/assets/logos/bim.png',
        'sok' => 'https://market-api-814938584526.us-central1.run.app/assets/logos/sok.png',
        'migros' => 'https://market-api-814938584526.us-central1.run.app/assets/logos/migros.png',
        // diğer marketler...
    ];
    return $logos[strtolower($marketId)] ?? null;
}

function getBrandLogo($brandName) {
    // Marka logolarını döndür
    return null; // şimdilik null
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    // Haversine formülü ile mesafe hesapla
    $earthRadius = 6371; // km

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return round($earthRadius * $c, 2);
}

// Sıralama
if ($sortBy == 'price_asc') {
    usort($formattedResponse['products'], function($a, $b) {
        return $a['price']['current'] <=> $b['price']['current'];
    });
} else if ($sortBy == 'price_desc') {
    usort($formattedResponse['products'], function($a, $b) {
        return $b['price']['current'] <=> $a['price']['current'];
    });
} else if ($sortBy == 'distance' && $userLat && $userLng) {
    usort($formattedResponse['products'], function($a, $b) {
        return $a['bestOffer']['distance'] <=> $b['bestOffer']['distance'];
    });
}

// Market filtresi
if (!empty($market)) {
    $formattedResponse['products'] = array_filter($formattedResponse['products'], function($product) use ($market) {
        return strtolower($product['bestOffer']['market']['id']) == strtolower($market);
    });
}

echo json_encode($formattedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
