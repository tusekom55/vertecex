<?php
// Coin logo sistemini düzelt
require_once 'backend/config.php';

try {
    $conn = db_connect();
    
    echo "🔧 Coin Logo Sistemi Düzeltiliyor...\n\n";
    
    // 1. Logo kolonu var mı kontrol et
    $check_column = $conn->query("SHOW COLUMNS FROM coins LIKE 'logo_url'");
    
    if ($check_column->rowCount() == 0) {
        echo "📝 Logo kolonu ekleniyor...\n";
        $conn->exec("ALTER TABLE coins ADD COLUMN logo_url VARCHAR(500) NULL AFTER coin_kodu");
        echo "✅ Logo kolonu eklendi\n\n";
    } else {
        echo "✅ Logo kolonu zaten mevcut\n\n";
    }
    
    // 2. Popüler coinler için logo URL'lerini güncelle
    $coin_logos = [
        'BTC' => 'https://assets.coingecko.com/coins/images/1/large/bitcoin.png',
        'ETH' => 'https://assets.coingecko.com/coins/images/279/large/ethereum.png',
        'BNB' => 'https://assets.coingecko.com/coins/images/825/large/bnb-icon2_2x.png',
        'ADA' => 'https://assets.coingecko.com/coins/images/975/large/cardano.png',
        'SOL' => 'https://assets.coingecko.com/coins/images/4128/large/solana.png',
        'XRP' => 'https://assets.coingecko.com/coins/images/44/large/xrp-symbol-white-128.png',
        'DOT' => 'https://assets.coingecko.com/coins/images/12171/large/polkadot.png',
        'DOGE' => 'https://assets.coingecko.com/coins/images/5/large/dogecoin.png',
        'AVAX' => 'https://assets.coingecko.com/coins/images/12559/large/Avalanche_Circle_RedWhite_Trans.png',
        'SHIB' => 'https://assets.coingecko.com/coins/images/11939/large/shiba.png',
        'LINK' => 'https://assets.coingecko.com/coins/images/877/large/chainlink-new-logo.png',
        'TRX' => 'https://assets.coingecko.com/coins/images/1094/large/tron-logo.png',
        'MATIC' => 'https://assets.coingecko.com/coins/images/4713/large/matic-token-icon.png',
        'LTC' => 'https://assets.coingecko.com/coins/images/2/large/litecoin.png',
        'BCH' => 'https://assets.coingecko.com/coins/images/780/large/bitcoin-cash-circle.png',
        'UNI' => 'https://assets.coingecko.com/coins/images/12504/large/uniswap-uni.png',
        'ATOM' => 'https://assets.coingecko.com/coins/images/1481/large/cosmos_hub.png',
        'XLM' => 'https://assets.coingecko.com/coins/images/100/large/Stellar_symbol_black_RGB.png',
        'VET' => 'https://assets.coingecko.com/coins/images/1167/large/VeChain-Logo-768x725.png',
        'FIL' => 'https://assets.coingecko.com/coins/images/12817/large/filecoin.png',
        'THETA' => 'https://assets.coingecko.com/coins/images/2538/large/theta-token-logo.png',
        'ICP' => 'https://assets.coingecko.com/coins/images/14495/large/Internet_Computer_logo.png',
        'ALGO' => 'https://assets.coingecko.com/coins/images/4380/large/download.png',
        'XTZ' => 'https://assets.coingecko.com/coins/images/976/large/Tezos-logo.png',
        'EGLD' => 'https://assets.coingecko.com/coins/images/12335/large/egld-token-logo.png',
        'AAVE' => 'https://assets.coingecko.com/coins/images/12645/large/AAVE.png',
        'EOS' => 'https://assets.coingecko.com/coins/images/738/large/eos-eos-logo.png',
        'XMR' => 'https://assets.coingecko.com/coins/images/69/large/monero_logo.png',
        'MANA' => 'https://assets.coingecko.com/coins/images/878/large/decentraland-mana.png',
        'SAND' => 'https://assets.coingecko.com/coins/images/12129/large/sandbox_logo.jpg',
        'CRO' => 'https://assets.coingecko.com/coins/images/7310/large/cypto.png',
        'NEAR' => 'https://assets.coingecko.com/coins/images/10365/large/near_icon.png',
        'APE' => 'https://assets.coingecko.com/coins/images/24383/large/apecoin.jpg',
        'LDO' => 'https://assets.coingecko.com/coins/images/13573/large/Lido_DAO.png',
        'USDT' => 'https://assets.coingecko.com/coins/images/325/large/Tether.png',
        'USDC' => 'https://assets.coingecko.com/coins/images/6319/large/USD_Coin_icon.png',
        'BUSD' => 'https://assets.coingecko.com/coins/images/9576/large/BUSD.png',
        'DAI' => 'https://assets.coingecko.com/coins/images/9956/large/4943.png'
    ];
    
    echo "📸 Logo URL'leri güncelleniyor...\n";
    $updated_count = 0;
    
    foreach ($coin_logos as $coin_code => $logo_url) {
        $stmt = $conn->prepare("UPDATE coins SET logo_url = ? WHERE coin_kodu = ?");
        $result = $stmt->execute([$logo_url, $coin_code]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo "✅ $coin_code logo güncellendi\n";
            $updated_count++;
        }
    }
    
    echo "\n📊 Toplam $updated_count coin logosu güncellendi\n\n";
    
    // 3. Logo olmayan coinler için placeholder ayarla
    $stmt = $conn->prepare("
        UPDATE coins 
        SET logo_url = CONCAT('https://via.placeholder.com/64x64/4fc3f7/ffffff?text=', LEFT(coin_kodu, 2))
        WHERE logo_url IS NULL OR logo_url = ''
    ");
    $stmt->execute();
    
    echo "🔧 Placeholder logolar ayarlandı\n";
    
    // 4. Güncel durumu kontrol et
    $stmt = $conn->query("
        SELECT 
            coin_kodu,
            logo_url,
            CASE 
                WHEN logo_url LIKE '%coingecko%' THEN 'CoinGecko'
                WHEN logo_url LIKE '%placeholder%' THEN 'Placeholder'
                ELSE 'Diğer'
            END as logo_type
        FROM coins 
        WHERE is_active = 1 
        ORDER BY coin_kodu
    ");
    
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📋 Güncel Logo Durumu:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $logo_stats = ['CoinGecko' => 0, 'Placeholder' => 0, 'Diğer' => 0];
    
    foreach ($coins as $coin) {
        $logo_stats[$coin['logo_type']]++;
        echo sprintf("%-8s | %-12s | %s\n", 
            $coin['coin_kodu'], 
            $coin['logo_type'],
            substr($coin['logo_url'], 0, 50) . (strlen($coin['logo_url']) > 50 ? '...' : '')
        );
    }
    
    echo "\n📊 İstatistikler:\n";
    foreach ($logo_stats as $type => $count) {
        echo "- $type: $count coin\n";
    }
    
    echo "\n✅ Logo sistemi başarıyla düzeltildi!\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>
