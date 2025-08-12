<?php
// Hata raporlamayı kapat (JSON çıktısını bozmasın)
error_reporting(0);
ini_set('display_errors', 0);

// Output buffering başlat (beklenmeyen çıktıları yakala)
ob_start();

// Session yönetimi - çakışma önleme
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer'ı temizle ve header'ları ayarla
ob_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Config dosyası path'ini esnek şekilde bulma
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Config dosyası bulunamadı']));
}

try {
    $conn = db_connect();
    
    // Fiyat güncelleme sistemi entegrasyonu
    require_once __DIR__ . '/../utils/price_manager.php';
    $priceManager = new PriceManager();
    
    // Otomatik fiyat güncelleme (her 5 dakikada bir)
    $last_update_file = __DIR__ . '/../cache/last_price_update.txt';
    $should_update = false;
    
    if (!file_exists($last_update_file)) {
        $should_update = true;
    } else {
        $last_update = intval(file_get_contents($last_update_file));
        $current_time = time();
        if (($current_time - $last_update) > 300) { // 5 dakika = 300 saniye
            $should_update = true;
        }
    }
    
    if ($should_update) {
        $priceManager->updateAllPrices();
        if (!is_dir(__DIR__ . '/../cache')) {
            mkdir(__DIR__ . '/../cache', 0755, true);
        }
        file_put_contents($last_update_file, time());
    }
    
    // Tekli coin bilgisi isteniyor mu?
    $coin_id = $_GET['coin_id'] ?? null;
    
    if ($coin_id) {
        // Tekli coin bilgisi getir - yeni yapıya uygun
        $sql = 'SELECT 
                    coins.id, 
                    coins.coin_adi, 
                    coins.coin_kodu, 
                    coins.current_price, 
                    coins.price_change_24h, 
                    coins.coin_type,
                    coins.price_source,
                    coins.logo_url,
                    "Kripto Para" as kategori_adi
                FROM coins 
                WHERE coins.id = ? AND coins.is_active = 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$coin_id]);
        $coin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coin) {
            $coin['current_price'] = floatval($coin['current_price']);
            $coin['price_change_24h'] = floatval($coin['price_change_24h']);
            $coin['currency'] = 'TRY';
            
            // Logo URL'si veritabanından gelsin, yoksa placeholder
            if (empty($coin['logo_url'])) {
                $coin['logo_url'] = 'https://via.placeholder.com/64x64/4fc3f7/ffffff?text=' . substr($coin['coin_kodu'], 0, 2);
            }
            
            // Kategori adını coin tipine göre ayarla
            if ($coin['coin_type'] === 'manual') {
                $coin['kategori_adi'] = 'Özel Coinler';
            } else {
                $coin['kategori_adi'] = 'Kripto Para';
            }
            
            echo json_encode(['success' => true, 'coin' => $coin]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Coin bulunamadı']);
        }
        exit;
    }
    
    // Coins tablosunun var olup olmadığını kontrol et
    $table_check = $conn->prepare("SHOW TABLES LIKE 'coins'");
    $table_check->execute();
    
    if ($table_check->rowCount() == 0) {
        // Mock data döndür
        echo json_encode([
            'success' => true, 
            'coins' => [
                ['id' => 1, 'coin_adi' => 'Bitcoin', 'coin_kodu' => 'BTC', 'current_price' => 1350000, 'price_change_24h' => 2.5, 'kategori_adi' => 'Kripto Para'],
                ['id' => 2, 'coin_adi' => 'Ethereum', 'coin_kodu' => 'ETH', 'current_price' => 85000, 'price_change_24h' => -1.2, 'kategori_adi' => 'Kripto Para'],
                ['id' => 3, 'coin_adi' => 'BNB', 'coin_kodu' => 'BNB', 'current_price' => 12500, 'price_change_24h' => 0.8, 'kategori_adi' => 'Kripto Para']
            ]
        ]);
        exit;
    }
    
    // Arama parametresini kontrol et
    $search = $_GET['search'] ?? '';
    
    // Yeni veritabanı yapısına uygun sorgu - arama desteği ile
    // NOT: Piyasalar listesi için is_active kontrolü yapılır, ancak portföy için yapılmaz
    $sql = 'SELECT 
                coins.id, 
                coins.coin_adi, 
                coins.coin_kodu, 
                coins.current_price, 
                coins.price_change_24h, 
                coins.coin_type,
                coins.price_source,
                coins.logo_url,
                "Kripto Para" as kategori_adi
            FROM coins 
            WHERE coins.is_active = 1';
    
    $params = [];
    
    // Arama varsa WHERE koşuluna ekle
    if (!empty($search)) {
        $sql .= ' AND (coins.coin_adi LIKE ? OR coins.coin_kodu LIKE ?)';
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $sql .= ' ORDER BY 
                CASE 
                    WHEN coins.coin_type = "manual" THEN 1 
                    ELSE 2 
                END, 
                coins.coin_kodu ASC';
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fiyatları düzenle ve ek bilgiler ekle
    foreach ($coins as &$coin) {
        $coin['current_price'] = floatval($coin['current_price']);
        $coin['price_change_24h'] = floatval($coin['price_change_24h']);
        $coin['currency'] = 'TRY';
        
        // Logo URL'si veritabanından gelsin, yoksa placeholder
        if (empty($coin['logo_url'])) {
            $coin['logo_url'] = 'https://via.placeholder.com/64x64/4fc3f7/ffffff?text=' . substr($coin['coin_kodu'], 0, 2);
        }
        
        // Kategori adını coin tipine göre ayarla
        if ($coin['coin_type'] === 'manual') {
            $coin['kategori_adi'] = 'Özel Coinler';
        } else {
            $coin['kategori_adi'] = 'Kripto Para';
        }
    }
    
    echo json_encode(['success' => true, 'coins' => $coins]);
    
} catch (PDOException $e) {
    error_log('Database error in coins.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası']);
} catch (Exception $e) {
    error_log('General error in coins.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sistem hatası']);
}
