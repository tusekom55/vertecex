<?php
require_once 'backend/config.php';

echo "<h1>SATIŞ İŞLEMİ TEST</h1>";

try {
    $conn = db_connect();
    
    echo "<h2>1. Mevcut Portföy</h2>";
    $stmt = $conn->prepare('
        SELECT 
            ci.coin_id,
            c.coin_adi,
            c.coin_kodu,
            c.current_price,
            SUM(CASE WHEN ci.islem = "al" THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
            AVG(CASE WHEN ci.islem = "al" THEN ci.fiyat ELSE NULL END) as avg_buy_price
        FROM coin_islemleri ci
        JOIN coins c ON ci.coin_id = c.id
        WHERE ci.user_id = 1
        GROUP BY ci.coin_id, c.coin_adi, c.coin_kodu, c.current_price
        HAVING net_miktar > 0
    ');
    $stmt->execute();
    $portfolio = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Coin</th><th>Net Miktar</th><th>Ort. Alış</th><th>Güncel Fiyat</th><th>Değer</th><th>Test Satış</th></tr>";
    
    foreach ($portfolio as $item) {
        $net_miktar = floatval($item['net_miktar']);
        $current_price = floatval($item['current_price']);
        $avg_buy_price = floatval($item['avg_buy_price']);
        $current_value = $net_miktar * $current_price;
        
        echo "<tr>";
        echo "<td>" . $item['coin_adi'] . " (" . $item['coin_kodu'] . ")</td>";
        echo "<td>" . number_format($net_miktar, 8) . "</td>";
        echo "<td>₺" . number_format($avg_buy_price, 2) . "</td>";
        echo "<td>₺" . number_format($current_price, 2) . "</td>";
        echo "<td>₺" . number_format($current_value, 2) . "</td>";
        echo "<td>";
        echo "<form method='post' style='display:inline;'>";
        echo "<input type='hidden' name='test_sell' value='1'>";
        echo "<input type='hidden' name='coin_id' value='" . $item['coin_id'] . "'>";
        echo "<input type='hidden' name='miktar' value='" . ($net_miktar * 0.1) . "'>";
        echo "<input type='submit' value='%10 Sat'>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test satış işlemi
    if (isset($_POST['test_sell'])) {
        echo "<h2>2. Test Satış İşlemi</h2>";
        
        $coin_id = intval($_POST['coin_id']);
        $miktar = floatval($_POST['miktar']);
        
        echo "<p>Satış testi: Coin ID {$coin_id}, Miktar: {$miktar}</p>";
        
        // Coin bilgilerini al
        $stmt = $conn->prepare('SELECT coin_adi, coin_kodu, current_price FROM coins WHERE id = ?');
        $stmt->execute([$coin_id]);
        $coin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coin) {
            $fiyat = floatval($coin['current_price']);
            $toplam_tutar = $miktar * $fiyat;
            
            echo "<p>Coin: {$coin['coin_adi']} ({$coin['coin_kodu']})</p>";
            echo "<p>Satış miktarı: {$miktar}</p>";
            echo "<p>Güncel fiyat: ₺{$fiyat}</p>";
            echo "<p>Toplam tutar: ₺{$toplam_tutar}</p>";
            
            // Gerçek satış API'sini test et
            $url = 'https://silver-eland-900684.hostingersite.com/backend/user/trading.php?action=sell';
            $data = [
                'coin_id' => $coin_id,
                'miktar' => $miktar,
                'fiyat' => $fiyat
            ];
            
            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data)
                ]
            ];
            
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            echo "<h3>API Yanıtı:</h3>";
            echo "<pre>" . htmlspecialchars($result) . "</pre>";
            
            $response = json_decode($result, true);
            if ($response) {
                if ($response['success']) {
                    echo "<p style='color:green;'>✅ Satış başarılı!</p>";
                } else {
                    echo "<p style='color:red;'>❌ Satış başarısız: " . $response['message'] . "</p>";
                    if (isset($response['debug'])) {
                        echo "<h4>Debug Bilgileri:</h4>";
                        echo "<pre>" . print_r($response['debug'], true) . "</pre>";
                    }
                }
            } else {
                echo "<p style='color:red;'>❌ API yanıtı parse edilemedi</p>";
            }
        }
    }
    
    echo "<h2>3. İşlem Geçmişi (Son 10)</h2>";
    $stmt = $conn->prepare('
        SELECT ci.*, c.coin_adi, c.coin_kodu 
        FROM coin_islemleri ci 
        JOIN coins c ON ci.coin_id = c.id 
        WHERE ci.user_id = 1 
        ORDER BY ci.tarih DESC 
        LIMIT 10
    ');
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Tarih</th><th>Coin</th><th>İşlem</th><th>Miktar</th><th>Fiyat</th></tr>";
    foreach ($transactions as $tx) {
        echo "<tr>";
        echo "<td>" . $tx['tarih'] . "</td>";
        echo "<td>" . $tx['coin_adi'] . " (" . $tx['coin_kodu'] . ")</td>";
        echo "<td>" . $tx['islem'] . "</td>";
        echo "<td>" . $tx['miktar'] . "</td>";
        echo "<td>₺" . number_format($tx['fiyat'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h2>❌ HATA</h2>";
    echo "<p>Hata: " . $e->getMessage() . "</p>";
}
?>
