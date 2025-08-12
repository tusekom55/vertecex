<?php
// Basit bağlantı testi
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Hostinger Bağlantı Testi</h1>";

// PHP Sürümü
echo "<h2>PHP Sürümü:</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// MySQL/MariaDB Sürümü
echo "<h2>MySQL Sürümü:</h2>";
try {
    $test_conn = new mysqli('localhost', 'u225998063_yenip', '123456Tubb', 'u225998063_yenip');
    
    if ($test_conn->connect_error) {
        echo "❌ MySQL Bağlantı Hatası: " . $test_conn->connect_error . "<br>";
    } else {
        echo "✅ MySQL Bağlantısı Başarılı<br>";
        echo "MySQL Version: " . $test_conn->server_info . "<br>";
        
        // Tablo kontrolü
        $tables = ['users', 'leverage_positions', 'coins'];
        foreach ($tables as $table) {
            $result = $test_conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "✅ Tablo '$table' mevcut<br>";
            } else {
                echo "❌ Tablo '$table' bulunamadı<br>";
            }
        }
        
        $test_conn->close();
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Session Test
echo "<h2>Session Test:</h2>";
session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session aktif<br>";
} else {
    echo "❌ Session aktif değil<br>";
}

// File Permissions
echo "<h2>Dosya İzinleri:</h2>";
$files = ['config.php', 'user/leverage_trading.php', 'test_positions.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file mevcut<br>";
        if (is_readable($file)) {
            echo "✅ $file okunabilir<br>";
        } else {
            echo "❌ $file okunamıyor<br>";
        }
    } else {
        echo "❌ $file bulunamadı<br>";
    }
}

// Error Log
echo "<h2>Error Log:</h2>";
$error_log = error_get_last();
if ($error_log) {
    echo "Son Hata: " . $error_log['message'] . "<br>";
} else {
    echo "Hata yok<br>";
}

echo "<h2>Test Tamamlandı</h2>";
?> 