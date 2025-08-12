<?php
// API Test Dosyası - Deposit sisteminin JSON response'larını test eder
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session yönetimi - çakışma önleme
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>🧪 Deposit API Test Sonuçları</h2>\n";

// Test 1: Config dosyası ve PDO bağlantısı
echo "<h3>1. Database Bağlantı Testi</h3>\n";

// Config dosyası path'ini esnek şekilde bulma
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    dirname(__DIR__) . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        echo "📁 Config dosyası bulundu: " . htmlspecialchars($path) . "<br>\n";
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    echo "❌ Config dosyası hiçbir yerde bulunamadı. Aranan yerler:<br>\n";
    foreach ($config_paths as $path) {
        echo "   - " . htmlspecialchars($path) . "<br>\n";
    }
    exit;
}

try {
    $conn = db_connect();
    echo "✅ PDO Bağlantısı başarılı<br>\n";
    echo "📊 Connection type: " . get_class($conn) . "<br>\n";
} catch (Exception $e) {
    echo "❌ Bağlantı hatası: " . $e->getMessage() . "<br>\n";
}

// Test 2: User deposits API test
echo "<h3>2. User Deposits API Testi</h3>\n";
$_GET['action'] = 'list';
$_SESSION['user_id'] = 1;

// Output buffering ile JSON response'u yakala
ob_start();
include 'user/deposits.php';
$user_response = ob_get_clean();

echo "🔍 User API Response:<br>\n";
echo "<pre>" . htmlspecialchars($user_response) . "</pre>\n";

// JSON geçerliliğini kontrol et
$user_json = json_decode($user_response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✅ User API JSON formatı geçerli<br>\n";
} else {
    echo "❌ User API JSON hatası: " . json_last_error_msg() . "<br>\n";
}

// Test 3: Admin deposits API test  
echo "<h3>3. Admin Deposits API Testi</h3>\n";
$_GET['action'] = 'list';

ob_start();
include 'admin/deposits.php';
$admin_response = ob_get_clean();

echo "🔍 Admin API Response:<br>\n";
echo "<pre>" . htmlspecialchars($admin_response) . "</pre>\n";

// JSON geçerliliğini kontrol et
$admin_json = json_decode($admin_response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✅ Admin API JSON formatı geçerli<br>\n";
} else {
    echo "❌ Admin API JSON hatası: " . json_last_error_msg() . "<br>\n";
}

// Test 4: Database tablo kontrolü
echo "<h3>4. Database Tablo Kontrolü</h3>\n";
try {
    $tables = ['para_yatirma_talepleri', 'users', 'kullanici_islem_gecmisi', 'admin_islem_loglari'];
    
    foreach ($tables as $table) {
        $sql = "SHOW TABLES LIKE '$table'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "✅ Tablo '$table' mevcut<br>\n";
            
            // Kolom kontrolleri
            if ($table === 'para_yatirma_talepleri') {
                $col_sql = "SHOW COLUMNS FROM $table LIKE 'onay_tarihi'";
                $col_stmt = $conn->prepare($col_sql);
                $col_stmt->execute();
                
                if ($col_stmt->rowCount() > 0) {
                    echo "✅ 'onay_tarihi' kolonu mevcut<br>\n";
                } else {
                    echo "⚠️ 'onay_tarihi' kolonu eksik - admin_schema.sql çalıştırın<br>\n";
                }
            }
        } else {
            echo "❌ Tablo '$table' bulunamadı<br>\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Tablo kontrolü hatası: " . $e->getMessage() . "<br>\n";
}

echo "<h3>✨ Test Tamamlandı</h3>\n";
echo "<p>Eğer JSON hataları devam ediyorsa, admin_schema.sql dosyasını database'de çalıştırın.</p>\n";
?>