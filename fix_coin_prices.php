<?php
require_once 'backend/config.php';

echo "<h1>COIN FİYAT DÜZELTME</h1>";

try {
    $conn = db_connect();
    
    echo "<h2>1. Mevcut Fiyatlar</h2>";
    $stmt = $conn->prepare('SELECT coin_adi, coin_kodu, current_price FROM coins WHERE coin_kodu IN ("T", "SEX") ORDER BY id');
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Coin</th><th>Kod</th><th>Eski Fiyat</th></tr>";
    foreach ($coins as $coin) {
        echo "<tr>";
        echo "<td>" . $coin['coin_adi'] . "</td>";
        echo "<td>" . $coin['coin_kodu'] . "</td>";
        echo "<td>₺" . number_format($coin['current_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>2. Fiyat Güncelleme İşlemi</h2>";
    
    // Tugaycoin fiyatını güncelle
    $stmt = $conn->prepare('UPDATE coins SET current_price = 150000 WHERE coin_kodu = "T"');
    $result1 = $stmt->execute();
    echo "<p>Tugaycoin (T) güncelleme: " . ($result1 ? "✅ BAŞARILI" : "❌ BAŞARISIZ") . "</p>";
    
    // SEX coin fiyatını güncelle  
    $stmt = $conn->prepare('UPDATE coins SET current_price = 120 WHERE coin_kodu = "SEX"');
    $result2 = $stmt->execute();
    echo "<p>SEX coin güncelleme: " . ($result2 ? "✅ BAŞARILI" : "❌ BAŞARISIZ") . "</p>";
    
    echo "<h2>3. Güncellenmiş Fiyatlar</h2>";
    $stmt = $conn->prepare('SELECT coin_adi, coin_kodu, current_price FROM coins WHERE coin_kodu IN ("T", "SEX") ORDER BY id');
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Coin</th><th>Kod</th><th>Yeni Fiyat</th></tr>";
    foreach ($coins as $coin) {
        echo "<tr>";
        echo "<td>" . $coin['coin_adi'] . "</td>";
        echo "<td>" . $coin['coin_kodu'] . "</td>";
        echo "<td>₺" . number_format($coin['current_price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>4. Portföy Test</h2>";
    echo "<p><a href='test_portfolio_simple.php' target='_blank'>Portföy Testini Çalıştır</a></p>";
    echo "<p><a href='user-panel.html' target='_blank'>User Panel'i Aç</a></p>";
    
    echo "<h2>✅ İŞLEM TAMAMLANDI</h2>";
    echo "<p>Artık portföy sayfanızı kontrol edebilirsiniz. Kar/zarar oranları düzelmiş olmalı.</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ HATA</h2>";
    echo "<p>Hata: " . $e->getMessage() . "</p>";
}
?>
