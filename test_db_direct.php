<?php
require_once 'backend/config.php';

echo "🔄 Veritabanı bağlantısı test ediliyor...\n\n";

try {
    $conn = db_connect();
    echo "✅ Veritabanı bağlantısı başarılı!\n\n";
    
    // Tabloları listele
    echo "📋 Mevcut tablolar:\n";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
    echo "\n";
    
    // Coins tablosu var mı kontrol et
    if (in_array('coins', $tables)) {
        echo "✅ coins tablosu mevcut\n\n";
        
        // Coins tablosu yapısını göster
        echo "🏗️ coins tablosu yapısı:\n";
        $columns = $conn->query("DESCRIBE coins")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}\n";
        }
        echo "\n";
        
        // Coin sayısını kontrol et
        $count = $conn->query("SELECT COUNT(*) FROM coins WHERE is_active = 1")->fetchColumn();
        echo "📊 Aktif coin sayısı: {$count}\n\n";
        
        if ($count > 0) {
            echo "💰 İlk 10 coin:\n";
            $coins = $conn->query("SELECT id, coin_adi, coin_kodu, current_price, price_change_24h, coin_type, price_source FROM coins WHERE is_active = 1 ORDER BY id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($coins as $coin) {
                echo sprintf("  %d. %s (%s) - ₺%s (%s%%) [%s/%s]\n", 
                    $coin['id'],
                    $coin['coin_adi'],
                    $coin['coin_kodu'],
                    number_format($coin['current_price'], 2),
                    $coin['price_change_24h'],
                    $coin['coin_type'],
                    $coin['price_source']
                );
            }
        } else {
            echo "❌ Hiç aktif coin bulunamadı!\n";
            echo "🔧 Yeni sistem kurulumu gerekli olabilir.\n";
        }
        
    } else {
        echo "❌ coins tablosu bulunamadı!\n";
        echo "🔧 Veritabanı kurulumu gerekli.\n\n";
        
        echo "📋 Mevcut tablolar:\n";
        foreach ($tables as $table) {
            echo "  - {$table}\n";
        }
    }
    
    echo "\n";
    
    // Price history tablosu kontrol
    if (in_array('price_history', $tables)) {
        $price_count = $conn->query("SELECT COUNT(*) FROM price_history")->fetchColumn();
        echo "📈 Fiyat geçmişi kayıt sayısı: {$price_count}\n";
    }
    
    // Portfolio tablosu kontrol
    if (in_array('portfolios', $tables)) {
        $portfolio_count = $conn->query("SELECT COUNT(*) FROM portfolios WHERE miktar > 0")->fetchColumn();
        echo "👤 Aktif portföy sayısı: {$portfolio_count}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    echo "📍 Dosya: " . $e->getFile() . " Satır: " . $e->getLine() . "\n";
}
?>
