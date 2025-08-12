<?php
// Direkt satış API testi
echo "<h1>DİREKT SATIŞ API TESTİ</h1>";

// Test parametreleri
$coin_id = 4; // USDT
$miktar = 1.0;
$fiyat = 1.0;

echo "<h2>Test Parametreleri</h2>";
echo "<p>Coin ID: {$coin_id}</p>";
echo "<p>Miktar: {$miktar}</p>";
echo "<p>Fiyat: {$fiyat}</p>";

// API URL
$url = 'https://silver-eland-900684.hostingersite.com/backend/user/trading.php?action=sell';

// POST verisi
$postdata = http_build_query([
    'coin_id' => $coin_id,
    'miktar' => $miktar,
    'fiyat' => $fiyat
]);

// cURL kullanarak API'yi çağır
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<h2>API Yanıtı</h2>";
echo "<p>HTTP Kodu: {$http_code}</p>";

if ($curl_error) {
    echo "<p style='color:red;'>cURL Hatası: {$curl_error}</p>";
} else {
    echo "<h3>Ham Yanıt:</h3>";
    echo "<pre style='background:#f0f0f0; padding:10px; border:1px solid #ccc;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // JSON parse denemeleri
    $json_data = json_decode($response, true);
    
    if ($json_data !== null) {
        echo "<h3>✅ JSON Parse Başarılı:</h3>";
        echo "<pre>";
        print_r($json_data);
        echo "</pre>";
        
        if (isset($json_data['success'])) {
            if ($json_data['success']) {
                echo "<p style='color:green; font-size:18px;'>✅ SATIŞ BAŞARILI!</p>";
            } else {
                echo "<p style='color:red; font-size:18px;'>❌ SATIŞ BAŞARISIZ: " . ($json_data['message'] ?? 'Bilinmeyen hata') . "</p>";
            }
        }
    } else {
        echo "<h3>❌ JSON Parse Başarısız</h3>";
        echo "<p>JSON Hatası: " . json_last_error_msg() . "</p>";
        
        // Yanıtın ilk 500 karakterini kontrol et
        echo "<h4>Yanıtın İlk 500 Karakteri:</h4>";
        echo "<pre style='background:#ffe6e6; padding:10px; border:1px solid #ff0000;'>";
        echo htmlspecialchars(substr($response, 0, 500));
        echo "</pre>";
    }
}

echo "<h2>Alternatif Test</h2>";
echo "<p><a href='backend/user/trading.php?action=sell' target='_blank'>Direkt API Linkini Aç</a> (GET isteği)</p>";
?>
