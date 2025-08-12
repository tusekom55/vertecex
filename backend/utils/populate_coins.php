<?php
// Debug için error reporting aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

// CoinGecko'dan popüler coinleri çek ve veritabanına kaydet
function populateCoinsFromAPI() {
    $conn = db_connect();
    
    // Önce şema kontrolü yap
    if (!checkSchema($conn)) {
        echo "❌ Veritabanı şeması güncel değil!\n";
        echo "📋 Lütfen önce 'update_schema.sql' dosyasını phpMyAdmin'de çalıştırın.\n\n";
        return;
    }
    
    // Önce kategorilerin ID'lerini alalım
    $categories = [
        'Major Coins' => 1,
        'Altcoins' => 2,
        'DeFi' => 3,
        'NFT' => 4,
        'Meme Coins' => 5,
        'Stablecoins' => 6
    ];
    
    try {
        echo "🚀 CoinGecko API'den coin verileri çekiliyor...\n\n";
        
        // CoinGecko'dan top 100 coin çek
        $url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=100&page=1&sparkline=false';
        
        // cURL kullanarak daha güvenilir API çağrısı
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TradePro/1.0 (PHP)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL sorunları için
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($response === FALSE || !empty($curl_error)) {
            throw new Exception('CoinGecko API cURL hatası: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            throw new Exception('CoinGecko API HTTP hatası: ' . $http_code);
        }
        
        $coins = json_decode($response, true);
        
        if (!$coins || !is_array($coins)) {
            throw new Exception('API\'den geçersiz veri');
        }
        
        $success_count = 0;
        $skip_count = 0;
        
        foreach ($coins as $index => $coin) {
            // Coin kategorisini belirle
            $kategori_id = getCoinCategory($coin['name'], $coin['symbol']);
            
            // Veritabanında mevcut mu kontrol et
            $check_stmt = $conn->prepare('SELECT id FROM coins WHERE coingecko_id = ?');
            $check_stmt->bind_param('s', $coin['id']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo "⏭️  {$coin['name']} zaten mevcut, atlanıyor...\n";
                $skip_count++;
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Coin'i ekle
            $stmt = $conn->prepare('
                INSERT INTO coins (
                    kategori_id, coingecko_id, coin_adi, coin_kodu, logo_url, 
                    current_price, price_change_24h, market_cap, api_aktif, 
                    is_active, sira
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $sira = $index + 1;
            $api_aktif = false; // Default olarak API kapalı
            $is_active = true;
            
            $stmt->bind_param(
                'issssdiiiii',
                $kategori_id,
                $coin['id'], // coingecko_id
                $coin['name'],
                strtoupper($coin['symbol']),
                $coin['image'],
                $coin['current_price'],
                $coin['price_change_percentage_24h'],
                $coin['market_cap'],
                $api_aktif,
                $is_active,
                $sira
            );
            
            if ($stmt->execute()) {
                echo "✅ {$coin['name']} ({$coin['symbol']}) eklendi - Sıra: {$sira}\n";
                $success_count++;
            } else {
                echo "❌ {$coin['name']} eklenirken hata: " . $stmt->error . "\n";
            }
            
            $stmt->close();
        }
        
        echo "\n🎉 İşlem tamamlandı!\n";
        echo "✅ Başarılı: {$success_count} coin\n";
        echo "⏭️  Atlanan: {$skip_count} coin\n\n";
        
        // İstatistikleri göster
        showStats($conn);
        
    } catch (Exception $e) {
        echo "❌ Hata: " . $e->getMessage() . "\n";
        echo "\n🔄 API erişimi başarısız. Manuel coin ekleme yapılıyor...\n\n";
        
        // Manuel coin ekleme - API olmadan temel coinler
        addManualCoins($conn);
    } finally {
        $conn->close();
    }
}

// Şema kontrolü - gerekli kolonların varlığını kontrol et
function checkSchema($conn) {
    $query = "SHOW COLUMNS FROM coins LIKE 'coingecko_id'";
    $result = $conn->query($query);
    return $result->num_rows > 0;
}

// Coin kategorisini belirle (basit logic)
function getCoinCategory($name, $symbol) {
    $name_lower = strtolower($name);
    $symbol_lower = strtolower($symbol);
    
    // Major Coins
    if (in_array($symbol_lower, ['btc', 'eth', 'bnb', 'xrp', 'ada', 'sol', 'dot', 'avax'])) {
        return 1;
    }
    
    // Stablecoins
    if (in_array($symbol_lower, ['usdt', 'usdc', 'busd', 'dai', 'tusd', 'usdd'])) {
        return 6;
    }
    
    // Meme Coins
    if (strpos($name_lower, 'doge') !== false || strpos($name_lower, 'shib') !== false || 
        strpos($name_lower, 'meme') !== false || in_array($symbol_lower, ['doge', 'shib', 'pepe'])) {
        return 5;
    }
    
    // DeFi
    if (strpos($name_lower, 'defi') !== false || strpos($name_lower, 'swap') !== false ||
        in_array($symbol_lower, ['uni', 'cake', 'aave', 'comp', 'sushi', 'crv'])) {
        return 3;
    }
    
    // Default: Altcoins
    return 2;
}

// İstatistikleri göster
function showStats($conn) {
    echo "📊 COIN İSTATİSTİKLERİ:\n";
    echo "=" . str_repeat("=", 30) . "\n";
    
    $stmt = $conn->prepare('
        SELECT 
            ck.kategori_adi,
            COUNT(c.id) as coin_sayisi,
            SUM(CASE WHEN c.api_aktif = 1 THEN 1 ELSE 0 END) as api_aktif_sayisi
        FROM coin_kategorileri ck
        LEFT JOIN coins c ON ck.id = c.kategori_id
        GROUP BY ck.id, ck.kategori_adi
        ORDER BY coin_sayisi DESC
    ');
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        echo sprintf("📂 %-15s: %2d coin (API: %d)\n", 
            $row['kategori_adi'], 
            $row['coin_sayisi'], 
            $row['api_aktif_sayisi']
        );
    }
    
    $stmt->close();
    
    // Toplam istatistik
    $total_stmt = $conn->prepare('SELECT COUNT(*) as total FROM coins');
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total = $total_result->fetch_assoc()['total'];
    $total_stmt->close();
    
    echo "\n💰 TOPLAM COIN SAYISI: {$total}\n";
    echo "🔴 API DURUMU: KAPALI (Tüm fiyatlar sabit)\n\n";
}

// Manuel coin ekleme - API olmadan
function addManualCoins($conn) {
    // Şema kontrolü
    if (!checkSchema($conn)) {
        echo "❌ Veritabanı şeması güncel değil! Manuel ekleme yapılamıyor.\n";
        echo "📋 Lütfen önce 'update_schema.sql' dosyasını phpMyAdmin'de çalıştırın.\n\n";
        return;
    }
    $manual_coins = [
        // Major Coins
        ['bitcoin', 'Bitcoin', 'BTC', 'https://assets.coingecko.com/coins/images/1/small/bitcoin.png', 45000, 2.5, 850000000000, 1],
        ['ethereum', 'Ethereum', 'ETH', 'https://assets.coingecko.com/coins/images/279/small/ethereum.png', 2800, 1.8, 340000000000, 1],
        ['binancecoin', 'BNB', 'BNB', 'https://assets.coingecko.com/coins/images/825/small/bnb-icon2_2x.png', 420, -1.2, 65000000000, 1],
        ['ripple', 'XRP', 'XRP', 'https://assets.coingecko.com/coins/images/44/small/xrp-symbol-white-128.png', 0.65, 3.4, 36000000000, 1],
        ['cardano', 'Cardano', 'ADA', 'https://assets.coingecko.com/coins/images/975/small/cardano.png', 0.45, -0.8, 16000000000, 1],
        ['solana', 'Solana', 'SOL', 'https://assets.coingecko.com/coins/images/4128/small/solana.png', 95, 4.2, 42000000000, 1],
        
        // Altcoins  
        ['polkadot', 'Polkadot', 'DOT', 'https://assets.coingecko.com/coins/images/12171/small/polkadot.png', 7.5, -2.1, 9500000000, 2],
        ['chainlink', 'Chainlink', 'LINK', 'https://assets.coingecko.com/coins/images/877/small/chainlink-new-logo.png', 12.8, 1.9, 7200000000, 2],
        ['litecoin', 'Litecoin', 'LTC', 'https://assets.coingecko.com/coins/images/2/small/litecoin.png', 95, -1.5, 7000000000, 2],
        ['avalanche-2', 'Avalanche', 'AVAX', 'https://assets.coingecko.com/coins/images/12559/small/Avalanche_Circle_RedWhite_Trans.png', 28, 2.8, 11000000000, 2],
        
        // Stablecoins
        ['tether', 'Tether', 'USDT', 'https://assets.coingecko.com/coins/images/325/small/Tether.png', 1.0, 0.01, 83000000000, 6],
        ['usd-coin', 'USD Coin', 'USDC', 'https://assets.coingecko.com/coins/images/6319/small/USD_Coin_icon.png', 1.0, 0.02, 28000000000, 6],
        
        // Meme Coins
        ['dogecoin', 'Dogecoin', 'DOGE', 'https://assets.coingecko.com/coins/images/5/small/dogecoin.png', 0.085, 5.6, 12000000000, 5],
        ['shiba-inu', 'Shiba Inu', 'SHIB', 'https://assets.coingecko.com/coins/images/11939/small/shiba.png', 0.000009, -3.2, 5300000000, 5],
        
        // DeFi
        ['uniswap', 'Uniswap', 'UNI', 'https://assets.coingecko.com/coins/images/12504/small/uni.jpg', 6.8, 2.1, 5100000000, 3],
        ['pancakeswap-token', 'PancakeSwap', 'CAKE', 'https://assets.coingecko.com/coins/images/12632/small/pancakeswap-cake-logo_.png', 2.4, -1.8, 750000000, 3]
    ];
    
    $success_count = 0;
    
    foreach ($manual_coins as $index => $coin_data) {
        list($coingecko_id, $name, $symbol, $logo, $price, $change, $market_cap, $kategori_id) = $coin_data;
        
        // Mevcut kontrolü
        $check_stmt = $conn->prepare('SELECT id FROM coins WHERE coingecko_id = ?');
        $check_stmt->bind_param('s', $coingecko_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "⏭️  {$name} zaten mevcut, atlanıyor...\n";
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        // Coin ekle
        $stmt = $conn->prepare('
            INSERT INTO coins (
                kategori_id, coingecko_id, coin_adi, coin_kodu, logo_url, 
                current_price, price_change_24h, market_cap, api_aktif, 
                is_active, sira
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $sira = $index + 1;
        $api_aktif = false; // Default kapalı
        $is_active = true;
        
        $stmt->bind_param(
            'issssdiiiii',
            $kategori_id,
            $coingecko_id,
            $name,
            $symbol,
            $logo,
            $price,
            $change,
            $market_cap,
            $api_aktif,
            $is_active,
            $sira
        );
        
        if ($stmt->execute()) {
            echo "✅ {$name} ({$symbol}) manuel eklendi - Fiyat: \${$price}\n";
            $success_count++;
        } else {
            echo "❌ {$name} eklenirken hata: " . $stmt->error . "\n";
        }
        
        $stmt->close();
    }
    
    echo "\n🎉 Manuel ekleme tamamlandı: {$success_count} coin\n\n";
    showStats($conn);
}

// Script çalıştırılırsa
if (php_sapi_name() === 'cli' || basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    echo "🪙 COIN VERİTABANI POPULATE İŞLEMİ\n";
    echo "=" . str_repeat("=", 40) . "\n\n";
    
    try {
        populateCoinsFromAPI();
    } catch (Exception $e) {
        echo "❌ Genel Hata: " . $e->getMessage() . "\n";
        echo "📍 Dosya: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}
?> 