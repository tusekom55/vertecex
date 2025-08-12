<?php
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
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Config dosyası bulunamadı']));
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Session kontrolü (debug modunda esneklik)
if (!isset($_SESSION['user_id']) && (!defined('DEBUG_MODE') || !DEBUG_MODE)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor']);
    exit;
}

$action = $_GET['action'] ?? 'list';
$user_id = $_SESSION['user_id'];

try {
    $conn = db_connect();
    
    switch ($action) {
        case 'list':
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
            
            // Tüm işlem tiplerini birleştir
            $sql = "
                (
                    SELECT 
                        'coin_trade' as type,
                        ci.id,
                        ci.tarih as date,
                        CONCAT(
                            CASE ci.islem 
                                WHEN 'al' THEN 'Coin Alım: '
                                WHEN 'sat' THEN 'Coin Satım: '
                            END,
                            ci.miktar, ' ', c.coin_kodu, ' (₺', ci.fiyat, ')'
                        ) as description,
                        CASE ci.islem 
                            WHEN 'al' THEN -(ci.miktar * ci.fiyat)
                            WHEN 'sat' THEN (ci.miktar * ci.fiyat)
                        END as amount,
                        ci.islem as sub_type,
                        c.coin_adi as coin_name,
                        c.coin_kodu as coin_symbol,
                        ci.miktar as quantity,
                        ci.fiyat as price
                    FROM coin_islemleri ci
                    JOIN coins c ON ci.coin_id = c.id
                    WHERE ci.user_id = ?
                )
                UNION ALL
                (
                    SELECT 
                        'deposit' as type,
                        pyt.id,
                        pyt.tarih as date,
                        CONCAT('Para Yatırma: ₺', pyt.tutar, ' (', 
                            CASE pyt.yontem 
                                WHEN 'papara' THEN 'Papara'
                                WHEN 'kredi_karti' THEN 'Kredi Kartı'
                                WHEN 'havale' THEN 'Havale'
                                ELSE pyt.yontem
                            END, ')'
                        ) as description,
                        CASE pyt.durum 
                            WHEN 'onaylandi' THEN pyt.tutar
                            ELSE 0
                        END as amount,
                        pyt.durum as sub_type,
                        NULL as coin_name,
                        NULL as coin_symbol,
                        NULL as quantity,
                        pyt.tutar as price
                    FROM para_yatirma_talepleri pyt
                    WHERE pyt.user_id = ?
                )
                UNION ALL
                (
                    SELECT 
                        'withdrawal' as type,
                        pct.id,
                        pct.tarih as date,
                        CONCAT('Para Çekme: ₺', pct.tutar, ' (', 
                            CASE pct.yontem 
                                WHEN 'papara' THEN 'Papara'
                                WHEN 'kredi_karti' THEN 'Kredi Kartı'
                                WHEN 'havale' THEN 'Havale'
                                ELSE pct.yontem
                            END, ')'
                        ) as description,
                        CASE pct.durum 
                            WHEN 'onaylandi' THEN -pct.tutar
                            ELSE 0
                        END as amount,
                        pct.durum as sub_type,
                        NULL as coin_name,
                        NULL as coin_symbol,
                        NULL as quantity,
                        pct.tutar as price
                    FROM para_cekme_talepleri pct
                    WHERE pct.user_id = ?
                )
                ORDER BY date DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $user_id, $user_id, $limit, $offset]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // İstatistik bilgileri
            $stats_sql = "
                SELECT 
                    COUNT(CASE WHEN ci.islem = 'al' THEN 1 END) as total_buys,
                    COUNT(CASE WHEN ci.islem = 'sat' THEN 1 END) as total_sells,
                    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_buy_amount,
                    SUM(CASE WHEN ci.islem = 'sat' THEN ci.miktar * ci.fiyat ELSE 0 END) as total_sell_amount,
                    COUNT(CASE WHEN pyt.durum = 'onaylandi' THEN 1 END) as total_deposits,
                    SUM(CASE WHEN pyt.durum = 'onaylandi' THEN pyt.tutar ELSE 0 END) as total_deposit_amount,
                    COUNT(CASE WHEN pct.durum = 'onaylandi' THEN 1 END) as total_withdrawals,
                    SUM(CASE WHEN pct.durum = 'onaylandi' THEN pct.tutar ELSE 0 END) as total_withdrawal_amount
                FROM (SELECT ? as user_id) u
                LEFT JOIN coin_islemleri ci ON ci.user_id = u.user_id
                LEFT JOIN para_yatirma_talepleri pyt ON pyt.user_id = u.user_id
                LEFT JOIN para_cekme_talepleri pct ON pct.user_id = u.user_id
            ";
            
            $stats_stmt = $conn->prepare($stats_sql);
            $stats_stmt->execute([$user_id]);
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Null değerleri sıfırla
            foreach ($stats as $key => $value) {
                $stats[$key] = $value ?: 0;
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'stats' => $stats,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'count' => count($transactions)
                    ]
                ]
            ]);
            break;
            
        case 'stats':
            // Sadece istatistikler
            $stats_sql = "
                SELECT 
                    (SELECT COUNT(*) FROM coin_islemleri WHERE user_id = ?) as total_trades,
                    (SELECT COUNT(*) FROM para_yatirma_talepleri WHERE user_id = ? AND durum = 'onaylandi') as total_deposits,
                    (SELECT COUNT(*) FROM para_cekme_talepleri WHERE user_id = ? AND durum = 'onaylandi') as total_withdrawals,
                    (SELECT SUM(tutar) FROM para_yatirma_talepleri WHERE user_id = ? AND durum = 'onaylandi') as total_deposited,
                    (SELECT SUM(tutar) FROM para_cekme_talepleri WHERE user_id = ? AND durum = 'onaylandi') as total_withdrawn
            ";
            
            $stmt = $conn->prepare($stats_sql);
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Null değerleri sıfırla
            foreach ($stats as $key => $value) {
                $stats[$key] = $value ?: 0;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
            break;
    }
    
} catch (PDOException $e) {
    error_log('Transaction History API Database Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası']);
} catch (Exception $e) {
    error_log('Transaction History API General Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sistem hatası']);
}
?>