<?php
require_once 'config.php';

// Test pozisyonları oluştur
function createTestPositions() {
    global $conn;
    
    // Önce mevcut test pozisyonlarını temizle
    $conn->query("DELETE FROM leverage_positions WHERE user_id IN (2, 3, 4)");
    
    // Test pozisyonları ekle
    $testPositions = [
        [
            'user_id' => 2,
            'coin_symbol' => 'BTC',
            'position_type' => 'long',
            'leverage_ratio' => 2.0,
            'entry_price' => 1350000.00,
            'position_size' => 0.001,
            'invested_amount' => 675.00,
            'current_price' => 1350000.00,
            'unrealized_pnl' => 0.00,
            'liquidation_price' => 675000.00
        ],
        [
            'user_id' => 2,
            'coin_symbol' => 'ETH',
            'position_type' => 'short',
            'leverage_ratio' => 3.0,
            'entry_price' => 85000.00,
            'position_size' => 0.01,
            'invested_amount' => 283.33,
            'current_price' => 85000.00,
            'unrealized_pnl' => 0.00,
            'liquidation_price' => 113333.33
        ],
        [
            'user_id' => 3,
            'coin_symbol' => 'BNB',
            'position_type' => 'long',
            'leverage_ratio' => 1.5,
            'entry_price' => 4500.00,
            'position_size' => 0.1,
            'invested_amount' => 300.00,
            'current_price' => 4500.00,
            'unrealized_pnl' => 0.00,
            'liquidation_price' => 3000.00
        ],
        [
            'user_id' => 4,
            'coin_symbol' => 'ADA',
            'position_type' => 'long',
            'leverage_ratio' => 5.0,
            'entry_price' => 2.50,
            'position_size' => 100,
            'invested_amount' => 50.00,
            'current_price' => 2.50,
            'unrealized_pnl' => 0.00,
            'liquidation_price' => 2.00
        ]
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO leverage_positions 
        (user_id, coin_symbol, position_type, leverage_ratio, entry_price, position_size, invested_amount, current_price, unrealized_pnl, status, liquidation_price, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW())
    ");
    
    foreach ($testPositions as $position) {
        $user_id = $position['user_id'];
        $coin_symbol = $position['coin_symbol'];
        $position_type = $position['position_type'];
        $leverage_ratio = $position['leverage_ratio'];
        $entry_price = $position['entry_price'];
        $position_size = $position['position_size'];
        $invested_amount = $position['invested_amount'];
        $current_price = $position['current_price'];
        $unrealized_pnl = $position['unrealized_pnl'];
        $liquidation_price = $position['liquidation_price'];
        
        $stmt->bind_param("issdddddd", 
            $user_id,
            $coin_symbol,
            $position_type,
            $leverage_ratio,
            $entry_price,
            $position_size,
            $invested_amount,
            $current_price,
            $unrealized_pnl,
            $liquidation_price
        );
        $stmt->execute();
    }
    
    echo "Test pozisyonları oluşturuldu!\n";
    echo "user1 (ID: 2) - BTC ve ETH pozisyonları\n";
    echo "user2 (ID: 3) - BNB pozisyonu\n";
    echo "user3 (ID: 4) - ADA pozisyonu\n";
}

// Test coins verisi ekle
function createTestCoins() {
    global $conn;
    
    // Mevcut test coins'leri temizle
    $conn->query("DELETE FROM coins WHERE coin_kodu IN ('BTC', 'ETH', 'BNB', 'ADA')");
    
    $testCoins = [
        ['bitcoin', 'Bitcoin', 'BTC', 1350000.00, 2.5, 2500000000000],
        ['ethereum', 'Ethereum', 'ETH', 85000.00, -1.2, 1000000000000],
        ['binancecoin', 'Binance Coin', 'BNB', 4500.00, 0.8, 75000000000],
        ['cardano', 'Cardano', 'ADA', 2.50, 1.5, 35000000000]
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO coins (coingecko_id, coin_adi, coin_kodu, current_price, price_change_24h, market_cap, sira)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($testCoins as $index => $coin) {
        $coingecko_id = $coin[0];
        $coin_adi = $coin[1];
        $coin_kodu = $coin[2];
        $current_price = $coin[3];
        $price_change_24h = $coin[4];
        $market_cap = $coin[5];
        $sira = $index + 1;
        
        $stmt->bind_param("ssdddi", 
            $coingecko_id, $coin_adi, $coin_kodu, $current_price, $price_change_24h, $market_cap, $sira
        );
        $stmt->execute();
    }
    
    echo "Test coins verisi eklendi!\n";
}

// Ana fonksiyon
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        createTestCoins();
        createTestPositions();
        echo "✅ Tüm test verisi başarıyla oluşturuldu!\n";
        echo "Şişli kullanıcıları ile test edebilirsiniz:\n";
        echo "- user1@example.com (şifre: password)\n";
        echo "- user2@example.com (şifre: password)\n";
        echo "- user3@example.com (şifre: password)\n";
    } catch (Exception $e) {
        echo "❌ Hata: " . $e->getMessage() . "\n";
    }
}
?> 