<?php
require_once 'backend/config.php';

try {
    $conn = db_connect();
    
    echo "<h1>🔍 Coins Veritabanı Kontrolü ve Düzeltme</h1>";
    
    // 1. Coins tablosu var mı?
    echo "<h2>1. Coins Tablosu Kontrolü</h2>";
    $table_check = $conn->prepare("SHOW TABLES LIKE 'coins'");
    $table_check->execute();
    
    if ($table_check->rowCount() == 0) {
        echo "❌ Coins tablosu bulunamadı! Tablo oluşturuluyor...<br>";
        
        // Coins tablosunu oluştur
        $create_table = "CREATE TABLE IF NOT EXISTS coins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coin_adi VARCHAR(100) NOT NULL,
            coin_kodu VARCHAR(10) NOT NULL UNIQUE,
            current_price DECIMAL(20,8) DEFAULT 0,
            price_change_24h DECIMAL(10,4) DEFAULT 0,
            coin_type ENUM('api', 'manual') DEFAULT 'api',
            price_source VARCHAR(50) DEFAULT 'binance',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $conn->exec($create_table);
        echo "✅ Coins tablosu oluşturuldu!<br>";
    } else {
        echo "✅ Coins tablosu mevcut<br>";
    }
    
    // 2. Coins sayısını kontrol et
    echo "<h2>2. Mevcut Coin Sayısı</h2>";
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM coins WHERE is_active = 1");
    $count_stmt->execute();
    $coin_count = $count_stmt->fetchColumn();
    
    echo "Aktif coin sayısı: <strong>$coin_count</strong><br>";
    
    if ($coin_count == 0) {
        echo "<h2>3. Test Coinleri Ekleniyor...</h2>";
        
        // Test coinleri ekle
        $test_coins = [
            ['Bitcoin', 'BTC', 2850000, 2.5],
            ['Ethereum', 'ETH', 95000, -1.2],
            ['BNB', 'BNB', 15000, 0.8],
            ['Cardano', 'ADA', 12.5, 3.2],
            ['Solana', 'SOL', 350, -2.1],
            ['XRP', 'XRP', 18.5, 1.5],
            ['Dogecoin', 'DOGE', 2.8, 5.2],
            ['Polygon', 'MATIC', 28, -0.5],
            ['Chainlink', 'LINK', 450, 2.8],
            ['Avalanche', 'AVAX', 120, 1.9]
        ];
        
        $insert_sql = "INSERT INTO coins (coin_adi, coin_kodu, current_price, price_change_24h, coin_type, is_active) 
                       VALUES (?, ?, ?, ?, 'manual', 1)";
        $insert_stmt = $conn->prepare($insert_sql);
        
        $added_count = 0;
        foreach ($test_coins as $coin) {
            try {
                $insert_stmt->execute($coin);
                echo "✅ {$coin[1]} ({$coin[0]}) eklendi - ₺{$coin[2]}<br>";
                $added_count++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo "ℹ️ {$coin[1]} zaten mevcut<br>";
                } else {
                    echo "❌ {$coin[1]} eklenirken hata: " . $e->getMessage() . "<br>";
                }
            }
        }
        
        echo "<br><strong>$added_count yeni coin eklendi!</strong><br>";
    } else {
        echo "<h2>3. Mevcut Coinler</h2>";
        $coins_stmt = $conn->prepare("SELECT coin_kodu, coin_adi, current_price, is_active FROM coins ORDER BY coin_kodu");
        $coins_stmt->execute();
        $coins = $coins_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Kod</th><th>Ad</th><th>Fiyat (₺)</th><th>Aktif</th></tr>";
        foreach ($coins as $coin) {
            $status = $coin['is_active'] ? '✅' : '❌';
            echo "<tr>";
            echo "<td>{$coin['coin_kodu']}</td>";
            echo "<td>{$coin['coin_adi']}</td>";
            echo "<td>₺" . number_format($coin['current_price'], 2) . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Coins API'sini test et
    echo "<h2>4. Coins API Test</h2>";
    echo "<button onclick=\"testCoinsAPI()\">Coins API'sini Test Et</button>";
    echo "<div id='api-result'></div>";
    
    echo "<script>
    async function testCoinsAPI() {
        const resultDiv = document.getElementById('api-result');
        resultDiv.innerHTML = '🔄 Test ediliyor...';
        
        try {
            const response = await fetch('backend/user/coins.php');
            const data = await response.json();
            
            if (data.success) {
                resultDiv.innerHTML = `
                    <div style='background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px;'>
                        <h3>✅ Coins API Başarılı!</h3>
                        <p><strong>Dönen Coin Sayısı:</strong> \${data.coins.length}</p>
                        <pre>\${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div style='background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>
                        <h3>❌ Coins API Hatası</h3>
                        <p><strong>Hata:</strong> \${data.message}</p>
                        <pre>\${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            }
        } catch (error) {
            resultDiv.innerHTML = `
                <div style='background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>
                    <h3>❌ Network Hatası</h3>
                    <p><strong>Hata:</strong> \${error.message}</p>
                </div>
            `;
        }
    }
    </script>";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage();
}
?>
