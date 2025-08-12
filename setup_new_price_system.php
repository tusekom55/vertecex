<?php
/**
 * Yeni Fiyat Sistemi Kurulum Dosyası
 * Bu dosyayı bir kez çalıştırarak sistemi kurun
 */

require_once 'backend/config.php';

echo "<h1>🚀 YENİ FİYAT SİSTEMİ KURULUMU</h1>";

try {
    $conn = db_connect();
    
    echo "<h2>1. Veritabanı Şeması Güncelleniyor...</h2>";
    
    // SQL dosyasını oku ve çalıştır
    $sql_content = file_get_contents('database_schema_update.sql');
    $sql_statements = explode(';', $sql_content);
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($sql_statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $conn->exec($statement);
            $success_count++;
            echo "<p style='color:green;'>✅ SQL başarılı: " . substr($statement, 0, 50) . "...</p>";
        } catch (Exception $e) {
            // Sütun zaten varsa hatayı yok say
            if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color:blue;'>ℹ️ Zaten mevcut: " . substr($statement, 0, 50) . "...</p>";
                $success_count++;
            } else {
                $error_count++;
                echo "<p style='color:red;'>❌ SQL hatası: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<h2>2. Cache Klasörü Oluşturuluyor...</h2>";
    $cache_dir = 'backend/cache';
    if (!is_dir($cache_dir)) {
        if (mkdir($cache_dir, 0755, true)) {
            echo "<p style='color:green;'>✅ Cache klasörü oluşturuldu: {$cache_dir}</p>";
        } else {
            echo "<p style='color:red;'>❌ Cache klasörü oluşturulamadı</p>";
        }
    } else {
        echo "<p style='color:blue;'>ℹ️ Cache klasörü zaten mevcut</p>";
    }
    
    echo "<h2>3. Fiyat Yöneticisi Test Ediliyor...</h2>";
    require_once 'backend/utils/price_manager.php';
    
    $priceManager = new PriceManager();
    
    // Test güncellemesi
    echo "<p>Manuel coinler için test dalgalanması yapılıyor...</p>";
    $priceManager->updateAllPrices();
    echo "<p style='color:green;'>✅ Fiyat güncelleme testi başarılı</p>";
    
    echo "<h2>4. Sistem Durumu Kontrolü</h2>";
    
    // Sütunların varlığını kontrol et
    $columns_check = $conn->prepare("SHOW COLUMNS FROM coins LIKE 'is_api_coin'");
    $columns_check->execute();
    
    if ($columns_check->rowCount() > 0) {
        // Coin sayıları
        $stmt = $conn->prepare("SELECT 
            COUNT(*) as total_coins,
            SUM(CASE WHEN is_api_coin = 1 THEN 1 ELSE 0 END) as api_coins,
            SUM(CASE WHEN is_api_coin = 0 THEN 1 ELSE 0 END) as manual_coins
            FROM coins WHERE is_active = 1");
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Sütunlar henüz eklenmemişse basit sayım
        $stmt = $conn->prepare("SELECT COUNT(*) as total_coins FROM coins WHERE is_active = 1");
        $stmt->execute();
        $total = $stmt->fetchColumn();
        $stats = ['total_coins' => $total, 'api_coins' => 'Bilinmiyor', 'manual_coins' => 'Bilinmiyor'];
    }
    
    echo "<table border='1' style='border-collapse:collapse; margin:20px 0;'>";
    echo "<tr><th style='padding:10px; background:#f0f0f0;'>Özellik</th><th style='padding:10px; background:#f0f0f0;'>Değer</th></tr>";
    echo "<tr><td style='padding:8px;'>Toplam Aktif Coin</td><td style='padding:8px;'>{$stats['total_coins']}</td></tr>";
    echo "<tr><td style='padding:8px;'>API Coinleri</td><td style='padding:8px;'>{$stats['api_coins']}</td></tr>";
    echo "<tr><td style='padding:8px;'>Manuel Coinler</td><td style='padding:8px;'>{$stats['manual_coins']}</td></tr>";
    echo "<tr><td style='padding:8px;'>SQL Başarılı</td><td style='padding:8px;'>{$success_count}</td></tr>";
    echo "<tr><td style='padding:8px;'>SQL Hatalı</td><td style='padding:8px;'>{$error_count}</td></tr>";
    echo "</table>";
    
    echo "<h2>5. Test Linkleri</h2>";
    echo "<div style='margin:20px 0;'>";
    echo "<a href='backend/utils/price_manager.php?update_prices=1' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#007bff; color:white; text-decoration:none; border-radius:5px;'>🔄 Fiyatları Güncelle</a>";
    echo "<a href='backend/admin/price_control.php' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#28a745; color:white; text-decoration:none; border-radius:5px;'>🎯 Admin Fiyat Kontrol</a>";
    echo "<a href='user-panel.html' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#ffc107; color:black; text-decoration:none; border-radius:5px;'>👤 User Panel</a>";
    echo "<a href='test_portfolio_simple.php' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#17a2b8; color:white; text-decoration:none; border-radius:5px;'>📊 Portföy Test</a>";
    echo "</div>";
    
    echo "<h2>✅ KURULUM TAMAMLANDI!</h2>";
    echo "<div style='background:#d4edda; padding:20px; border-radius:8px; margin:20px 0;'>";
    echo "<h3 style='color:#155724; margin-top:0;'>Sistem Özellikleri:</h3>";
    echo "<ul style='color:#155724;'>";
    echo "<li><strong>API Coinleri:</strong> CoinGecko'dan gerçek zamanlı fiyatlar (BTC, ETH, BNB, XRP, USDT, ADA, SOL, DOGE, MATIC, DOT)</li>";
    echo "<li><strong>Manuel Coinler:</strong> %5 ile %30 arası sahte dalgalanma (T, SEX, TTT)</li>";
    echo "<li><strong>Otomatik Güncelleme:</strong> Her 5 dakikada bir fiyatlar güncellenir</li>";
    echo "<li><strong>Admin Kontrol:</strong> Manuel coinler için fiyat artırma paneli</li>";
    echo "<li><strong>Portföy Hesaplama:</strong> FIFO mantığı ile doğru kar/zarar hesaplaması</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>📋 Sonraki Adımlar:</h3>";
    echo "<ol>";
    echo "<li>Admin panelinden fiyat kontrollerini test edin</li>";
    echo "<li>User panelinde portföy değerlerini kontrol edin</li>";
    echo "<li>Alış/satış işlemlerini test edin</li>";
    echo "<li>Cron job kurun: <code>*/5 * * * * php /path/to/backend/utils/price_manager.php</code></li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ KURULUM HATASI</h2>";
    echo "<p style='color:red;'>Hata: " . $e->getMessage() . "</p>";
    echo "<p>Lütfen veritabanı bağlantınızı kontrol edin ve tekrar deneyin.</p>";
}
?>
