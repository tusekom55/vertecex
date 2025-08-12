<?php
// Portfolio hesaplama debug dosyası
session_start();

// Config dosyasını yükle
require_once 'backend/config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $conn = db_connect();
    
    // Test user_id (varsayılan olarak 1)
    $user_id = $_SESSION['user_id'] ?? 1;
    
    echo "<h2>Portfolio Hesaplama Debug - User ID: {$user_id}</h2>";
    
    // 1. Coin işlemlerini listele
    echo "<h3>1. Coin İşlemleri</h3>";
    $islemler_sql = "SELECT 
                        ci.id,
                        ci.coin_id,
                        c.coin_kodu,
                        ci.islem,
                        ci.miktar,
                        ci.fiyat,
                        ci.tarih,
                        (ci.miktar * ci.fiyat) as toplam_tutar
                     FROM coin_islemleri ci
                     JOIN coins c ON ci.coin_id = c.id
                     WHERE ci.user_id = ?
                     ORDER BY ci.coin_id, ci.tarih";
    
    $islemler_stmt = $conn->prepare($islemler_sql);
    $islemler_stmt->execute([$user_id]);
    $islemler = $islemler_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($islemler)) {
        echo "<p>Hiç işlem bulunamadı.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Coin</th><th>İşlem</th><th>Miktar</th><th>Fiyat (TL)</th><th>Toplam (TL)</th><th>Tarih</th></tr>";
        
        foreach ($islemler as $islem) {
            echo "<tr>";
            echo "<td>{$islem['id']}</td>";
            echo "<td>{$islem['coin_kodu']}</td>";
            echo "<td>{$islem['islem']}</td>";
            echo "<td>{$islem['miktar']}</td>";
            echo "<td>₺" . number_format($islem['fiyat'], 2) . "</td>";
            echo "<td>₺" . number_format($islem['toplam_tutar'], 2) . "</td>";
            echo "<td>{$islem['tarih']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Coin bazında özet hesaplama
    echo "<h3>2. Coin Bazında Özet</h3>";
    $ozet_sql = "SELECT 
                    ci.coin_id,
                    c.coin_kodu,
                    c.coin_adi,
                    c.current_price as guncel_fiyat_tl,
                    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
                    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) as toplam_alinan,
                    SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar ELSE 0 END) as toplam_satilan,
                    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) as toplam_alis_tutari,
                    SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar * ci.fiyat ELSE 0 END) as toplam_satis_tutari,
                    CASE 
                        WHEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) > 0 
                        THEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) / SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END)
                        ELSE 0 
                    END as ortalama_alis_fiyati
                 FROM coin_islemleri ci
                 JOIN coins c ON ci.coin_id = c.id
                 WHERE ci.user_id = ?
                 GROUP BY ci.coin_id, c.coin_kodu, c.coin_adi, c.current_price
                 HAVING net_miktar > 0.00000001
                 ORDER BY net_miktar DESC";
    
    $ozet_stmt = $conn->prepare($ozet_sql);
    $ozet_stmt->execute([$user_id]);
    $ozet = $ozet_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($ozet)) {
        echo "<p>Portföyde coin bulunamadı.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>Coin</th>";
        echo "<th>Net Miktar</th>";
        echo "<th>Ortalama Alış Fiyatı</th>";
        echo "<th>Güncel Fiyat</th>";
        echo "<th>Yatırılan Değer</th>";
        echo "<th>Mevcut Değer</th>";
        echo "<th>Kar/Zarar</th>";
        echo "<th>Kar/Zarar %</th>";
        echo "</tr>";
        
        $toplam_yatirilan = 0;
        $toplam_mevcut = 0;
        
        foreach ($ozet as $coin) {
            $net_miktar = floatval($coin['net_miktar']);
            $ortalama_alis = floatval($coin['ortalama_alis_fiyati']);
            $guncel_fiyat = floatval($coin['guncel_fiyat_tl']);
            
            // DÜZELTME: Doğru yatırılan değer hesaplaması
            $toplam_alis_tutari = floatval($coin['toplam_alis_tutari']);
            $toplam_satis_tutari = floatval($coin['toplam_satis_tutari']);
            $yatirilan_deger = $toplam_alis_tutari - $toplam_satis_tutari;
            
            $mevcut_deger = $net_miktar * $guncel_fiyat;
            $kar_zarar = $mevcut_deger - $yatirilan_deger;
            $kar_zarar_yuzde = $yatirilan_deger > 0 ? (($kar_zarar / $yatirilan_deger) * 100) : 0;
            
            $toplam_yatirilan += $yatirilan_deger;
            $toplam_mevcut += $mevcut_deger;
            
            echo "<tr>";
            echo "<td>{$coin['coin_kodu']}</td>";
            echo "<td>" . number_format($net_miktar, 8) . "</td>";
            echo "<td>₺" . number_format($ortalama_alis, 2) . "</td>";
            echo "<td>₺" . number_format($guncel_fiyat, 2) . "</td>";
            echo "<td>₺" . number_format($yatirilan_deger, 2) . "</td>";
            echo "<td>₺" . number_format($mevcut_deger, 2) . "</td>";
            echo "<td style='color: " . ($kar_zarar >= 0 ? 'green' : 'red') . "'>₺" . number_format($kar_zarar, 2) . "</td>";
            echo "<td style='color: " . ($kar_zarar_yuzde >= 0 ? 'green' : 'red') . "'>" . number_format($kar_zarar_yuzde, 2) . "%</td>";
            echo "</tr>";
        }
        
        $toplam_kar_zarar = $toplam_mevcut - $toplam_yatirilan;
        $toplam_kar_zarar_yuzde = $toplam_yatirilan > 0 ? (($toplam_kar_zarar / $toplam_yatirilan) * 100) : 0;
        
        echo "<tr style='font-weight: bold; background-color: #f0f0f0;'>";
        echo "<td>TOPLAM</td>";
        echo "<td>-</td>";
        echo "<td>-</td>";
        echo "<td>-</td>";
        echo "<td>₺" . number_format($toplam_yatirilan, 2) . "</td>";
        echo "<td>₺" . number_format($toplam_mevcut, 2) . "</td>";
        echo "<td style='color: " . ($toplam_kar_zarar >= 0 ? 'green' : 'red') . "'>₺" . number_format($toplam_kar_zarar, 2) . "</td>";
        echo "<td style='color: " . ($toplam_kar_zarar_yuzde >= 0 ? 'green' : 'red') . "'>" . number_format($toplam_kar_zarar_yuzde, 2) . "%</td>";
        echo "</tr>";
        echo "</table>";
    }
    
    // 3. Coin fiyatları kontrolü
    echo "<h3>3. Coin Fiyatları Kontrolü</h3>";
    $fiyat_sql = "SELECT coin_kodu, coin_adi, current_price, price_change_24h, is_active FROM coins WHERE is_active = 1 ORDER BY coin_kodu";
    $fiyat_stmt = $conn->prepare($fiyat_sql);
    $fiyat_stmt->execute();
    $fiyatlar = $fiyat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Coin Kodu</th><th>Coin Adı</th><th>Güncel Fiyat (TL)</th><th>24h Değişim</th><th>Aktif</th></tr>";
    
    foreach ($fiyatlar as $fiyat) {
        echo "<tr>";
        echo "<td>{$fiyat['coin_kodu']}</td>";
        echo "<td>{$fiyat['coin_adi']}</td>";
        echo "<td>₺" . number_format($fiyat['current_price'], 2) . "</td>";
        echo "<td>" . number_format($fiyat['price_change_24h'], 2) . "%</td>";
        echo "<td>" . ($fiyat['is_active'] ? 'Evet' : 'Hayır') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Hata: " . $e->getMessage() . "</h3>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
