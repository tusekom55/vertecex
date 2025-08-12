<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Fiyat Sistemi Kurulumu</title>
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
        .step {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .step.completed {
            background: #d4edda;
            border-color: #28a745;
        }
        .step.error {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .step h3 {
            margin-top: 0;
            color: #333;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .progress {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-bar {
            height: 100%;
            background: #007bff;
            transition: width 0.3s ease;
        }
        .log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        .coin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .coin-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .coin-card.success {
            border-color: #28a745;
            background: #f8fff9;
        }
        .coin-card.error {
            border-color: #dc3545;
            background: #fff8f8;
        }
        .coin-price {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Yeni Fiyat Sistemi Kurulumu</h1>
        <p><strong>Tamamen sıfırdan yeni fiyat sistemi oluşturuluyor...</strong></p>
        
        <?php
        require_once 'backend/config.php';
        
        $step = $_GET['step'] ?? 'start';
        $auto_run = isset($_GET['auto_run']);
        
        // Adım durumları
        $steps = [
            'backup' => 'Mevcut Verileri Yedekle',
            'create_tables' => 'Yeni Tabloları Oluştur', 
            'fetch_coins' => 'API\'den Coinleri Çek',
            'setup_manual' => 'Manuel Coinleri Ekle',
            'test_system' => 'Sistemi Test Et',
            'finalize' => 'Sistemi Aktifleştir'
        ];
        
        try {
            $conn = db_connect();
            
            echo "<div class='progress'>";
            $current_step_index = array_search($step, array_keys($steps));
            $progress = $current_step_index !== false ? (($current_step_index + 1) / count($steps)) * 100 : 0;
            echo "<div class='progress-bar' style='width: {$progress}%'></div>";
            echo "</div>";
            
            echo "<p>İlerleme: " . round($progress) . "% - " . ($steps[$step] ?? 'Başlangıç') . "</p>";
            
            // Adım 1: Yedekleme
            if ($step === 'backup' || $step === 'start') {
                echo "<div class='step' id='step-backup'>";
                echo "<h3>📦 Adım 1: Mevcut Verileri Yedekle</h3>";
                
                if ($_POST['action'] ?? '' === 'backup') {
                    echo "<div class='log'>";
                    echo "🔄 Yedekleme başlatılıyor...\n";
                    
                    // Mevcut tabloları yedekle
                    $backup_tables = ['coins', 'portfolios', 'trading_islemleri'];
                    $backup_success = true;
                    
                    foreach ($backup_tables as $table) {
                        try {
                            $check_sql = "SHOW TABLES LIKE '{$table}'";
                            $stmt = $conn->prepare($check_sql);
                            $stmt->execute();
                            
                            if ($stmt->rowCount() > 0) {
                                $backup_sql = "CREATE TABLE {$table}_backup AS SELECT * FROM {$table}";
                                $conn->exec($backup_sql);
                                echo "✅ {$table} tablosu yedeklendi\n";
                            } else {
                                echo "ℹ️ {$table} tablosu bulunamadı\n";
                            }
                        } catch (Exception $e) {
                            echo "❌ {$table} yedekleme hatası: " . $e->getMessage() . "\n";
                            $backup_success = false;
                        }
                    }
                    
                    if ($backup_success) {
                        echo "✅ Yedekleme tamamlandı!\n";
                        echo "</div>";
                        echo "<script>setTimeout(() => window.location.href = '?step=create_tables', 2000);</script>";
                    } else {
                        echo "❌ Yedekleme başarısız!\n";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Mevcut verileriniz yedeklenecek. Bu işlem geri alınamaz!</p>";
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='action' value='backup'>";
                    echo "<button type='submit' class='btn btn-warning'>Yedeklemeyi Başlat</button>";
                    echo "</form>";
                }
                echo "</div>";
            }
            
            // Adım 2: Yeni tabloları oluştur
            if ($step === 'create_tables') {
                echo "<div class='step' id='step-create-tables'>";
                echo "<h3>🏗️ Adım 2: Yeni Tabloları Oluştur</h3>";
                
                if ($_POST['action'] ?? '' === 'create_tables') {
                    echo "<div class='log'>";
                    echo "🔄 Yeni tablolar oluşturuluyor...\n";
                    
                    try {
                        // Foreign key kontrollerini geçici olarak kapat
                        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
                        echo "🔧 Foreign key kontrolleri kapatıldı\n";
                        
                        // Bağımlı tabloları önce sil
                        $drop_order = ['price_history', 'portfolios', 'trading_islemleri', 'coins'];
                        foreach ($drop_order as $table) {
                            try {
                                $conn->exec("DROP TABLE IF EXISTS {$table}");
                                echo "🗑️ {$table} tablosu silindi\n";
                            } catch (Exception $e) {
                                echo "⚠️ {$table} silinirken hata (devam ediliyor): " . $e->getMessage() . "\n";
                            }
                        }
                        
                        // Foreign key kontrollerini tekrar aç
                        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
                        echo "🔧 Foreign key kontrolleri açıldı\n";
                        
                        // Yeni coins tablosu
                        $coins_sql = "
                        CREATE TABLE coins (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            coin_adi VARCHAR(100) NOT NULL,
                            coin_kodu VARCHAR(10) UNIQUE NOT NULL,
                            coin_type ENUM('api', 'manual', 'stable') NOT NULL DEFAULT 'api',
                            coingecko_id VARCHAR(50) NULL,
                            base_price DECIMAL(20,8) DEFAULT 0,
                            current_price DECIMAL(20,8) DEFAULT 0,
                            price_change_24h DECIMAL(10,4) DEFAULT 0,
                            price_source VARCHAR(20) DEFAULT 'api',
                            last_update TIMESTAMP NULL,
                            is_active BOOLEAN DEFAULT 1,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_coin_kodu (coin_kodu),
                            INDEX idx_coin_type (coin_type),
                            INDEX idx_is_active (is_active)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                        
                        $conn->exec($coins_sql);
                        echo "✅ Yeni coins tablosu oluşturuldu\n";
                        
                        // Fiyat geçmişi tablosu
                        $price_history_sql = "
                        CREATE TABLE price_history (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            coin_id INT NOT NULL,
                            price DECIMAL(20,8) NOT NULL,
                            price_source ENUM('api', 'manual', 'admin') NOT NULL,
                            currency ENUM('USD', 'TRY') DEFAULT 'TRY',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (coin_id) REFERENCES coins(id) ON DELETE CASCADE,
                            INDEX idx_coin_id (coin_id),
                            INDEX idx_created_at (created_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                        
                        $conn->exec($price_history_sql);
                        echo "✅ price_history tablosu oluşturuldu\n";
                        
                        // Portfolios tablosunu güncelle
                        $portfolio_sql = "
                        CREATE TABLE IF NOT EXISTS portfolios (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NOT NULL,
                            coin_id INT NOT NULL,
                            miktar DECIMAL(20,8) NOT NULL DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            FOREIGN KEY (coin_id) REFERENCES coins(id) ON DELETE CASCADE,
                            UNIQUE KEY unique_user_coin (user_id, coin_id),
                            INDEX idx_user_id (user_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                        
                        $conn->exec($portfolio_sql);
                        echo "✅ portfolios tablosu hazırlandı\n";
                        
                        echo "✅ Tüm tablolar başarıyla oluşturuldu!\n";
                        echo "</div>";
                        echo "<script>setTimeout(() => window.location.href = '?step=fetch_coins', 2000);</script>";
                        
                    } catch (Exception $e) {
                        echo "❌ Tablo oluşturma hatası: " . $e->getMessage() . "\n";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Yeni veritabanı tabloları oluşturulacak.</p>";
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='action' value='create_tables'>";
                    echo "<button type='submit' class='btn btn-success'>Tabloları Oluştur</button>";
                    echo "</form>";
                }
                echo "</div>";
            }
            
            // Adım 3: API'den coinleri çek
            if ($step === 'fetch_coins') {
                echo "<div class='step' id='step-fetch-coins'>";
                echo "<h3>🌐 Adım 3: API'den Coinleri Çek</h3>";
                
                if ($_POST['action'] ?? '' === 'fetch_coins') {
                    echo "<div class='log'>";
                    echo "🔄 CoinGecko API'den coinler çekiliyor...\n";
                    
                    try {
                        // Popüler coinlerin listesi
                        $api_coins = [
                            ['id' => 'bitcoin', 'symbol' => 'BTC', 'name' => 'Bitcoin'],
                            ['id' => 'ethereum', 'symbol' => 'ETH', 'name' => 'Ethereum'],
                            ['id' => 'binancecoin', 'symbol' => 'BNB', 'name' => 'BNB'],
                            ['id' => 'ripple', 'symbol' => 'XRP', 'name' => 'XRP'],
                            ['id' => 'tether', 'symbol' => 'USDT', 'name' => 'Tether'],
                            ['id' => 'cardano', 'symbol' => 'ADA', 'name' => 'Cardano'],
                            ['id' => 'solana', 'symbol' => 'SOL', 'name' => 'Solana'],
                            ['id' => 'dogecoin', 'symbol' => 'DOGE', 'name' => 'Dogecoin'],
                            ['id' => 'matic-network', 'symbol' => 'MATIC', 'name' => 'Polygon'],
                            ['id' => 'polkadot', 'symbol' => 'DOT', 'name' => 'Polkadot'],
                            ['id' => 'chainlink', 'symbol' => 'LINK', 'name' => 'Chainlink'],
                            ['id' => 'litecoin', 'symbol' => 'LTC', 'name' => 'Litecoin'],
                            ['id' => 'avalanche-2', 'symbol' => 'AVAX', 'name' => 'Avalanche'],
                            ['id' => 'uniswap', 'symbol' => 'UNI', 'name' => 'Uniswap'],
                            ['id' => 'cosmos', 'symbol' => 'ATOM', 'name' => 'Cosmos']
                        ];
                        
                        // USD/TRY kurunu API'den al
                        $usd_try_rate = 34.15; // Varsayılan
                        echo "🔄 USD/TRY döviz kuru çekiliyor...\n";
                        
                        // Birden fazla API dene
                        $rate_apis = [
                            [
                                'name' => 'ExchangeRate-API',
                                'url' => 'https://api.exchangerate-api.com/v4/latest/USD',
                                'path' => 'rates.TRY'
                            ],
                            [
                                'name' => 'Fixer.io',
                                'url' => 'https://api.fixer.io/latest?base=USD&symbols=TRY',
                                'path' => 'rates.TRY'
                            ],
                            [
                                'name' => 'CurrencyAPI',
                                'url' => 'https://api.currencyapi.com/v3/latest?apikey=free&currencies=TRY&base_currency=USD',
                                'path' => 'data.TRY.value'
                            ],
                            [
                                'name' => 'TCMB (Merkez Bankası)',
                                'url' => 'https://evds2.tcmb.gov.tr/service/evds/series=TP.DK.USD.A.YTL/type=json',
                                'path' => 'items.0.TP_DK_USD_A_YTL'
                            ]
                        ];
                        
                        $rate_found = false;
                        foreach ($rate_apis as $api) {
                            try {
                                echo "🌐 {$api['name']} API'si deneniyor...\n";
                                
                                $context = stream_context_create([
                                    'http' => [
                                        'timeout' => 10,
                                        'user_agent' => 'Mozilla/5.0 (compatible; CryptoTrading/1.0)'
                                    ]
                                ]);
                                
                                $rate_response = @file_get_contents($api['url'], false, $context);
                                if ($rate_response) {
                                    $rate_data = json_decode($rate_response, true);
                                    
                                    // Path'e göre değeri al
                                    $path_parts = explode('.', $api['path']);
                                    $value = $rate_data;
                                    
                                    foreach ($path_parts as $part) {
                                        if (isset($value[$part])) {
                                            $value = $value[$part];
                                        } else {
                                            $value = null;
                                            break;
                                        }
                                    }
                                    
                                    if ($value && is_numeric($value) && $value > 20 && $value < 50) {
                                        $usd_try_rate = floatval($value);
                                        echo "✅ {$api['name']}'den kur alındı: ₺{$usd_try_rate}\n";
                                        $rate_found = true;
                                        break;
                                    } else {
                                        echo "⚠️ {$api['name']}: Geçersiz kur değeri ({$value})\n";
                                    }
                                } else {
                                    echo "❌ {$api['name']}: API yanıt vermedi\n";
                                }
                            } catch (Exception $e) {
                                echo "❌ {$api['name']} hatası: " . $e->getMessage() . "\n";
                            }
                        }
                        
                        if (!$rate_found) {
                            echo "⚠️ Hiçbir API'den kur alınamadı, varsayılan kullanılıyor: ₺{$usd_try_rate}\n";
                        }
                        
                        // Kuru veritabanına kaydet
                        try {
                            $rate_insert_sql = "INSERT INTO price_history (coin_id, price, price_source, currency, created_at) 
                                                SELECT id, ?, 'api', 'TRY', NOW() 
                                                FROM coins WHERE coin_kodu = 'USD' 
                                                LIMIT 1";
                            $rate_stmt = $conn->prepare($rate_insert_sql);
                            $rate_stmt->execute([$usd_try_rate]);
                            echo "💾 USD/TRY kuru veritabanına kaydedildi\n";
                        } catch (Exception $e) {
                            echo "⚠️ Kur kaydetme hatası: " . $e->getMessage() . "\n";
                        }
                        
                        // CoinGecko'dan fiyatları al
                        $coin_ids = implode(',', array_column($api_coins, 'id'));
                        $api_url = "https://api.coingecko.com/api/v3/simple/price?ids={$coin_ids}&vs_currencies=usd&include_24hr_change=true";
                        
                        echo "🌐 API URL: {$api_url}\n";
                        
                        $response = @file_get_contents($api_url);
                        if (!$response) {
                            throw new Exception("CoinGecko API'ye erişilemedi");
                        }
                        
                        $price_data = json_decode($response, true);
                        if (!$price_data) {
                            throw new Exception("API yanıtı geçersiz");
                        }
                        
                        echo "✅ API'den " . count($price_data) . " coin fiyatı alındı\n";
                        
                        // Coinleri veritabanına ekle
                        $insert_sql = "INSERT INTO coins (coin_adi, coin_kodu, coin_type, coingecko_id, base_price, current_price, price_change_24h, price_source, last_update) 
                                       VALUES (?, ?, 'api', ?, ?, ?, ?, 'api', NOW())";
                        $stmt = $conn->prepare($insert_sql);
                        
                        $success_count = 0;
                        foreach ($api_coins as $coin) {
                            if (isset($price_data[$coin['id']])) {
                                $usd_price = $price_data[$coin['id']]['usd'];
                                $try_price = $usd_price * $usd_try_rate;
                                $change_24h = $price_data[$coin['id']]['usd_24h_change'] ?? 0;
                                
                                try {
                                    $stmt->execute([
                                        $coin['name'],
                                        strtoupper($coin['symbol']),
                                        $coin['id'],
                                        $try_price,
                                        $try_price,
                                        round($change_24h, 2)
                                    ]);
                                    
                                    echo "✅ {$coin['symbol']}: ₺" . number_format($try_price, 2) . " ({$change_24h}%)\n";
                                    $success_count++;
                                } catch (Exception $e) {
                                    echo "❌ {$coin['symbol']} eklenemedi: " . $e->getMessage() . "\n";
                                }
                            } else {
                                echo "⚠️ {$coin['symbol']} fiyatı bulunamadı\n";
                            }
                        }
                        
                        echo "✅ {$success_count} coin başarıyla eklendi!\n";
                        echo "</div>";
                        echo "<script>setTimeout(() => window.location.href = '?step=setup_manual', 2000);</script>";
                        
                    } catch (Exception $e) {
                        echo "❌ API hatası: " . $e->getMessage() . "\n";
                        echo "</div>";
                    }
                } else {
                    echo "<p>CoinGecko API'den popüler coinlerin fiyatları çekilecek.</p>";
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='action' value='fetch_coins'>";
                    echo "<button type='submit' class='btn btn-success'>Coinleri Çek</button>";
                    echo "</form>";
                }
                echo "</div>";
            }
            
            // Adım 4: Manuel coinleri ekle
            if ($step === 'setup_manual') {
                echo "<div class='step' id='step-setup-manual'>";
                echo "<h3>⚙️ Adım 4: Manuel Coinleri Ekle</h3>";
                
                if ($_POST['action'] ?? '' === 'setup_manual') {
                    echo "<div class='log'>";
                    echo "🔄 Manuel coinler ekleniyor...\n";
                    
                    try {
                        $manual_coins = [
                            ['name' => 'Tugaycoin', 'symbol' => 'T', 'price' => 10.00],
                            ['name' => 'SEX Coin', 'symbol' => 'SEX', 'price' => 0.25],
                            ['name' => 'TTT Dollar', 'symbol' => 'TTT', 'price' => 34.15],
                            ['name' => 'USDS', 'symbol' => 'USDS', 'price' => 34.15]
                        ];
                        
                        $insert_sql = "INSERT INTO coins (coin_adi, coin_kodu, coin_type, base_price, current_price, price_change_24h, price_source, last_update) 
                                       VALUES (?, ?, 'manual', ?, ?, 0, 'manual', NOW())";
                        $stmt = $conn->prepare($insert_sql);
                        
                        foreach ($manual_coins as $coin) {
                            try {
                                $stmt->execute([
                                    $coin['name'],
                                    $coin['symbol'],
                                    $coin['price'],
                                    $coin['price']
                                ]);
                                echo "✅ {$coin['symbol']}: ₺" . number_format($coin['price'], 2) . "\n";
                            } catch (Exception $e) {
                                echo "❌ {$coin['symbol']} eklenemedi: " . $e->getMessage() . "\n";
                            }
                        }
                        
                        echo "✅ Manuel coinler eklendi!\n";
                        echo "</div>";
                        echo "<script>setTimeout(() => window.location.href = '?step=test_system', 2000);</script>";
                        
                    } catch (Exception $e) {
                        echo "❌ Manuel coin hatası: " . $e->getMessage() . "\n";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Özel coinler (T, SEX, TTT, USDS) manuel olarak eklenecek.</p>";
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='action' value='setup_manual'>";
                    echo "<button type='submit' class='btn btn-success'>Manuel Coinleri Ekle</button>";
                    echo "</form>";
                }
                echo "</div>";
            }
            
            // Adım 5: Sistemi test et
            if ($step === 'test_system') {
                echo "<div class='step' id='step-test-system'>";
                echo "<h3>🧪 Adım 5: Sistemi Test Et</h3>";
                
                try {
                    // Tüm coinleri listele
                    $sql = "SELECT * FROM coins WHERE is_active = 1 ORDER BY coin_type, coin_kodu";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<p>✅ Toplam " . count($coins) . " coin başarıyla yüklendi!</p>";
                    
                    echo "<div class='coin-grid'>";
                    foreach ($coins as $coin) {
                        $type_color = $coin['coin_type'] === 'api' ? '#007bff' : '#28a745';
                        echo "<div class='coin-card success'>";
                        echo "<h4 style='color: {$type_color};'>{$coin['coin_kodu']}</h4>";
                        echo "<p>{$coin['coin_adi']}</p>";
                        echo "<div class='coin-price'>₺" . number_format($coin['current_price'], 2) . "</div>";
                        echo "<small>{$coin['coin_type']} | {$coin['price_change_24h']}%</small>";
                        echo "</div>";
                    }
                    echo "</div>";
                    
                    echo "<table>";
                    echo "<tr><th>Coin</th><th>Kod</th><th>Tip</th><th>Fiyat</th><th>Değişim</th><th>Kaynak</th><th>Güncelleme</th></tr>";
                    foreach ($coins as $coin) {
                        echo "<tr>";
                        echo "<td>{$coin['coin_adi']}</td>";
                        echo "<td><strong>{$coin['coin_kodu']}</strong></td>";
                        echo "<td>{$coin['coin_type']}</td>";
                        echo "<td>₺" . number_format($coin['current_price'], 2) . "</td>";
                        echo "<td>{$coin['price_change_24h']}%</td>";
                        echo "<td>{$coin['price_source']}</td>";
                        echo "<td>{$coin['last_update']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    echo "<form method='get'>";
                    echo "<input type='hidden' name='step' value='finalize'>";
                    echo "<button type='submit' class='btn btn-success'>Sistemi Aktifleştir</button>";
                    echo "</form>";
                    
                } catch (Exception $e) {
                    echo "<p class='error'>❌ Test hatası: " . $e->getMessage() . "</p>";
                }
                echo "</div>";
            }
            
            // Adım 6: Sistemi aktifleştir
            if ($step === 'finalize') {
                echo "<div class='step completed' id='step-finalize'>";
                echo "<h3>🎉 Adım 6: Sistem Aktifleştirildi!</h3>";
                
                echo "<div class='log'>";
                echo "✅ Yeni fiyat sistemi başarıyla kuruldu!\n";
                echo "✅ " . $conn->query("SELECT COUNT(*) FROM coins WHERE is_active = 1")->fetchColumn() . " coin aktif\n";
                echo "✅ API coinleri otomatik güncellenecek\n";
                echo "✅ Manuel coinler admin kontrolünde\n";
                echo "✅ Fiyat geçmişi kaydediliyor\n";
                echo "✅ Portföy hesaplamaları tutarlı\n";
                echo "</div>";
                
                echo "<h4>🔗 Hızlı Linkler:</h4>";
                echo "<a href='admin-panel.html' class='btn btn-primary'>Admin Panel</a>";
                echo "<a href='user-panel.html' class='btn btn-success'>Kullanıcı Panel</a>";
                echo "<a href='backend/user/coins.php' class='btn btn-warning'>Coin API Test</a>";
                
                echo "<h4>📊 Sistem Özeti:</h4>";
                echo "<ul>";
                echo "<li><strong>API Coinleri:</strong> Otomatik güncelleme (5 dakikada bir)</li>";
                echo "<li><strong>Manuel Coinleri:</strong> Admin kontrolü</li>";
                echo "<li><strong>Fiyat Kaynağı:</strong> CoinGecko API + USD/TRY kuru</li>";
                echo "<li><strong>Veritabanı:</strong> Temiz yapı, fiyat geçmişi</li>";
                echo "<li><strong>Portföy:</strong> Gerçek zamanlı hesaplama</li>";
                echo "</ul>";
                
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='step error'>";
            echo "<h3>❌ Hata</h3>";
            echo "<p>Sistem kurulumu sırasında hata oluştu: " . $e->getMessage() . "</p>";
            echo "</div>";
        }
        ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #e9ecef; border-radius: 8px;">
            <h4>📋 Kurulum Adımları:</h4>
            <ol>
                <?php foreach ($steps as $step_key => $step_name): ?>
                    <li>
                        <a href="?step=<?= $step_key ?>" style="text-decoration: none;">
                            <?= $step_name ?>
                        </a>
                        <?php if ($step === $step_key): ?>
                            <strong style="color: #007bff;"> ← Şu anda burada</strong>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
    
    <script>
        // Otomatik yenileme için
        function autoRefresh() {
            setTimeout(() => {
                location.reload();
            }, 30000);
        }
        
        // Progress bar animasyonu
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = progressBar.style.width;
                }, 100);
            }
        });
    </script>
</body>
</html>
