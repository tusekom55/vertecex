<?php
// JSON parse hatasını debug etmek için test dosyası
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== PORTFOLIO JSON DEBUG TEST ===\n";
echo "Test Time: " . date('Y-m-d H:i:s') . "\n\n";

// Config dosyasını yükle
$config_paths = [
    __DIR__ . '/backend/config.php',
    __DIR__ . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        echo "✅ Config loaded from: $path\n";
        break;
    }
}

if (!$config_loaded) {
    die("❌ Config dosyası bulunamadı\n");
}

// Veritabanı bağlantısını test et
try {
    $conn = db_connect();
    echo "✅ Database connection successful\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Portfolio API'yi test et
echo "\n=== TESTING PORTFOLIO API ===\n";

// Session başlat
session_start();
$_SESSION['user_id'] = 1; // Test user ID

// API endpoint'i çağır
$api_url = 'http://localhost/turgis-main/backend/user/trading.php?action=portfolio';

echo "API URL: $api_url\n";

// cURL ile test et
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

if (curl_error($ch)) {
    echo "❌ cURL Error: " . curl_error($ch) . "\n";
} else {
    echo "✅ HTTP Code: $http_code\n";
    
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    echo "\n=== RESPONSE HEADERS ===\n";
    echo $headers . "\n";
    
    echo "\n=== RESPONSE BODY ===\n";
    echo "Body Length: " . strlen($body) . " characters\n";
    echo "First 200 chars: " . substr($body, 0, 200) . "\n";
    echo "Last 200 chars: " . substr($body, -200) . "\n";
    
    // JSON parse test
    echo "\n=== JSON PARSE TEST ===\n";
    $json_data = json_decode($body, true);
    $json_error = json_last_error();
    
    if ($json_error === JSON_ERROR_NONE) {
        echo "✅ JSON parse successful\n";
        echo "Response keys: " . implode(', ', array_keys($json_data)) . "\n";
    } else {
        echo "❌ JSON parse failed\n";
        echo "JSON Error Code: $json_error\n";
        echo "JSON Error Message: " . json_last_error_msg() . "\n";
        
        // Problematik karakterleri bul
        echo "\n=== PROBLEMATIC CHARACTERS ===\n";
        for ($i = 0; $i < min(strlen($body), 500); $i++) {
            $char = $body[$i];
            $ascii = ord($char);
            if ($ascii < 32 && $ascii != 9 && $ascii != 10 && $ascii != 13) {
                echo "Found control character at position $i: ASCII $ascii\n";
            }
        }
        
        // HTML tag kontrolü
        if (strpos($body, '<') !== false) {
            echo "⚠️ HTML content detected in response\n";
            preg_match_all('/<[^>]+>/', $body, $matches);
            echo "HTML tags found: " . implode(', ', array_unique($matches[0])) . "\n";
        }
    }
}

curl_close($ch);

// Direct PHP include test
echo "\n=== DIRECT PHP INCLUDE TEST ===\n";

// Output buffering ile test
ob_start();
$_GET['action'] = 'portfolio';
$_SESSION['user_id'] = 1;

try {
    include 'backend/user/trading.php';
    $direct_output = ob_get_contents();
    ob_end_clean();
    
    echo "Direct output length: " . strlen($direct_output) . "\n";
    echo "Direct output first 200 chars: " . substr($direct_output, 0, 200) . "\n";
    
    $direct_json = json_decode($direct_output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Direct PHP execution JSON parse successful\n";
    } else {
        echo "❌ Direct PHP execution JSON parse failed: " . json_last_error_msg() . "\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Direct PHP execution failed: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETED ===\n";
?>
