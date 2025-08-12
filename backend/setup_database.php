<?php
/**
 * Veritabanı Kurulum Scripti
 * Tüm tabloları ve başlangıç verilerini oluşturur
 */

// Veritabanı bağlantı bilgileri
$DB_HOST = 'localhost';
$DB_USER = 'u225998063_yenip';
$DB_PASS = '123456Tubb';
$DB_NAME = 'u225998063_yenip';

try {
    // PDO bağlantısı oluştur
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    echo "✅ Veritabanı bağlantısı başarılı!\n\n";
    
    // MySQLi bağlantısı da oluştur (mixed usage için)
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($mysqli->connect_error) {
        throw new Exception('MySQLi bağlantı hatası: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset("utf8mb4");
    
    // SQL dosyalarını çalıştır
    $sqlFiles = [
        'proje_db_schema.sql',
        'admin_schema.sql', 
        'forex_schema.sql',
        'leverage_positions_schema.sql',
        'sample_data.sql',
        'update_schema.sql'
    ];
    
    foreach ($sqlFiles as $sqlFile) {
        if (file_exists($sqlFile)) {
            echo "📄 {$sqlFile} dosyası çalıştırılıyor...\n";
            
            $sql = file_get_contents($sqlFile);
            
            // SQL dosyasını statement'lara böl
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
                }
            );
            
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Tablo zaten varsa hatayı görmezden gel
                        if (strpos($e->getMessage(), 'already exists') === false && 
                            strpos($e->getMessage(), 'Duplicate') === false) {
                            echo "⚠️  Uyarı: " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
            
            echo "✅ {$sqlFile} başarıyla çalıştırıldı!\n\n";
        } else {
            echo "⚠️  {$sqlFile} dosyası bulunamadı, atlanıyor...\n\n";
        }
    }
    
    // Temel kullanıcı oluştur
    echo "👤 Admin kullanıcısı oluşturuluyor...\n";
    
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (username, password, email, ad_soyad, role, balance, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        'admin',
        $adminPassword,
        'admin@gtrader.com',
        'System Administrator',
        'admin',
        1000000.00,
        1
    ]);
    
    echo "✅ Admin kullanıcısı oluşturuldu! (Kullanıcı: admin, Şifre: admin123)\n\n";
    
    // Test kullanıcısı oluştur
    echo "👤 Test kullanıcısı oluşturuluyor...\n";
    
    $testPassword = password_hash('test123', PASSWORD_DEFAULT);
    $stmt->execute([
        'testuser',
        $testPassword,
        'test@gtrader.com',
        'Test User',
        'user',
        50000.00,
        1
    ]);
    
    echo "✅ Test kullanıcısı oluşturuldu! (Kullanıcı: testuser, Şifre: test123)\n\n";
    
    // Coin kategorileri oluştur
    echo "💰 Coin kategorileri oluşturuluyor...\n";
    
    $categories = [
        [1, 'Major Coins', 'Bitcoin, Ethereum gibi büyük coinler'],
        [2, 'Altcoins', 'Alternatif kripto paralar'],
        [3, 'DeFi', 'Merkezi olmayan finans tokenları'],
        [4, 'NFT', 'NFT ile ilgili tokenlar'],
        [5, 'Meme Coins', 'Meme coinler'],
        [6, 'Stablecoins', 'Sabit değerli coinler']
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO coin_kategorileri (id, kategori_adi, aciklama) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
    
    echo "✅ Coin kategorileri oluşturuldu!\n\n";
    
    // Örnek coinler ekle
    echo "🪙 Örnek coinler ekleniyor...\n";
    
    $coins = [
        ['Bitcoin', 'BTC', 'bitcoin', 1, 1, 1350000.00, 2.45],
        ['Ethereum', 'ETH', 'ethereum', 1, 2, 85000.00, 1.23],
        ['Binance Coin', 'BNB', 'binancecoin', 2, 3, 12500.00, -0.56],
        ['Cardano', 'ADA', 'cardano', 2, 4, 2.45, 3.21],
        ['Solana', 'SOL', 'solana', 2, 5, 650.00, 5.67],
        ['Dogecoin', 'DOGE', 'dogecoin', 5, 6, 2.15, -1.23]
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO coins (
            coin_adi, coin_kodu, coingecko_id, kategori_id, sira, 
            current_price, price_change_24h, api_aktif, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)
    ");
    
    foreach ($coins as $coin) {
        $stmt->execute($coin);
    }
    
    echo "✅ Örnek coinler eklendi!\n\n";
    
    // Başlangıç ayarları
    echo "⚙️ Sistem ayarları yapılandırılıyor...\n";
    
    $settings = [
        ['site_baslik', 'GlobalTradePro'],
        ['site_aciklama', 'Profesyonel Kripto Para Trading Platformu'],
        ['komisyon_orani', '0.1'],
        ['minimum_yatirim', '100'],
        ['api_guncelleme_aktif', 'true'],
        ['api_guncelleme_siklik', '300'],
        ['varsayilan_fiyat_kaynak', 'coingecko']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO ayarlar (`key`, `value`) VALUES (?, ?)");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    
    echo "✅ Sistem ayarları yapılandırıldı!\n\n";
    
    // Kaldıraç ayarları için default değerler
    echo "📈 Kaldıraç sistemi ayarları...\n";
    
    $leverageSettings = [
        ['max_leverage', '10.0'],
        ['min_leverage', '1.0'],
        ['liquidation_threshold', '80.0'],
        ['leverage_fee', '0.05']
    ];
    
    foreach ($leverageSettings as $setting) {
        $stmt->execute($setting);
    }
    
    echo "✅ Kaldıraç sistemi ayarları tamamlandı!\n\n";
    
    echo "🎉 VERİTABANI KURULUMU TAMAMLANDI!\n\n";
    echo "📋 KULLANICI BİLGİLERİ:\n";
    echo "   Admin: admin / admin123\n";
    echo "   Test:  testuser / test123\n\n";
    echo "🌐 Veritabanı: {$DB_NAME}\n";
    echo "👤 Kullanıcı: {$DB_USER}\n\n";
    echo "✅ Sistem kullanıma hazır!\n";
    
} catch (Exception $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
    exit(1);
}
?>