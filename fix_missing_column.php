<?php
/**
 * Eksik price_source sütununu ekle
 */

require_once 'backend/config.php';

echo "<h1>🔧 EKSİK SÜTUN DÜZELTMESİ</h1>";

try {
    $conn = db_connect();
    
    echo "<h2>1. price_source Sütunu Kontrol Ediliyor...</h2>";
    
    // Sütunun var olup olmadığını kontrol et
    $check = $conn->prepare("SHOW COLUMNS FROM coins LIKE 'price_source'");
    $check->execute();
    
    if ($check->rowCount() == 0) {
        echo "<p>price_source sütunu bulunamadı, ekleniyor...</p>";
        
        // Sütunu ekle
        $conn->exec("ALTER TABLE coins ADD COLUMN price_source VARCHAR(20) DEFAULT 'manual'");
        echo "<p style='color:green;'>✅ price_source sütunu başarıyla eklendi</p>";
        
        // API coinlerini işaretle
        $conn->exec("UPDATE coins SET price_source = 'api' WHERE coin_kodu IN ('BTC', 'ETH', 'BNB', 'XRP', 'USDT', 'ADA', 'SOL', 'DOGE', 'MATIC', 'DOT')");
        echo "<p style='color:green;'>✅ API coinleri işaretlendi</p>";
        
        // Manuel coinleri işaretle
        $conn->exec("UPDATE coins SET price_source = 'manual' WHERE coin_kodu IN ('T', 'SEX', 'TTT')");
        echo "<p style='color:green;'>✅ Manuel coinler işaretlendi</p>";
        
        // İndeks ekle
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_coins_source ON coins(price_source)");
        echo "<p style='color:green;'>✅ İndeks eklendi</p>";
        
    } else {
        echo "<p style='color:blue;'>ℹ️ price_source sütunu zaten mevcut</p>";
    }
    
    echo "<h2>2. API/Manuel Coin Ayrımı Güncelleniyor...</h2>";
    
    // API coinlerini güncelle
    $api_update = $conn->exec("UPDATE coins SET is_api_coin = 1, price_source = 'api' WHERE coin_kodu IN ('BTC', 'ETH', 'BNB', 'XRP', 'USDT', 'ADA', 'SOL', 'DOGE', 'MATIC', 'DOT')");
    echo "<p style='color:green;'>✅ {$api_update} API coin güncellendi</p>";
    
    // Manuel coinleri güncelle
    $manual_update = $conn->exec("UPDATE coins SET is_api_coin = 0, price_source = 'manual' WHERE coin_kodu IN ('T', 'SEX', 'TTT')");
    echo "<p style='color:green;'>✅ {$manual_update} Manuel coin güncellendi</p>";
    
    echo "<h2>3. Son Durum Kontrolü</h2>";
    
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_coins,
        SUM(CASE WHEN is_api_coin = 1 THEN 1 ELSE 0 END) as api_coins,
        SUM(CASE WHEN is_api_coin = 0 THEN 1 ELSE 0 END) as manual_coins
        FROM coins WHERE is_active = 1");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse:collapse; margin:20px 0;'>";
    echo "<tr><th style='padding:10px; background:#f0f0f0;'>Özellik</th><th style='padding:10px; background:#f0f0f0;'>Değer</th></tr>";
    echo "<tr><td style='padding:8px;'>Toplam Aktif Coin</td><td style='padding:8px;'>{$stats['total_coins']}</td></tr>";
    echo "<tr><td style='padding:8px;'>API Coinleri</td><td style='padding:8px;'>{$stats['api_coins']}</td></tr>";
    echo "<tr><td style='padding:8px;'>Manuel Coinler</td><td style='padding:8px;'>{$stats['manual_coins']}</td></tr>";
    echo "</table>";
    
    echo "<h2>4. Test Linkleri</h2>";
    echo "<div style='margin:20px 0;'>";
    echo "<a href='backend/utils/price_manager.php?update_prices=1' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#007bff; color:white; text-decoration:none; border-radius:5px;'>🔄 Fiyatları Güncelle</a>";
    echo "<a href='backend/admin/price_control.php' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#28a745; color:white; text-decoration:none; border-radius:5px;'>🎯 Admin Fiyat Kontrol</a>";
    echo "<a href='user-panel.html' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#ffc107; color:black; text-decoration:none; border-radius:5px;'>👤 User Panel</a>";
    echo "<a href='test_portfolio_simple.php' target='_blank' style='display:inline-block; margin:5px; padding:10px 15px; background:#17a2b8; color:white; text-decoration:none; border-radius:5px;'>📊 Portföy Test</a>";
    echo "</div>";
    
    echo "<h2>✅ DÜZELTME TAMAMLANDI!</h2>";
    echo "<div style='background:#d4edda; padding:20px; border-radius:8px; margin:20px 0;'>";
    echo "<h3 style='color:#155724; margin-top:0;'>Sistem Artık Tamamen Hazır!</h3>";
    echo "<p style='color:#155724;'>Tüm sütunlar eklendi ve coin ayrımları yapıldı. Artık fiyat sistemi tam olarak çalışacak.</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ HATA</h2>";
    echo "<p style='color:red;'>Hata: " . $e->getMessage() . "</p>";
}
?>
