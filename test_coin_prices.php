<?php
require_once 'backend/config.php';

try {
    $conn = db_connect();
    
    echo "<h2>VERİTABANINDAKİ COIN FİYATLARI</h2>";
    $stmt = $conn->prepare('SELECT coin_adi, coin_kodu, current_price FROM coins WHERE is_active = 1 ORDER BY id');
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Coin Adı</th><th>Kod</th><th>Güncel Fiyat</th></tr>";
    foreach ($coins as $coin) {
        echo "<tr>";
        echo "<td>" . $coin['coin_adi'] . "</td>";
        echo "<td>" . $coin['coin_kodu'] . "</td>";
        echo "<td>₺" . number_format($coin['current_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>PORTFÖY İŞLEMLERİ (Son 10)</h2>";
    $stmt = $conn->prepare('
        SELECT ci.coin_id, c.coin_adi, c.coin_kodu, ci.islem_turu, ci.miktar, ci.fiyat, ci.tarih 
        FROM coin_islemleri ci 
        LEFT JOIN coins c ON ci.coin_id = c.id 
        WHERE ci.user_id = 1 
        ORDER BY ci.tarih DESC 
        LIMIT 10
    ');
    $stmt->execute();
    $islemler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Coin</th><th>İşlem</th><th>Miktar</th><th>Fiyat</th><th>Tarih</th></tr>";
    foreach ($islemler as $islem) {
        echo "<tr>";
        echo "<td>" . $islem['coin_adi'] . " (" . $islem['coin_kodu'] . ")</td>";
        echo "<td>" . $islem['islem_turu'] . "</td>";
        echo "<td>" . $islem['miktar'] . "</td>";
        echo "<td>₺" . number_format($islem['fiyat'], 2) . "</td>";
        echo "<td>" . $islem['tarih'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>GÜNCEL FİYAT GÜNCELLEMESİ</h2>";
    echo "<p>Şimdi fiyatları gerçekçi değerlere güncelleyeceğim...</p>";
    
    // Fiyatları güncelle
    $updates = [
        'T' => 150000,    // Tugaycoin için gerçekçi fiyat
        'SEX' => 120,     // SEX coin için gerçekçi fiyat
        'BTC' => 1350000, // Bitcoin
        'ETH' => 85000,   // Ethereum
        'BNB' => 12500    // BNB
    ];
    
    foreach ($updates as $coin_kodu => $new_price) {
        $stmt = $conn->prepare('UPDATE coins SET current_price = ? WHERE coin_kodu = ?');
        $result = $stmt->execute([$new_price, $coin_kodu]);
        echo "<p>$coin_kodu fiyatı ₺" . number_format($new_price, 2) . " olarak güncellendi.</p>";
    }
    
    echo "<h2>GÜNCELLENMİŞ FİYATLAR</h2>";
    $stmt = $conn->prepare('SELECT coin_adi, coin_kodu, current_price FROM coins WHERE is_active = 1 ORDER BY id');
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Coin Adı</th><th>Kod</th><th>Yeni Fiyat</th></tr>";
    foreach ($coins as $coin) {
        echo "<tr>";
        echo "<td>" . $coin['coin_adi'] . "</td>";
        echo "<td>" . $coin['coin_kodu'] . "</td>";
        echo "<td>₺" . number_format($coin['current_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}
?>
