<?php
// Gerçek portföy verilerini test etmek için debug dosyası
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/config.php';

echo "<h1>Portföy Debug - Gerçek Veriler</h1>";

try {
    $conn = db_connect();
    echo "<p style='color: green;'>✅ Veritabanı bağlantısı başarılı</p>";
    
    // Test kullanıcısı ID'si
    $user_id = 1;
    
    echo "<h2>1. Kullanıcı İşlemleri (coin_islemleri tablosu)</h2>";
    
    $sql = "SELECT ci.*, c.coin_adi, c.coin_kodu, c.current_price 
            FROM coin_islemleri ci 
            LEFT JOIN coins c ON ci.coin_id = c.id 
            WHERE ci.user_id = ? 
            ORDER BY ci.coin_id, ci.tarih";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p style='color: orange;'>⚠️ Kullanıcı ID {$user_id} için işlem bulunamadı</p>";
        
        // Tüm kullanıcıları listele
        echo "<h3>Mevcut Kullanıcılar:</h3>";
        $users_sql = "SELECT DISTINCT user_id FROM coin_islemleri LIMIT 10";
        $users_stmt = $conn->prepare($users_sql);
        $users_stmt->execute();
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            echo "<p>User ID: {$user['user_id']}</p>";
        }
        
        if (!empty($users)) {
            $user_id = $users[0]['user_id'];
            echo "<p>Test için User ID {$user_id} kullanılacak...</p>";
            
            // Yeniden sorgu çalıştır
            $stmt->execute([$user_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    if (!empty($transactions)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Coin</th><th>İşlem</th><th>Miktar</th><th>Fiyat</th><th>Tarih</th><th>Güncel Fiyat</th></tr>";
        
        foreach ($transactions as $tx) {
            echo "<tr>";
            echo "<td>{$tx['id']}</td>";
            echo "<td>{$tx['coin_adi']} ({$tx['coin_kodu']})</td>";
            echo "<td>{$tx['islem']}</td>";
            echo "<td>{$tx['miktar']}</td>";
            echo "<td>₺{$tx['fiyat']}</td>";
            echo "<td>{$tx['tarih']}</td>";
            echo "<td>₺{$tx['current_price']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h2>2. Portföy Hesaplama (Mevcut Sistem)</h2>";
        
        // Mevcut sistemin portföy hesaplamasını test et
        $portfolio_sql = "SELECT 
                            p.*,
                            c.coin_adi,
                            c.coin_kodu,
                            COALESCE(c.current_price, 0) as current_price_tl
                          FROM (
                            SELECT 
                                ci.coin_id,
                                SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
                                
                                CASE 
                                    WHEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) > 0 
                                    THEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) / 
                                         SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END)
                                    ELSE 0 
                                END as avg_buy_price_tl,
                                
                                SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_bought_amount,
                                SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) as total_bought_quantity,
                                SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_sold_amount,
                                SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar ELSE 0 END) as total_sold_quantity
                            FROM coin_islemleri ci
                            WHERE ci.user_id = ?
                            GROUP BY ci.coin_id
                            HAVING SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) > 0.00000001
                          ) p
                          JOIN coins c ON p.coin_id = c.id
                          WHERE c.is_active = 1";
        
        $portfolio_stmt = $conn->prepare($portfolio_sql);
        $portfolio_stmt->execute([$user_id]);
        $portfolio = $portfolio_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($portfolio)) {
            echo "<p style='color: orange;'>⚠️ Portföyde coin bulunamadı</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Coin</th><th>Net Miktar</th><th>Ort. Alış</th><th>Güncel Fiyat</th><th>Yatırılan (Eski)</th><th>Yatırılan (Yeni)</th><th>Mevcut Değer</th><th>K/Z (Eski)</th><th>K/Z (Yeni)</th><th>% (Eski)</th><th>% (Yeni)</th></tr>";
            
            foreach ($portfolio as $item) {
                $net_miktar = floatval($item['net_miktar']);
                $avg_buy_price = floatval($item['avg_buy_price_tl']);
                $current_price = floatval($item['current_price_tl']);
                $total_bought_amount = floatval($item['total_bought_amount']);
                $total_sold_quantity = floatval($item['total_sold_quantity']);
                
                // ESKİ YANLIŞ HESAPLAMA
                $old_invested_value = $net_miktar * $avg_buy_price;
                
                // YENİ DOĞRU HESAPLAMA
                if ($total_sold_quantity == 0) {
                    $new_invested_value = $net_miktar * $avg_buy_price;
                } else {
                    $sold_cost = $total_sold_quantity * $avg_buy_price;
                    $new_invested_value = $total_bought_amount - $sold_cost;
                    if ($new_invested_value < 0) {
                        $new_invested_value = $net_miktar * $avg_buy_price;
                    }
                }
                
                $current_value = $net_miktar * $current_price;
                
                $old_profit_loss = $current_value - $old_invested_value;
                $new_profit_loss = $current_value - $new_invested_value;
                
                $old_percent = $old_invested_value > 0 ? ($old_profit_loss / $old_invested_value) * 100 : 0;
                $new_percent = $new_invested_value > 0 ? ($new_profit_loss / $new_invested_value) * 100 : 0;
                
                echo "<tr>";
                echo "<td>{$item['coin_kodu']}</td>";
                echo "<td>" . number_format($net_miktar, 4) . "</td>";
                echo "<td>₺" . number_format($avg_buy_price, 2) . "</td>";
                echo "<td>₺" . number_format($current_price, 2) . "</td>";
                echo "<td>₺" . number_format($old_invested_value, 2) . "</td>";
                echo "<td style='background: lightgreen;'>₺" . number_format($new_invested_value, 2) . "</td>";
                echo "<td>₺" . number_format($current_value, 2) . "</td>";
                echo "<td style='color: " . ($old_profit_loss >= 0 ? 'green' : 'red') . "'>₺" . number_format($old_profit_loss, 2) . "</td>";
                echo "<td style='background: lightgreen; color: " . ($new_profit_loss >= 0 ? 'green' : 'red') . "'>₺" . number_format($new_profit_loss, 2) . "</td>";
                echo "<td style='color: " . ($old_percent >= 0 ? 'green' : 'red') . "'>" . number_format($old_percent, 2) . "%</td>";
                echo "<td style='background: lightgreen; color: " . ($new_percent >= 0 ? 'green' : 'red') . "'>" . number_format($new_percent, 2) . "%</td>";
                echo "</tr>";
                
                echo "<tr><td colspan='11' style='font-size: 12px; background: #f0f0f0;'>";
                echo "Toplam Alış: " . number_format($item['total_bought_amount'], 2) . " TL, ";
                echo "Toplam Satış: " . number_format($item['total_sold_amount'], 2) . " TL, ";
                echo "Satılan Miktar: " . number_format($total_sold_quantity, 4);
                echo "</td></tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Hiç işlem bulunamadı</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>
