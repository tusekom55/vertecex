<?php
/**
 * USDS Coin Fiyat Düzeltme Scripti
 */

require_once 'backend/config.php';

try {
    $conn = db_connect();
    
    echo "🔍 USDS Coin Fiyat Kontrolü\n";
    echo "=" . str_repeat("=", 50) . "\n\n";
    
    // USDS coin bilgilerini kontrol et
    $sql = "SELECT id, coin_adi, coin_kodu, current_price, price_change_24h, price_source, last_update 
            FROM coins 
            WHERE coin_kodu = 'USDS' OR coin_adi LIKE '%USDS%'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $usds_coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($usds_coins)) {
        echo "❌ USDS coin bulunamadı!\n";
        
        // USDS coin'i ekle
        $insert_sql = "INSERT INTO coins (coin_adi, coin_kodu, current_price, price_change_24h, price_source, is_active, created_at) 
                       VALUES ('USDS', 'USDS', 34.15, 0, 'manual', 1, NOW())";
        $conn->prepare($insert_sql)->execute();
        echo "✅ USDS coin eklendi (₺34.15)\n";
        
    } else {
        echo "📊 Mevcut USDS Coin Bilgileri:\n";
        foreach ($usds_coins as $coin) {
            echo "ID: {$coin['id']}\n";
            echo "Adı: {$coin['coin_adi']}\n";
            echo "Kod: {$coin['coin_kodu']}\n";
            echo "Fiyat: ₺" . number_format($coin['current_price'], 2) . "\n";
            echo "Değişim: {$coin['price_change_24h']}%\n";
            echo "Kaynak: {$coin['price_source']}\n";
            echo "Güncelleme: {$coin['last_update']}\n";
            echo "-" . str_repeat("-", 30) . "\n";
        }
        
        // Doğru fiyatı ayarla (USDS genellikle 1 USD = ~34 TL)
        $correct_price = 34.15;
        
        foreach ($usds_coins as $coin) {
            if (abs($coin['current_price'] - $correct_price) > 1) {
                echo "🔧 USDS fiyatı düzeltiliyor: ₺{$coin['current_price']} → ₺{$correct_price}\n";
                
                $update_sql = "UPDATE coins 
                               SET current_price = ?, 
                                   price_source = 'admin_fix',
                                   last_update = NOW() 
                               WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->execute([$correct_price, $coin['id']]);
                
                echo "✅ USDS fiyatı güncellendi!\n";
            } else {
                echo "✅ USDS fiyatı zaten doğru: ₺" . number_format($coin['current_price'], 2) . "\n";
            }
        }
    }
    
    echo "\n🔍 Portföy Hesaplama Kontrolü\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    // Kullanıcı portföylerinde USDS kontrolü
    $portfolio_sql = "SELECT p.user_id, p.coin_id, p.miktar, c.coin_kodu, c.current_price,
                             (p.miktar * c.current_price) as toplam_deger
                      FROM portfolios p 
                      JOIN coins c ON p.coin_id = c.id 
                      WHERE c.coin_kodu = 'USDS' AND p.miktar > 0";
    $stmt = $conn->prepare($portfolio_sql);
    $stmt->execute();
    $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($portfolios)) {
        echo "📈 USDS Portföy Durumu:\n";
        foreach ($portfolios as $portfolio) {
            echo "Kullanıcı ID: {$portfolio['user_id']}\n";
            echo "Miktar: {$portfolio['miktar']} USDS\n";
            echo "Birim Fiyat: ₺" . number_format($portfolio['current_price'], 2) . "\n";
            echo "Toplam Değer: ₺" . number_format($portfolio['toplam_deger'], 2) . "\n";
            echo "-" . str_repeat("-", 30) . "\n";
        }
    } else {
        echo "ℹ️ Portföyde USDS bulunamadı\n";
    }
    
    echo "\n🔍 Trading İşlemleri Kontrolü\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    // Son USDS işlemlerini kontrol et
    $trading_sql = "SELECT t.*, c.coin_kodu, c.current_price as coin_price
                    FROM trading_islemleri t
                    JOIN coins c ON t.coin_id = c.id
                    WHERE c.coin_kodu = 'USDS'
                    ORDER BY t.islem_tarihi DESC
                    LIMIT 10";
    $stmt = $conn->prepare($trading_sql);
    $stmt->execute();
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($trades)) {
        echo "📊 Son USDS İşlemleri:\n";
        foreach ($trades as $trade) {
            echo "Tarih: {$trade['islem_tarihi']}\n";
            echo "Tip: {$trade['islem_tipi']}\n";
            echo "Miktar: {$trade['miktar']} USDS\n";
            echo "Fiyat: ₺" . number_format($trade['fiyat'], 2) . "\n";
            echo "Toplam: ₺" . number_format($trade['toplam_tutar'], 2) . "\n";
            echo "Güncel Coin Fiyat: ₺" . number_format($trade['coin_price'], 2) . "\n";
            echo "-" . str_repeat("-", 30) . "\n";
        }
    } else {
        echo "ℹ️ USDS işlemi bulunamadı\n";
    }
    
    echo "\n✅ USDS Fiyat Kontrolü Tamamlandı!\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
