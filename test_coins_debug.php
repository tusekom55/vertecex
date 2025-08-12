<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Coins Debug Test</h2>";

// Config dosyasını yükle
$config_paths = [
    'backend/config.php',
    __DIR__ . '/backend/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        echo "<p style='color: green;'>✅ Config yüklendi: $path</p>";
        break;
    }
}

if (!$config_loaded) {
    echo "<p style='color: red;'>❌ Config dosyası bulunamadı</p>";
    exit;
}

// Database bağlantısını test et
try {
    $conn = db_connect();
    echo "<p style='color: green;'>✅ Database bağlantısı başarılı</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database bağlantısı hatası: " . $e->getMessage() . "</p>";
    exit;
}

// Coins tablosunu kontrol et
echo "<h3>Coins Tablosu Analizi</h3>";

// Tablo var mı?
try {
    $check_table = $conn->query("SHOW TABLES LIKE 'coins'");
    if ($check_table->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Coins tablosu mevcut</p>";
    } else {
        echo "<p style='color: red;'>❌ Coins tablosu bulunamadı</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Tablo kontrolü hatası: " . $e->getMessage() . "</p>";
    exit;
}

// Tablo şemasını kontrol et
try {
    $schema = $conn->query("DESCRIBE coins");
    $columns = $schema->fetchAll(PDO::FETCH_ASSOC);
    echo "<h4>Tablo Şeması:</h4>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column['Field']}</strong> - {$column['Type']} " . 
             ($column['Null'] == 'NO' ? '(NOT NULL)' : '(NULL)') . 
             ($column['Default'] ? " DEFAULT: {$column['Default']}" : "") . "</li>";
    }
    echo "</ul>";
    
    // coin_type kolonu var mı?
    $has_coin_type = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'coin_type') {
            $has_coin_type = true;
            echo "<p style='color: green;'>✅ coin_type kolonu mevcut</p>";
            break;
        }
    }
    
    if (!$has_coin_type) {
        echo "<p style='color: red;'>❌ coin_type kolonu bulunamadı</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Şema kontrolü hatası: " . $e->getMessage() . "</p>";
}

// Mevcut coinleri göster
echo "<h3>Mevcut Coinler</h3>";
try {
    // Tüm coinleri getir
    $all_coins = $conn->query("SELECT * FROM coins ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h4>Tüm Coinler (Son 10):</h4>";
    if (empty($all_coins)) {
        echo "<p style='color: orange;'>⚠️ Hiç coin bulunamadı</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        foreach (array_keys($all_coins[0]) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";
        foreach ($all_coins as $coin) {
            echo "<tr>";
            foreach ($coin as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Manuel coinleri getir
    if ($has_coin_type) {
        $manual_coins = $conn->query("SELECT * FROM coins WHERE coin_type = 'manual' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h4>Manuel Coinler:</h4>";
        if (empty($manual_coins)) {
            echo "<p style='color: orange;'>⚠️ Hiç manuel coin bulunamadı</p>";
        } else {
            echo "<p style='color: green;'>✅ " . count($manual_coins) . " manuel coin bulundu</p>";
            echo "<ul>";
            foreach ($manual_coins as $coin) {
                echo "<li><strong>{$coin['coin_adi']}</strong> ({$coin['coin_kodu']}) - ₺{$coin['current_price']}</li>";
            }
            echo "</ul>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Coin listesi hatası: " . $e->getMessage() . "</p>";
}

// API çağrısını test et
echo "<h3>API Test</h3>";
try {
    // Simulated API call
    $_GET['action'] = 'list';
    
    // API çağrısını simüle et
    ob_start();
    include 'backend/admin/coins.php';
    $api_output = ob_get_clean();
    
    echo "<h4>API Yanıtı:</h4>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars($api_output);
    echo "</pre>";
    
    // JSON geçerli mi kontrol et
    $json_data = json_decode($api_output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p style='color: green;'>✅ Geçerli JSON yanıtı</p>";
        if (isset($json_data['success']) && $json_data['success']) {
            echo "<p style='color: green;'>✅ API başarılı</p>";
            if (isset($json_data['data'])) {
                echo "<p>💰 " . count($json_data['data']) . " coin döndürüldü</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ API hatası: " . ($json_data['error'] ?? 'Bilinmeyen hata') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Geçersiz JSON yanıtı</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ API test hatası: " . $e->getMessage() . "</p>";
}

echo "<h3>Çözüm Önerileri</h3>";
echo "<ul>";
echo "<li>Eğer coin_type kolonu yoksa: <code>ALTER TABLE coins ADD COLUMN coin_type VARCHAR(20) DEFAULT 'manual';</code></li>";
echo "<li>Mevcut coinleri manuel olarak işaretle: <code>UPDATE coins SET coin_type = 'manual' WHERE coin_type IS NULL;</code></li>";
echo "<li>Yeni coin eklerken coin_type = 'manual' olarak ayarlanması gerekiyor</li>";
echo "</ul>";
?>
