<?php
// Basit portföy test dosyası
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Config dosyasını yükle
require_once 'backend/config.php';

echo "<h2>Portföy Test - Basit Kontrol</h2>";

try {
    $conn = db_connect();
    echo "<p style='color: green;'>✅ Veritabanı bağlantısı başarılı</p>";
    
    // Test user_id (gerçek kullanıcı ID'si)
    $user_id = 1;
    
    echo "<h3>1. Kullanıcı İşlemleri (user_id: $user_id)</h3>";
    
    // Tüm coin işlemlerini listele
    $sql = "SELECT ci.*, c.coin_adi, c.coin_kodu, c.current_price 
            FROM coin_islemleri ci 
            JOIN coins c ON ci.coin_id = c.id 
            WHERE ci.user_id = ? 
            ORDER BY ci.tarih DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Coin</th><th>İşlem</th><th>Miktar</th><th>Fiyat</th><th>Toplam</th><th>Tarih</th></tr>";
    
    foreach ($transactions as $tx) {
        $total = $tx['miktar'] * $tx['fiyat'];
        $color = $tx['islem'] == 'al' ? 'lightgreen' : 'lightcoral';
        echo "<tr style='background: $color;'>";
        echo "<td>{$tx['id']}</td>";
        echo "<td>{$tx['coin_adi']} ({$tx['coin_kodu']})</td>";
        echo "<td>" . strtoupper($tx['islem']) . "</td>";
        echo "<td>" . number_format($tx['miktar'], 8) . "</td>";
        echo "<td>₺" . number_format($tx['fiyat'], 2) . "</td>";
        echo "<td>₺" . number_format($total, 2) . "</td>";
        echo "<td>{$tx['tarih']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>2. Portföy Hesaplaması</h3>";
    
    // Her coin için net miktar hesapla
    $portfolio_sql = "SELECT 
                        ci.coin_id,
                        c.coin_adi,
                        c.coin_kodu,
                        c.current_price,
                        SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
                        SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_buy_amount,
                        SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) as total_buy_quantity,
                        SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_sell_amount,
                        SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar ELSE 0 END) as total_sell_quantity,
                        CASE 
                            WHEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) > 0 
                            THEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) / 
                                 SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END)
                            ELSE 0 
                        END as avg_buy_price
                      FROM coin_islemleri ci
                      JOIN coins c ON ci.coin_id = c.id
                      WHERE ci.user_id = ?
                      GROUP BY ci.coin_id, c.coin_adi, c.coin_kodu, c.current_price
                      HAVING SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) > 0.00000001";
    
    $portfolio_stmt = $conn->prepare($portfolio_sql);
    $portfolio_stmt->execute([$user_id]);
    $portfolio = $portfolio_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Coin</th><th>Net Miktar</th><th>Ort. Alış</th><th>Güncel Fiyat</th><th>Mevcut Değer</th><th>Yatırım</th><th>K/Z</th><th>K/Z %</th></tr>";
    
    $total_value = 0;
    $total_invested = 0;
    
    foreach ($portfolio as $item) {
        $net_miktar = floatval($item['net_miktar']);
        $current_price = floatval($item['current_price']);
        $avg_buy_price = floatval($item['avg_buy_price']);
        
        $current_value = $net_miktar * $current_price;
        $invested_value = $net_miktar * $avg_buy_price;
        $profit_loss = $current_value - $invested_value;
        $profit_loss_percent = $invested_value > 0 ? ($profit_loss / $invested_value) * 100 : 0;
        
        $total_value += $current_value;
        $total_invested += $invested_value;
        
        $pnl_color = $profit_loss >= 0 ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>{$item['coin_adi']} ({$item['coin_kodu']})</td>";
        echo "<td>" . number_format($net_miktar, 8) . "</td>";
        echo "<td>₺" . number_format($avg_buy_price, 2) . "</td>";
        echo "<td>₺" . number_format($current_price, 2) . "</td>";
        echo "<td>₺" . number_format($current_value, 2) . "</td>";
        echo "<td>₺" . number_format($invested_value, 2) . "</td>";
        echo "<td style='color: $pnl_color;'>" . ($profit_loss >= 0 ? '+' : '') . "₺" . number_format($profit_loss, 2) . "</td>";
        echo "<td style='color: $pnl_color;'>" . ($profit_loss_percent >= 0 ? '+' : '') . number_format($profit_loss_percent, 2) . "%</td>";
        echo "</tr>";
    }
    
    $total_pnl = $total_value - $total_invested;
    $total_pnl_percent = $total_invested > 0 ? ($total_pnl / $total_invested) * 100 : 0;
    
    echo "<tr style='background: yellow; font-weight: bold;'>";
    echo "<td>TOPLAM</td>";
    echo "<td>-</td>";
    echo "<td>-</td>";
    echo "<td>-</td>";
    echo "<td>₺" . number_format($total_value, 2) . "</td>";
    echo "<td>₺" . number_format($total_invested, 2) . "</td>";
    echo "<td style='color: " . ($total_pnl >= 0 ? 'green' : 'red') . ";'>" . ($total_pnl >= 0 ? '+' : '') . "₺" . number_format($total_pnl, 2) . "</td>";
    echo "<td style='color: " . ($total_pnl_percent >= 0 ? 'green' : 'red') . ";'>" . ($total_pnl_percent >= 0 ? '+' : '') . number_format($total_pnl_percent, 2) . "%</td>";
    echo "</tr>";
    echo "</table>";
    
    echo "<h3>3. Kullanıcı Bakiyesi</h3>";
    $balance_sql = "SELECT balance FROM users WHERE id = ?";
    $balance_stmt = $conn->prepare($balance_sql);
    $balance_stmt->execute([$user_id]);
    $balance = $balance_stmt->fetchColumn();
    
    echo "<p><strong>Mevcut Bakiye:</strong> ₺" . number_format($balance, 2) . "</p>";
    
    echo "<h3>4. Problem Tespiti</h3>";
    
    if (empty($portfolio)) {
        echo "<p style='color: red;'>❌ Portföy boş - Hiç coin işlemi yok veya tüm coinler satılmış</p>";
    } else {
        echo "<p style='color: green;'>✅ Portföyde " . count($portfolio) . " farklı coin var</p>";
        
        // Negatif değer kontrolü
        foreach ($portfolio as $item) {
            if ($item['net_miktar'] <= 0) {
                echo "<p style='color: orange;'>⚠️ {$item['coin_kodu']} net miktarı sıfır veya negatif: " . $item['net_miktar'] . "</p>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>
