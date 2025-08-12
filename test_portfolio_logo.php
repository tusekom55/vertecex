<?php
/**
 * Portföy Logo Sorunu Test Dosyası
 * 
 * Bu test, portföyde coin logolarının görünüp görünmediğini kontrol eder.
 */

require_once 'backend/config.php';

echo "<h1>🖼️ Portföy Logo Test</h1>";
echo "<p>Bu test, portföyde coin logolarının düzgün görünüp görünmediğini kontrol eder.</p>";

try {
    $conn = db_connect();
    
    // Test kullanıcısı ID'si (varsayılan olarak 1)
    $test_user_id = 1;
    
    echo "<h2>📊 Test Adımları</h2>";
    
    // 1. Coins tablosunda logo_url sütunu var mı?
    echo "<h3>1. Coins Tablosu Yapısı</h3>";
    
    $columns_sql = "SHOW COLUMNS FROM coins";
    $stmt = $conn->prepare($columns_sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_logo_url = false;
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Sütun Adı</th><th>Tip</th><th>Null</th><th>Varsayılan</th></tr>";
    
    foreach ($columns as $column) {
        if ($column['Field'] == 'logo_url') {
            $has_logo_url = true;
        }
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($has_logo_url) {
        echo "<p style='color: green;'>✅ logo_url sütunu mevcut</p>";
    } else {
        echo "<p style='color: red;'>❌ logo_url sütunu eksik!</p>";
        echo "<p>Logo sütununu eklemek için fix_logo_column.php dosyasını çalıştırın.</p>";
    }
    
    // 2. Coin'lerin logo durumunu kontrol et
    echo "<h3>2. Coin Logo Durumu</h3>";
    
    $coins_sql = "SELECT id, coin_adi, coin_kodu, logo_url, is_active FROM coins ORDER BY coin_adi";
    $stmt = $conn->prepare($coins_sql);
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $with_logo = 0;
    $without_logo = 0;
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Coin</th><th>Kod</th><th>Logo URL</th><th>Logo Durumu</th><th>Aktif</th></tr>";
    
    foreach ($coins as $coin) {
        $logo_status = '';
        $logo_color = '';
        
        if (empty($coin['logo_url'])) {
            $logo_status = '❌ Logo Yok';
            $logo_color = 'red';
            $without_logo++;
        } else {
            $logo_status = '✅ Logo Var';
            $logo_color = 'green';
            $with_logo++;
        }
        
        $active_status = $coin['is_active'] ? '✅' : '❌';
        $active_color = $coin['is_active'] ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>{$coin['id']}</td>";
        echo "<td>{$coin['coin_adi']}</td>";
        echo "<td><strong>{$coin['coin_kodu']}</strong></td>";
        echo "<td style='max-width: 200px; word-break: break-all;'>" . ($coin['logo_url'] ?: '-') . "</td>";
        echo "<td style='color: {$logo_color};'>{$logo_status}</td>";
        echo "<td style='color: {$active_color};'>{$active_status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Logo İstatistikleri:</strong></p>";
    echo "<ul>";
    echo "<li>Logo olan coin sayısı: <span style='color: green;'>{$with_logo}</span></li>";
    echo "<li>Logo olmayan coin sayısı: <span style='color: red;'>{$without_logo}</span></li>";
    echo "<li>Toplam coin sayısı: " . count($coins) . "</li>";
    echo "</ul>";
    
    // 3. Portföy API'sinden logo verilerini test et
    echo "<h3>3. Portföy API Logo Testi</h3>";
    
    // Portfolio API'sini çağır
    $api_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/backend/user/trading.php?action=portfolio';
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "Content-Type: application/json\r\n"
        ]
    ]);
    
    $api_response = file_get_contents($api_url, false, $context);
    
    if ($api_response === false) {
        echo "<p style='color: red;'>❌ API çağrısı başarısız</p>";
    } else {
        $api_data = json_decode($api_response, true);
        
        if ($api_data && isset($api_data['success']) && $api_data['success']) {
            $portfolio = $api_data['data']['portfolio'] ?? [];
            echo "<p style='color: green;'>✅ API başarılı - " . count($portfolio) . " coin döndürüldü</p>";
            
            if (!empty($portfolio)) {
                echo "<h4>Portföy Logo Durumu:</h4>";
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Coin</th><th>Kod</th><th>Logo URL</th><th>Logo Durumu</th></tr>";
                
                foreach ($portfolio as $item) {
                    $logo_status = '';
                    $logo_color = '';
                    
                    if (empty($item['logo_url'])) {
                        $logo_status = '❌ Logo Yok';
                        $logo_color = 'red';
                    } else {
                        $logo_status = '✅ Logo Var';
                        $logo_color = 'green';
                    }
                    
                    echo "<tr>";
                    echo "<td>{$item['coin_adi']}</td>";
                    echo "<td><strong>{$item['coin_kodu']}</strong></td>";
                    echo "<td style='max-width: 200px; word-break: break-all;'>" . ($item['logo_url'] ?: '-') . "</td>";
                    echo "<td style='color: {$logo_color};'>{$logo_status}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p style='color: orange;'>⚠️ Portföyde coin bulunamadı</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ API hatası: " . ($api_data['message'] ?? 'Bilinmeyen hata') . "</p>";
            echo "<pre>" . htmlspecialchars($api_response) . "</pre>";
        }
    }
    
    // 4. Sonuç ve öneriler
    echo "<h3>4. Test Sonucu ve Öneriler</h3>";
    
    if (!$has_logo_url) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "<h4>❌ Kritik Sorun</h4>";
        echo "<p>Coins tablosunda logo_url sütunu eksik. Bu sütunu eklemek için:</p>";
        echo "<ol>";
        echo "<li><code>fix_logo_column.php</code> dosyasını çalıştırın</li>";
        echo "<li>Veya manuel olarak: <code>ALTER TABLE coins ADD COLUMN logo_url VARCHAR(500) DEFAULT NULL;</code></li>";
        echo "</ol>";
        echo "</div>";
    } elseif ($without_logo > 0) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px;'>";
        echo "<h4>⚠️ Logo Eksiklikleri</h4>";
        echo "<p>{$without_logo} coin'in logosu eksik. Logo eklemek için:</p>";
        echo "<ol>";
        echo "<li><code>fix_coin_logos.php</code> dosyasını çalıştırın</li>";
        echo "<li>Veya admin panelinden coin'leri düzenleyin</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
        echo "<h4>✅ Logo Sistemi Sağlıklı</h4>";
        echo "<p>Tüm coin'lerin logoları mevcut ve API'den düzgün şekilde döndürülüyor.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Test hatası: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Test tamamlandı:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><a href='user-panel.html'>← Kullanıcı Paneline Dön</a></p>";
?>
