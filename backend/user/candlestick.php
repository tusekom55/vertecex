<?php
// Session yönetimi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Config dosyasını yükle
require_once __DIR__ . '/../config.php';

// Authentication kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $conn = db_connect();
    
    $symbol = $_GET['symbol'] ?? 'BTC';
    $timeframe = $_GET['timeframe'] ?? '1d';
    $limit = intval($_GET['limit'] ?? 100);
    
    // Base fiyatları
    $basePrices = [
        'BTC' => 1350000,
        'ETH' => 85000,
        'BNB' => 12500
    ];
    
    $basePrice = $basePrices[$symbol] ?? 1350000;
    
    // Timeframe'e göre interval
    $intervals = [
        '1m' => 60,
        '5m' => 300,
        '1h' => 3600,
        '1d' => 86400
    ];
    
    $interval = $intervals[$timeframe] ?? 86400;
    
    // Candlestick verisi oluştur
    $data = [];
    $now = time();
    $currentPrice = $basePrice;
    
    for ($i = $limit; $i >= 0; $i--) {
        $timestamp = $now - ($i * $interval);
        
        // Random fiyat hareketi
        $change = (mt_rand(-100, 100) / 10000); // ±1% değişim
        $newPrice = $currentPrice * (1 + $change);
        
        // OHLC hesapla
        $high = max($currentPrice, $newPrice) * (1 + mt_rand(0, 50) / 10000);
        $low = min($currentPrice, $newPrice) * (1 - mt_rand(0, 50) / 10000);
        $open = $currentPrice;
        $close = $newPrice;
        
        $data[] = [
            'time' => $timestamp,
            'open' => round($open, 2),
            'high' => round($high, 2),
            'low' => round($low, 2),
            'close' => round($close, 2),
            'volume' => mt_rand(1000, 10000)
        ];
        
        $currentPrice = $newPrice;
    }
    
    echo json_encode([
        'success' => true,
        'symbol' => $symbol,
        'timeframe' => $timeframe,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log('Candlestick API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?> 