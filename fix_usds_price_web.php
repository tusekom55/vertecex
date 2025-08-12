<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USDS Fiyat Düzeltme</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
        }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .success-box {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .price {
            font-weight: bold;
            color: #007bff;
        }
        .old-price {
            color: #dc3545;
            text-decoration: line-through;
        }
        .new-price {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 USDS Coin Fiyat Düzeltme Sistemi</h1>
        
        <?php
        require_once 'backend/config.php';
        
        $messages = [];
        $errors = [];
        
        try {
            $conn = db_connect();
            
            // POST işlemi varsa
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';
                
                if ($action === 'fix_usds_price') {
                    $new_price = floatval($_POST['new_price'] ?? 34.15);
                    
                    if ($new_price > 0) {
                        $update_sql = "UPDATE coins 
                                       SET current_price = ?, 
                                           price_source = 'admin_fix',
                                           last_update = NOW() 
                                       WHERE coin_kodu = 'USDS'";
                        $stmt = $conn->prepare($update_sql);
                        $stmt->execute([$new_price]);
                        
                        $affected = $stmt->rowCount();
                        if ($affected > 0) {
                            $messages[] = "✅ USDS fiyatı ₺{$new_price} olarak güncellendi! ({$affected} kayıt etkilendi)";
                        } else {
                            $errors[] = "❌ USDS coin bulunamadı veya güncelleme yapılamadı!";
                        }
                    } else {
                        $errors[] = "❌ Geçerli bir fiyat girin!";
                    }
                }
                
                if ($action === 'add_usds') {
                    $price = floatval($_POST['price'] ?? 34.15);
                    
                    $insert_sql = "INSERT INTO coins (coin_adi, coin_kodu, current_price, price_change_24h, price_source, is_active, created_at) 
                                   VALUES ('USDS', 'USDS', ?, 0, 'admin_add', 1, NOW())";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->execute([$price]);
                    
                    $messages[] = "✅ USDS coin eklendi (₺{$price})";
                }
            }
            
            // Mesajları göster
            foreach ($messages as $message) {
                echo "<div class='success-box'>{$message}</div>";
            }
            foreach ($errors as $error) {
                echo "<div class='error-box'>{$error}</div>";
            }
            
            // USDS coin bilgilerini kontrol et
            echo "<div class='section'>";
            echo "<h3>📊 USDS Coin Durumu</h3>";
            
            $sql = "SELECT id, coin_adi, coin_kodu, current_price, price_change_24h, price_source, last_update 
                    FROM coins 
                    WHERE coin_kodu = 'USDS' OR coin_adi LIKE '%USDS%'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $usds_coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($usds_coins)) {
                echo "<div class='error-box'>❌ USDS coin bulunamadı!</div>";
                echo "<form method='post'>";
                echo "<input type='hidden' name='action' value='add_usds'>";
                echo "<label>USDS Fiyatı (₺): <input type='number' name='price' value='34.15' step='0.01' min='0'></label>";
                echo "<button type='submit' class='btn'>USDS Coin Ekle</button>";
                echo "</form>";
            } else {
                echo "<table>";
                echo "<tr><th>ID</th><th>Adı</th><th>Kod</th><th>Fiyat</th><th>Değişim</th><th>Kaynak</th><th>Güncelleme</th><th>İşlemler</th></tr>";
                
                foreach ($usds_coins as $coin) {
                    $price_class = ($coin['current_price'] < 30 || $coin['current_price'] > 40) ? 'old-price' : 'new-price';
                    
                    echo "<tr>";
                    echo "<td>{$coin['id']}</td>";
                    echo "<td>{$coin['coin_adi']}</td>";
                    echo "<td><strong>{$coin['coin_kodu']}</strong></td>";
                    echo "<td class='{$price_class}'>₺" . number_format($coin['current_price'], 2) . "</td>";
                    echo "<td>{$coin['price_change_24h']}%</td>";
                    echo "<td>{$coin['price_source']}</td>";
                    echo "<td>{$coin['last_update']}</td>";
                    echo "<td>";
                    if (abs($coin['current_price'] - 34.15) > 1) {
                        echo "<form method='post' style='display:inline;'>";
                        echo "<input type='hidden' name='action' value='fix_usds_price'>";
                        echo "<input type='hidden' name='new_price' value='34.15'>";
                        echo "<button type='submit' class='btn btn-danger'>₺34.15'e Düzelt</button>";
                        echo "</form>";
                    } else {
                        echo "<span style='color: green;'>✅ Doğru</span>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Manuel fiyat düzeltme formu
                echo "<div class='info-box'>";
                echo "<h4>Manuel Fiyat Düzeltme</h4>";
                echo "<form method='post'>";
                echo "<input type='hidden' name='action' value='fix_usds_price'>";
                echo "<label>Yeni USDS Fiyatı (₺): <input type='number' name='new_price' value='34.15' step='0.01' min='0'></label>";
                echo "<button type='submit' class='btn'>Fiyatı Güncelle</button>";
                echo "</form>";
                echo "</div>";
            }
            echo "</div>";
            
            // Portföy kontrolü
            echo "<div class='section'>";
            echo "<h3>📈 USDS Portföy Durumu</h3>";
            
            $portfolio_sql = "SELECT p.user_id, u.username, p.coin_id, p.miktar, c.coin_kodu, c.current_price,
                                     (p.miktar * c.current_price) as toplam_deger
                              FROM portfolios p 
                              JOIN coins c ON p.coin_id = c.id 
                              LEFT JOIN users u ON p.user_id = u.id
                              WHERE c.coin_kodu = 'USDS' AND p.miktar > 0";
            $stmt = $conn->prepare($portfolio_sql);
            $stmt->execute();
            $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($portfolios)) {
                echo "<table>";
                echo "<tr><th>Kullanıcı</th><th>Miktar</th><th>Birim Fiyat</th><th>Toplam Değer</th></tr>";
                
                foreach ($portfolios as $portfolio) {
                    echo "<tr>";
                    echo "<td>{$portfolio['username']} (ID: {$portfolio['user_id']})</td>";
                    echo "<td>{$portfolio['miktar']} USDS</td>";
                    echo "<td class='price'>₺" . number_format($portfolio['current_price'], 2) . "</td>";
                    echo "<td class='price'>₺" . number_format($portfolio['toplam_deger'], 2) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='info-box'>ℹ️ Portföyde USDS bulunamadı</div>";
            }
            echo "</div>";
            
            // Trading işlemleri
            echo "<div class='section'>";
            echo "<h3>📊 Son USDS İşlemleri</h3>";
            
            $trading_sql = "SELECT t.*, u.username, c.coin_kodu, c.current_price as coin_price
                            FROM trading_islemleri t
                            JOIN coins c ON t.coin_id = c.id
                            LEFT JOIN users u ON t.user_id = u.id
                            WHERE c.coin_kodu = 'USDS'
                            ORDER BY t.islem_tarihi DESC
                            LIMIT 10";
            $stmt = $conn->prepare($trading_sql);
            $stmt->execute();
            $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($trades)) {
                echo "<table>";
                echo "<tr><th>Tarih</th><th>Kullanıcı</th><th>Tip</th><th>Miktar</th><th>İşlem Fiyatı</th><th>Toplam</th><th>Güncel Fiyat</th></tr>";
                
                foreach ($trades as $trade) {
                    $price_diff = $trade['coin_price'] - $trade['fiyat'];
                    $price_diff_class = $price_diff > 0 ? 'new-price' : ($price_diff < 0 ? 'old-price' : '');
                    
                    echo "<tr>";
                    echo "<td>" . date('d.m.Y H:i', strtotime($trade['islem_tarihi'])) . "</td>";
                    echo "<td>{$trade['username']}</td>";
                    echo "<td>{$trade['islem_tipi']}</td>";
                    echo "<td>{$trade['miktar']} USDS</td>";
                    echo "<td>₺" . number_format($trade['fiyat'], 2) . "</td>";
                    echo "<td>₺" . number_format($trade['toplam_tutar'], 2) . "</td>";
                    echo "<td class='{$price_diff_class}'>₺" . number_format($trade['coin_price'], 2) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='info-box'>ℹ️ USDS işlemi bulunamadı</div>";
            }
            echo "</div>";
            
            // Tüm coinlerin fiyat durumu
            echo "<div class='section'>";
            echo "<h3>💰 Tüm Coin Fiyatları</h3>";
            
            $all_coins_sql = "SELECT coin_adi, coin_kodu, current_price, price_change_24h, price_source, last_update 
                              FROM coins 
                              WHERE is_active = 1 
                              ORDER BY coin_kodu";
            $stmt = $conn->prepare($all_coins_sql);
            $stmt->execute();
            $all_coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>";
            echo "<tr><th>Coin</th><th>Kod</th><th>Fiyat</th><th>Değişim</th><th>Kaynak</th><th>Güncelleme</th></tr>";
            
            foreach ($all_coins as $coin) {
                $highlight = ($coin['coin_kodu'] === 'USDS') ? 'style="background: #fff3cd;"' : '';
                
                echo "<tr {$highlight}>";
                echo "<td>{$coin['coin_adi']}</td>";
                echo "<td><strong>{$coin['coin_kodu']}</strong></td>";
                echo "<td class='price'>₺" . number_format($coin['current_price'], 2) . "</td>";
                echo "<td>{$coin['price_change_24h']}%</td>";
                echo "<td>{$coin['price_source']}</td>";
                echo "<td>" . ($coin['last_update'] ? date('d.m.Y H:i', strtotime($coin['last_update'])) : '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error-box'>❌ Hata: " . $e->getMessage() . "</div>";
        }
        ?>
        
        <div class="info-box">
            <h4>📝 Sorun Analizi</h4>
            <p><strong>USDS Fiyat Tutarsızlığı Sebepleri:</strong></p>
            <ul>
                <li><strong>Admin Paneli:</strong> ₺1 - Veritabanında yanlış fiyat</li>
                <li><strong>Portföy:</strong> ₺27,623 - Eski işlem fiyatı ile hesaplama</li>
                <li><strong>Piyasa:</strong> ₺40,674 - API'den gelen güncel fiyat</li>
            </ul>
            <p><strong>Çözüm:</strong> USDS fiyatını doğru değere (₺34.15) güncellemek</p>
        </div>
        
        <div class="info-box">
            <h4>🔄 Otomatik Yenileme</h4>
            <button onclick="location.reload()" class="btn">Sayfayı Yenile</button>
            <button onclick="window.open('admin-panel.html', '_blank')" class="btn">Admin Paneli Aç</button>
        </div>
    </div>
</body>
</html>
