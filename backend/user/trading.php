<?php
// JSON çıktısını korumak için hata raporlamayı tamamen kapat
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Output buffering başlat ve temizle
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Herhangi bir whitespace veya çıktıyı önle
ob_clean();

// Session yönetimi - çakışma önleme
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Config dosyası path'ini esnek şekilde bulma
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    // Buffer'ı temizle ve JSON header'ı ayarla
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Config dosyası bulunamadı']));
}

// Buffer'ı temizle ve header'ları ayarla
ob_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Session kontrolü (debug modunda esneklik)
$user_id = $_SESSION['user_id'] ?? null;

// Debug mode veya test için user_id yoksa varsayılan değer ata
if (!$user_id) {
    // Test/debug modunda varsayılan user_id kullan
    $user_id = 1;
    error_log("No user_id in session, using default user_id: 1 for testing");
}

$action = $_GET['action'] ?? '';

// Debug: Action ve request bilgilerini logla
error_log("TRADING API REQUEST - Action: '{$action}', Method: {$_SERVER['REQUEST_METHOD']}, User ID: {$user_id}");

try {
    $conn = db_connect();
    
    switch ($action) {
        case 'buy':
            // Coin satın alma
            $coin_id = intval($_POST['coin_id'] ?? 0);
            $miktar = floatval($_POST['miktar'] ?? 0);
            $fiyat = floatval($_POST['fiyat'] ?? 0);
            
            if ($coin_id <= 0 || $miktar <= 0 || $fiyat <= 0) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler']);
                exit;
            }
            
            $toplam_tutar = $miktar * $fiyat;
            
            // Kullanıcının bakiyesini kontrol et
            $balance_sql = "SELECT balance FROM users WHERE id = ?";
            $balance_stmt = $conn->prepare($balance_sql);
            $balance_stmt->execute([$user_id]);
            $current_balance = $balance_stmt->fetchColumn();
            
            if ($current_balance < $toplam_tutar) {
                echo json_encode(['success' => false, 'message' => 'Yetersiz bakiye. Mevcut: ₺' . number_format($current_balance, 2)]);
                exit;
            }
            
            // Coin bilgilerini al
            $coin_sql = "SELECT coin_adi, coin_kodu FROM coins WHERE id = ? AND is_active = 1";
            $coin_stmt = $conn->prepare($coin_sql);
            $coin_stmt->execute([$coin_id]);
            $coin = $coin_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coin) {
                echo json_encode(['success' => false, 'message' => 'Coin bulunamadı']);
                exit;
            }
            
            $conn->beginTransaction();
            
            try {
                // Coin işlemini kaydet
                $trade_sql = "INSERT INTO coin_islemleri (user_id, coin_id, islem, miktar, fiyat, tarih) VALUES (?, ?, 'al', ?, ?, NOW())";
                $trade_stmt = $conn->prepare($trade_sql);
                $trade_stmt->execute([$user_id, $coin_id, $miktar, $fiyat]);
                $trade_id = $conn->lastInsertId();
                
                // Kullanıcının bakiyesini güncelle
                $update_balance_sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
                $update_balance_stmt = $conn->prepare($update_balance_sql);
                $update_balance_stmt->execute([$toplam_tutar, $user_id]);
                
                // Yeni bakiyeyi al
                $new_balance = $current_balance - $toplam_tutar;
                
                // İşlem geçmişine ekle
                $history_sql = "INSERT INTO kullanici_islem_gecmisi 
                               (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye) 
                               VALUES (?, 'coin_al', ?, ?, ?, ?)";
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->execute([
                    $user_id,
                    "{$miktar} {$coin['coin_kodu']} satın alındı (₺{$fiyat} fiyatından)",
                    $toplam_tutar,
                    $current_balance,
                    $new_balance
                ]);
                
                // Log kaydı
                $log_sql = "INSERT INTO loglar (user_id, tip, detay) VALUES (?, 'coin_islem', ?)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->execute([
                    $user_id, 
                    "SATIN ALMA: {$miktar} {$coin['coin_kodu']} - ₺{$toplam_tutar} - ID:{$trade_id}"
                ]);
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => "{$miktar} {$coin['coin_kodu']} başarıyla satın alındı",
                    'data' => [
                        'trade_id' => $trade_id,
                        'miktar' => $miktar,
                        'fiyat' => $fiyat,
                        'toplam_tutar' => $toplam_tutar,
                        'new_balance' => $new_balance,
                        'coin' => $coin
                    ]
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;
            
        case 'sell':
            // Coin satma işlemi
            $coin_id = intval($_POST['coin_id'] ?? 0);
            $miktar = floatval($_POST['miktar'] ?? 0);
            $fiyat = floatval($_POST['fiyat'] ?? 0);
            
            // Debug log
            error_log("SELL REQUEST - coin_id: {$coin_id}, miktar: {$miktar}, fiyat: {$fiyat}, user_id: {$user_id}");
            
            if ($coin_id <= 0 || $miktar <= 0) {
                ob_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Geçersiz parametreler'
                ]);
                exit;
            }
            
            // Kullanıcının bu coin'den sahip olduğu miktarı hesapla
            $portfolio_sql = "SELECT 
                                SUM(CASE WHEN islem = 'al' THEN miktar ELSE -miktar END) as net_miktar,
                                COUNT(*) as transaction_count
                              FROM coin_islemleri 
                              WHERE user_id = ? AND coin_id = ?";
            $portfolio_stmt = $conn->prepare($portfolio_sql);
            $portfolio_stmt->execute([$user_id, $coin_id]);
            $portfolio_result = $portfolio_stmt->fetch(PDO::FETCH_ASSOC);
            $net_miktar = floatval($portfolio_result['net_miktar'] ?? 0);
            $transaction_count = intval($portfolio_result['transaction_count'] ?? 0);
            
            error_log("SELL PORTFOLIO CHECK - net_miktar: {$net_miktar}, transaction_count: {$transaction_count}");
            
            // Portföyde bu coin yoksa hata
            if ($transaction_count == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Bu coin portföyünüzde bulunmuyor.',
                    'debug' => [
                        'coin_id' => $coin_id,
                        'user_id' => $user_id,
                        'transaction_count' => $transaction_count
                    ]
                ]);
                exit;
            }
            
            // Hassas miktar kontrolü (floating point precision)
            if ($net_miktar < ($miktar - 0.00000001)) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Yetersiz coin miktarı. Mevcut: ' . number_format($net_miktar, 8) . ', İstenen: ' . number_format($miktar, 8),
                    'debug' => [
                        'requested_amount' => $miktar,
                        'available_amount' => $net_miktar,
                        'difference' => $miktar - $net_miktar,
                        'coin_id' => $coin_id,
                        'user_id' => $user_id
                    ]
                ]);
                exit;
            }
            
            // Coin bilgilerini al
            $coin_sql = "SELECT coin_adi, coin_kodu, current_price FROM coins WHERE id = ? AND is_active = 1";
            $coin_stmt = $conn->prepare($coin_sql);
            $coin_stmt->execute([$coin_id]);
            $coin = $coin_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coin) {
                echo json_encode(['success' => false, 'message' => 'Coin bulunamadı veya aktif değil']);
                exit;
            }
            
            // Fiyat kontrolü ve TL dönüşümü
            $current_price_tl = floatval($coin['current_price']);
            
            // Fiyat belirtilmemişse veya 0 ise güncel fiyatı kullan
            if ($fiyat <= 0) {
                $fiyat = $current_price_tl;
                error_log("SELL - Using current price: {$fiyat} TL");
            } else {
                error_log("SELL - Using provided price: {$fiyat} TL");
            }
            
            // Fiyat geçerlilik kontrolü
            if ($fiyat <= 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Geçersiz fiyat bilgisi. Güncel fiyat: ' . $current_price_tl,
                    'debug' => [
                        'provided_price' => $_POST['fiyat'] ?? 'not provided',
                        'current_price' => $current_price_tl,
                        'final_price' => $fiyat
                    ]
                ]);
                exit;
            }
            
            // Toplam tutar hesaplama
            $toplam_tutar = $miktar * $fiyat;
            $komisyon = $toplam_tutar * 0.001; // %0.1 komisyon
            $net_tutar = $toplam_tutar - $komisyon;
            
            error_log("SELL CALCULATION - miktar: {$miktar}, fiyat: {$fiyat}, toplam: {$toplam_tutar}, komisyon: {$komisyon}, net: {$net_tutar}");
            
            // Mevcut bakiyeyi al
            $balance_sql = "SELECT balance FROM users WHERE id = ?";
            $balance_stmt = $conn->prepare($balance_sql);
            $balance_stmt->execute([$user_id]);
            $current_balance = floatval($balance_stmt->fetchColumn() ?: 0);
            
            $conn->beginTransaction();
            
            try {
                // Coin satış işlemini kaydet
                $trade_sql = "INSERT INTO coin_islemleri (user_id, coin_id, islem, miktar, fiyat, tarih) VALUES (?, ?, 'sat', ?, ?, NOW())";
                $trade_stmt = $conn->prepare($trade_sql);
                $trade_stmt->execute([$user_id, $coin_id, $miktar, $fiyat]);
                $trade_id = $conn->lastInsertId();
                
                // Kullanıcının bakiyesini güncelle - Net tutarı ekle (komisyon düşülmüş)
                $update_balance_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $update_balance_stmt = $conn->prepare($update_balance_sql);
                $update_balance_stmt->execute([$net_tutar, $user_id]);
                
                // Yeni bakiyeyi hesapla
                $new_balance = $current_balance + $net_tutar;
                
                // İşlem geçmişine ekle - Komisyon bilgisi ile
                $history_sql = "INSERT INTO kullanici_islem_gecmisi 
                               (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye) 
                               VALUES (?, 'coin_sat', ?, ?, ?, ?)";
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->execute([
                    $user_id,
                    "{$miktar} {$coin['coin_kodu']} satıldı (₺{$fiyat} fiyatından, komisyon: ₺{$komisyon})",
                    $net_tutar,
                    $current_balance,
                    $new_balance
                ]);
                
                // Log kaydı - Komisyon bilgisi ile
                $log_sql = "INSERT INTO loglar (user_id, tip, detay) VALUES (?, 'coin_islem', ?)";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->execute([
                    $user_id, 
                    "SATIM: {$miktar} {$coin['coin_kodu']} - Brüt: ₺{$toplam_tutar}, Komisyon: ₺{$komisyon}, Net: ₺{$net_tutar} - ID:{$trade_id}"
                ]);
                
                $conn->commit();
                
                // Başarılı satış response'u - Komisyon detayları ile
                echo json_encode([
                    'success' => true, 
                    'message' => "{$miktar} {$coin['coin_kodu']} başarıyla satıldı (Net: ₺" . number_format($net_tutar, 2) . ")",
                    'data' => [
                        'trade_id' => $trade_id,
                        'miktar' => $miktar,
                        'fiyat' => $fiyat,
                        'toplam_tutar' => $toplam_tutar,
                        'komisyon' => $komisyon,
                        'net_tutar' => $net_tutar,
                        'new_balance' => $new_balance,
                        'coin' => $coin,
                        'portfolio_updated' => true
                    ]
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("SELL TRANSACTION ERROR: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Satış işlemi sırasında hata oluştu: ' . $e->getMessage(),
                    'error_details' => [
                        'user_id' => $user_id,
                        'coin_id' => $coin_id,
                        'miktar' => $miktar,
                        'fiyat' => $fiyat,
                        'net_miktar' => $net_miktar,
                        'error' => $e->getMessage()
                    ]
                ]);
                exit;
            }
            break;
            
        case 'portfolio':
            // Portföy listesi - coins.php ile uyumlu
            error_log("Portfolio - User ID: " . ($user_id ?? 'NULL'));
            
            // Test modunda user_id yoksa 1 olarak varsay
            if (!$user_id) {
                $user_id = 1;
                error_log("Portfolio - No user_id in session, defaulting to 1 for test mode");
            }
            
            // Gerekli tabloların varlığını kontrol et
            $required_tables = ['coins', 'coin_islemleri'];
            foreach ($required_tables as $table) {
                $table_check = $conn->prepare("SHOW TABLES LIKE '" . $table . "'");
                $table_check->execute();
                if ($table_check->rowCount() == 0) {
                    error_log("Portfolio - Missing table: $table");
                    echo json_encode([
                        'success' => false,
                        'message' => "Veritabanı tablosu eksik: $table. Lütfen veritabanını kurun.",
                        'error_type' => 'missing_table',
                        'missing_table' => $table
                    ]);
                    exit;
                }
            }
            
            try {
                // Önce kullanıcının işlemlerini kontrol et
                $check_sql = "SELECT COUNT(*) as transaction_count FROM coin_islemleri WHERE user_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->execute([$user_id]);
                $transaction_count = $check_stmt->fetchColumn();
                
                error_log("Portfolio Debug - User {$user_id} has {$transaction_count} transactions");
                
                if ($transaction_count == 0) {
                    // Hiç işlem yoksa boş portföy döndür
                    $portfolio = [];
                } else {
                    // DÜZELTME: Portföyde coin'in aktif durumu kontrol edilmemeli
                    // Kullanıcı coin'i satın aldığında aktifti, fiyat güncellemesi portföy görünürlüğünü etkilememeli
                    $portfolio_sql = "SELECT 
                                        p.*,
                                        c.coin_adi,
                                        c.coin_kodu,
                                        c.logo_url,
                                        COALESCE(c.current_price, 0) as current_price,
                                        COALESCE(c.price_change_24h, 0) as price_change_24h,
                                        'Kripto Para' as kategori_adi
                                      FROM (
                                        SELECT 
                                            ci.coin_id,
                                            -- Net miktar (alış - satış)
                                            SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
                                            
                                            -- Ortalama alış fiyatı (sadece alış işlemlerinin ağırlıklı ortalaması)
                                            CASE 
                                                WHEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) > 0 
                                                THEN SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) / 
                                                     SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END)
                                                ELSE 0 
                                            END as avg_buy_price,
                                            
                                            -- Toplam alış tutarı
                                            SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_bought_amount,
                                            -- Toplam alış miktarı
                                            SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE 0 END) as total_bought_quantity,
                                            -- Toplam satış tutarı
                                            SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_sold_amount,
                                            -- Toplam satış miktarı
                                            SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar ELSE 0 END) as total_sold_quantity,
                                            
                                            -- Son işlem tarihi
                                            MAX(ci.tarih) as last_transaction_date,
                                            -- İşlem sayısı
                                            COUNT(ci.id) as transaction_count
                                        FROM coin_islemleri ci
                                        WHERE ci.user_id = ?
                                        GROUP BY ci.coin_id
                                        -- Sadece pozitif miktarları göster
                                        HAVING SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) > 0.00000001
                                      ) p
                                      JOIN coins c ON p.coin_id = c.id
                                      ORDER BY (p.net_miktar * c.current_price) DESC";
            
                    $portfolio_stmt = $conn->prepare($portfolio_sql);
                    if (!$portfolio_stmt) {
                        throw new PDOException("SQL prepare failed: " . implode(', ', $conn->errorInfo()));
                    }
                    
                    $portfolio_stmt->execute([$user_id]);
                    
                    // SQL hatası kontrolü
                    if ($portfolio_stmt->errorCode() !== '00000') {
                        $error_info = $portfolio_stmt->errorInfo();
                        throw new PDOException("Portfolio SQL Error: " . $error_info[2]);
                    }
                    
                    $portfolio = $portfolio_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
            } catch (PDOException $sql_error) {
                error_log("Portfolio SQL Error: " . $sql_error->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Portföy verilerini çekerken SQL hatası oluştu: ' . $sql_error->getMessage(),
                    'error_type' => 'sql_error',
                    'sql_error' => $sql_error->getMessage()
                ]);
                exit;
            }
            
            error_log("Portfolio Result Count: " . count($portfolio));
            
            $total_value = 0;
            $total_invested = 0;
            
            foreach ($portfolio as &$item) {
                // Temel değerleri float'a çevir
                $item['net_miktar'] = floatval($item['net_miktar']);
                $item['current_price'] = floatval($item['current_price']);
                $item['avg_buy_price'] = floatval($item['avg_buy_price']) ?: 0;
                $item['price_change_24h'] = floatval($item['price_change_24h']) ?: 0;
                $item['total_bought_amount'] = floatval($item['total_bought_amount']) ?: 0;
                $item['total_sold_amount'] = floatval($item['total_sold_amount']) ?: 0;
                $item['total_bought_quantity'] = floatval($item['total_bought_quantity']) ?: 0;
                $item['total_sold_quantity'] = floatval($item['total_sold_quantity']) ?: 0;
                
                // Mevcut değer = Net miktar × Güncel fiyat
                $item['current_value'] = $item['net_miktar'] * $item['current_price'];
                
                // DÜZELTME: Doğru yatırım tutarı hesaplaması
                if ($item['total_sold_quantity'] == 0) {
                    // Hiç satış yapılmamışsa: Net miktar × Ortalama alış fiyatı
                    $item['invested_value'] = $item['net_miktar'] * $item['avg_buy_price'];
                } else {
                    // FIFO mantığı ile: Kalan miktar için gerçek yatırım tutarı
                    $sold_cost = $item['total_sold_quantity'] * $item['avg_buy_price'];
                    $item['invested_value'] = $item['total_bought_amount'] - $sold_cost;
                    
                    // Negatif değer kontrolü
                    if ($item['invested_value'] < 0) {
                        $item['invested_value'] = $item['net_miktar'] * $item['avg_buy_price'];
                    }
                }
                
                // Kar/Zarar = Mevcut değer - Yatırılan değer
                $item['profit_loss'] = $item['current_value'] - $item['invested_value'];
                
                // Kar/Zarar yüzdesi
                if ($item['invested_value'] > 0) {
                    $item['profit_loss_percent'] = ($item['profit_loss'] / $item['invested_value']) * 100;
                } else {
                    $item['profit_loss_percent'] = 0;
                }
                
                // Negatif değerleri düzelt
                if ($item['current_value'] < 0) $item['current_value'] = 0;
                if ($item['invested_value'] < 0) $item['invested_value'] = 0;
                
                // Ek bilgiler
                $item['kategori_adi'] = $item['kategori_adi'] ?: 'Diğer';
                $item['transaction_count'] = intval($item['transaction_count']);
                $item['currency'] = 'TRY';
                
                // Formatlanmış değerler
                $item['current_value_formatted'] = number_format($item['current_value'], 2, '.', ',');
                $item['invested_value_formatted'] = number_format($item['invested_value'], 2, '.', ',');
                $item['profit_loss_formatted'] = ($item['profit_loss'] >= 0 ? '+' : '') . number_format($item['profit_loss'], 2, '.', ',');
                
                // Toplam değerleri hesapla
                $total_value += $item['current_value'];
                $total_invested += $item['invested_value'];
                
                // Debug log
                error_log("Portfolio Item - {$item['coin_kodu']}: " .
                         "Net: {$item['net_miktar']}, " .
                         "Current Price: {$item['current_price']}, " .
                         "Avg Buy Price: {$item['avg_buy_price']}, " .
                         "Current Value: {$item['current_value']}, " .
                         "Invested: {$item['invested_value']}, " .
                         "P/L: {$item['profit_loss']} ({$item['profit_loss_percent']}%)");
            }
            
            $total_profit_loss = $total_value - $total_invested;
            $total_profit_loss_percent = $total_invested > 0 ? (($total_profit_loss / $total_invested) * 100) : 0;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'portfolio' => $portfolio,
                    'summary' => [
                        'total_value' => $total_value,
                        'total_invested' => $total_invested,
                        'total_profit_loss' => $total_profit_loss,
                        'total_profit_loss_percent' => $total_profit_loss_percent,
                        'coin_count' => count($portfolio),
                        'currency' => 'TRY'
                    ]
                ]
            ]);
            break;
            
        case 'health_check':
            // Portfolio sistem sağlık kontrolü
            try {
                // Gerekli tabloları kontrol et
                $tables_to_check = ['coins', 'coin_islemleri', 'users'];
                $missing_tables = [];
                
                foreach ($tables_to_check as $table) {
                    $check_sql = "SHOW TABLES LIKE '" . $table . "'";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute();
                    
                    if ($check_stmt->rowCount() == 0) {
                        $missing_tables[] = $table;
                    }
                }
                
                if (!empty($missing_tables)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Eksik tablolar: ' . implode(', ', $missing_tables),
                        'missing_tables' => $missing_tables,
                        'error_type' => 'missing_tables'
                    ]);
                    exit;
                }
                
                // Veritabanı bağlantısını test et
                $test_sql = "SELECT 1";
                $test_stmt = $conn->prepare($test_sql);
                $test_stmt->execute();
                
                // Kullanıcı session kontrolü
                $user_valid = isset($_SESSION['user_id']) || (defined('DEBUG_MODE') && DEBUG_MODE);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Portfolio sistem sağlıklı',
                    'checks' => [
                        'database' => true,
                        'tables' => true,
                        'user_session' => $user_valid
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Sağlık kontrolü başarısız: ' . $e->getMessage(),
                    'error_type' => 'health_check_failed'
                ]);
            }
            break;
            
        default:
            error_log("UNKNOWN ACTION: " . $action);
            echo json_encode([
                'success' => false, 
                'message' => 'Geçersiz işlem: ' . $action,
                'debug' => [
                    'action' => $action,
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'get_params' => $_GET,
                    'post_params' => $_POST
                ]
            ]);
            break;
    }
    
} catch (PDOException $e) {
    // Buffer'ı temizle ve sadece JSON çıktısı ver
    if (ob_get_level()) {
        ob_clean();
    }
    error_log('Trading API Database Error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası']);
} catch (Exception $e) {
    // Buffer'ı temizle ve sadece JSON çıktısı ver
    if (ob_get_level()) {
        ob_clean();
    }
    error_log('Trading API General Error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sistem hatası']);
}

// Son güvenlik kontrolü - beklenmeyen çıktıları temizle
if (ob_get_level()) {
    ob_end_flush();
}
exit;
?>
