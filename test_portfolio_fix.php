<?php
/**
 * Portföy Görünürlük Sorunu Test Dosyası
 * 
 * Bu test, fiyat güncellemesi sonrası coin'lerin portföyde kaybolma sorununu test eder.
 */

require_once 'backend/config.php';

echo "<h1>🔧 Portföy Görünürlük Sorunu Test</h1>";
echo "<p>Bu test, admin panelinde fiyat güncellemesi sonrası coin'lerin portföyde kaybolma sorununu kontrol eder.</p>";

try {
    $conn = db_connect();
    
    // Test kullanıcısı ID'si (varsayılan olarak 1)
    $test_user_id = 1;
    
    echo "<h2>📊 Test Adımları</h2>";
    
    // 1. Mevcut portföyü kontrol et
    echo "<h3>1. Mevcut Portföy Durumu</h3>";
    
    $portfolio_check = "SELECT 
                            ci.coin_id,
                            c.coin_adi,
                            c.coin_kodu,
                            c.is_active,
                            c.current_price,
                            SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar
                        FROM coin_islemleri ci
                        JOIN coins c ON ci.coin_id = c.id
                        WHERE ci.user_id = ?
                        GROUP BY ci.coin_id
                        HAVING SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) > 0";
    
    $stmt = $conn->prepare($portfolio_check);
    $stmt->execute([$test_user_id]);
    $current_portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($current_portfolio)) {
        echo "<p style='color: orange;'>⚠️ Test kullanıcısının portföyünde coin bulunamadı.</p>";
        echo "<p>Test için önce bir coin satın alın.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Coin</th><th>Kod</th><th>Net Miktar</th><th>Aktif Durum</th><th>Güncel Fiyat</th></tr>";
        
        foreach ($current_portfolio as $item) {
            $active_status = $item['is_active'] ? '✅ Aktif' : '❌ Pasif';
            $active_color = $item['is_active'] ? 'green' : 'red';
            
            echo "<tr>";
            echo "<td>{$item['coin_adi']}</td>";
            echo "<td><strong>{$item['coin_kodu']}</strong></td>";
            echo "<td>" . number_format($item['net_miktar'], 8) . "</td>";
            echo "<td style='color: {$active_color};'>{$active_status}</td>";
            echo "<td>₺" . number_format($item['current_price'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Eski portföy API'sini test et (is_active kontrolü ile)
    echo "<h3>2. Eski Portföy API Testi (is_active kontrolü ile)</h3>";
    
    $old_portfolio_sql = "SELECT 
                            p.*,
                            c.coin_adi,
                            c.coin_kodu,
                            c.current_price,
                            c.is_active
                          FROM (
                            SELECT 
                                ci.coin_id,
                                SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar
                            FROM coin_islemleri ci
                            WHERE ci.user_id = ?
                            GROUP BY ci.coin_id
                            HAVING SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) > 0.00000001
                          ) p
                          JOIN coins c ON p.coin_id = c.id
                          WHERE c.is_active = 1";
    
    $stmt = $conn->prepare($old_portfolio_sql);
    $stmt->execute([$test_user_id]);
    $old_portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Eski sistem sonucu:</strong> " . count($old_portfolio) . " coin bulundu</p>";
    
    // 3. Yeni portföy API'sini test et (is_active kontrolü olmadan)
    echo "<h3>3. Yeni Portföy API Testi (is_active kontrolü olmadan)</h3>";
    
    $new_portfolio_sql = "SELECT 
                            p.*,
                            c.coin_adi,
                            c.coin_kodu,
                            c.current_price,
                            c.is_active
                          FROM (
                            SELECT 
                                ci.coin_id,
                                SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar
                            FROM coin_islemleri ci
                            WHERE ci.user_id = ?
                            GROUP BY ci.coin_id
                            HAVING SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) > 0.00000001
                          ) p
                          JOIN coins c ON p.coin_id = c.id";
    
    $stmt = $conn->prepare($new_portfolio_sql);
    $stmt->execute([$test_user_id]);
    $new_portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Yeni sistem sonucu:</strong> " . count($new_portfolio) . " coin bulundu</p>";
    
    // 4. Fark analizi
    echo "<h3>4. Sistem Karşılaştırması</h3>";
    
    $old_count = count($old_portfolio);
    $new_count = count($new_portfolio);
    
    if ($old_count == $new_count) {
        echo "<p style='color: green;'>✅ Her iki sistem de aynı sayıda coin gösteriyor.</p>";
    } else {
        echo "<p style='color: red;'>❌ Sistemler arasında fark var!</p>";
        echo "<p>Eski sistem: {$old_count} coin</p>";
        echo "<p>Yeni sistem: {$new_count} coin</p>";
        
        // Eksik coin'leri bul
        $old_coins = array_column($old_portfolio, 'coin_kodu');
        $new_coins = array_column($new_portfolio, 'coin_kodu');
        
        $missing_in_old = array_diff($new_coins, $old_coins);
        $missing_in_new = array_diff($old_coins, $new_coins);
        
        if (!empty($missing_in_old)) {
            echo "<p><strong>Eski sistemde eksik:</strong> " . implode(', ', $missing_in_old) . "</p>";
        }
        
        if (!empty($missing_in_new)) {
            echo "<p><strong>Yeni sistemde eksik:</strong> " . implode(', ', $missing_in_new) . "</p>";
        }
    }
    
    // 5. Pasif coin'leri göster
    echo "<h3>5. Pasif Coin'ler (is_active = 0)</h3>";
    
    $passive_coins_sql = "SELECT coin_adi, coin_kodu, current_price, is_active FROM coins WHERE is_active = 0";
    $stmt = $conn->prepare($passive_coins_sql);
    $stmt->execute();
    $passive_coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($passive_coins)) {
        echo "<p style='color: green;'>✅ Hiç pasif coin yok.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ " . count($passive_coins) . " pasif coin bulundu:</p>";
        echo "<ul>";
        foreach ($passive_coins as $coin) {
            echo "<li>{$coin['coin_adi']} ({$coin['coin_kodu']}) - ₺" . number_format($coin['current_price'], 2) . "</li>";
        }
        echo "</ul>";
    }
    
    // 6. API Test
    echo "<h3>6. Trading API Testi</h3>";
    
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
            $api_portfolio_count = count($api_data['data']['portfolio'] ?? []);
            echo "<p style='color: green;'>✅ API başarılı - {$api_portfolio_count} coin döndürüldü</p>";
            
            if ($api_portfolio_count != $new_count) {
                echo "<p style='color: orange;'>⚠️ API sonucu ile veritabanı sonucu farklı!</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ API hatası: " . ($api_data['message'] ?? 'Bilinmeyen hata') . "</p>";
        }
    }
    
    // 7. Sonuç
    echo "<h3>7. Test Sonucu</h3>";
    
    if ($old_count == $new_count && empty($passive_coins)) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
        echo "<h4>✅ Test Başarılı</h4>";
        echo "<p>Portföy görünürlük sorunu düzeltildi. Fiyat güncellemesi portföy görünürlüğünü etkilemiyor.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "<h4>❌ Sorun Devam Ediyor</h4>";
        echo "<p>Hala bazı coin'ler portföyde görünmüyor olabilir. Lütfen detayları inceleyin.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Test hatası: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Test tamamlandı:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><a href='user-panel.html'>← Kullanıcı Paneline Dön</a></p>";
?>
