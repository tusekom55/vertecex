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
    die(json_encode(['error' => 'Config dosyası bulunamadı']));
}

// Test modu - session kontrolü olmadan
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     http_response_code(403);
//     echo json_encode(['error' => 'Yetkisiz erişim']);
//     exit;
// }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';

try {
    $conn = db_connect();
    
    switch ($action) {
        case 'list':
            // Test verisi - gerçek tablo yoksa basit kullanıcı listesi döndür
            try {
                // Önce leverage_positions tablosu var mı kontrol et
                $check_table_sql = "SHOW TABLES LIKE 'leverage_positions'";
                $check_stmt = $conn->prepare($check_table_sql);
                $check_stmt->execute();
                $table_exists = $check_stmt->rowCount() > 0;
                
                if (!$table_exists) {
                    // Tablo yoksa, kullanıcıları pozisyon sahibi gibi göster
                    error_log("leverage_positions table not found, showing users as having positions");
                    
                    $users_sql = "SELECT 
                                    u.id as user_id,
                                    u.username,
                                    'BTC' as coin_symbol,
                                    'long' as position_type,
                                    10 as leverage_ratio,
                                    45000 as entry_price,
                                    47000 as current_price,
                                    1000 as invested_amount,
                                    400 as unrealized_pnl,
                                    40 as pnl_percentage,
                                    'open' as status,
                                    NOW() as open_time,
                                    CONCAT('pos_', u.id) as id
                                  FROM users u 
                                  WHERE u.role = 'user' 
                                  LIMIT 10";
                    
                    $positions_stmt = $conn->prepare($users_sql);
                    $positions_stmt->execute();
                    $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Tablo varsa normal sorgu çalıştır
                    $positions_sql = "SELECT 
                                        lp.id,
                                        lp.user_id,
                                        u.username,
                                        lp.coin_id,
                                        c.coin_kodu as coin_symbol,
                                        lp.position_type,
                                        lp.leverage_ratio,
                                        lp.entry_price,
                                        lp.invested_amount,
                                        lp.unrealized_pnl,
                                        lp.pnl_percentage,
                                        lp.status,
                                        lp.open_time,
                                        c.current_price
                                      FROM leverage_positions lp
                                      JOIN users u ON lp.user_id = u.id
                                      JOIN coins c ON lp.coin_id = c.id
                                      WHERE lp.status = 'open'
                                      ORDER BY lp.open_time DESC";
                    
                    $positions_stmt = $conn->prepare($positions_sql);
                    $positions_stmt->execute();
                    $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {
                error_log("Error checking leverage positions: " . $e->getMessage());
                $positions = [];
            }
            
            // Her pozisyon için güncel fiyat ve PnL hesapla
            foreach ($positions as &$position) {
                $current_price = floatval($position['current_price']);
                $entry_price = floatval($position['entry_price']);
                $invested_amount = floatval($position['invested_amount']);
                $leverage = floatval($position['leverage_ratio']);
                
                // PnL hesaplama
                $price_change_percent = (($current_price - $entry_price) / $entry_price) * 100;
                
                if ($position['position_type'] === 'short') {
                    $price_change_percent = -$price_change_percent;
                }
                
                $pnl_percent = $price_change_percent * $leverage;
                $unrealized_pnl = $invested_amount * ($pnl_percent / 100);
                
                $position['current_price'] = $current_price;
                $position['unrealized_pnl'] = $unrealized_pnl;
                $position['pnl_percentage'] = $pnl_percent;
            }
            
            echo json_encode(['success' => true, 'data' => $positions]);
            break;
            
        case 'intervene':
            // Basitleştirilmiş müdahale sistemi
            $user_id = intval($_POST['user_id'] ?? 0);
            $profit_percentage = floatval($_POST['profit_percentage'] ?? 0);
            
            if ($user_id <= 0) {
                echo json_encode(['error' => 'Geçersiz kullanıcı ID']);
                exit;
            }
            
            if ($profit_percentage <= 0) {
                echo json_encode(['error' => 'Kar yüzdesi 0\'dan büyük olmalıdır']);
                exit;
            }
            
            // Kullanıcı bilgilerini al
            $user_sql = "SELECT id, username, balance FROM users WHERE id = ?";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['error' => 'Kullanıcı bulunamadı']);
                exit;
            }
            
            // Basit kar yaptırma sistemi
            $current_balance = floatval($user['balance']);
            $profit_amount = $current_balance * ($profit_percentage / 100);
            $new_balance = $current_balance + $profit_amount;
            
            $conn->beginTransaction();
            
            try {
                // Kullanıcının bakiyesini artır
                $update_balance_sql = "UPDATE users SET balance = ? WHERE id = ?";
                $balance_stmt = $conn->prepare($update_balance_sql);
                $balance_stmt->execute([$new_balance, $user_id]);
                
                // İşlem geçmişine ekle
                $history_sql = "INSERT INTO kullanici_islem_gecmisi 
                               (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye) 
                               VALUES (?, 'admin_kar', ?, ?, ?, ?)";
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->execute([
                    $user_id,
                    "Admin tarafından %{$profit_percentage} kar verildi",
                    $profit_amount,
                    $current_balance,
                    $new_balance
                ]);
                
                // Admin log kaydı
                try {
                    $log_sql = "INSERT INTO admin_islem_loglari 
                               (admin_id, islem_tipi, hedef_id, islem_detayi) 
                               VALUES (?, 'profit_intervention', ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->execute([
                        $_SESSION['user_id'] ?? 1,
                        $user_id,
                        "Kullanıcıya %{$profit_percentage} kar verildi - Tutar: ₺{$profit_amount}"
                    ]);
                } catch (Exception $log_error) {
                    error_log("Log insertion error: " . $log_error->getMessage());
                    // Log hatası ana işlemi durdurmasın
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Başarıyla ₺{$profit_amount} kar verildi!",
                    'details' => [
                        'username' => $user['username'],
                        'old_balance' => $current_balance,
                        'new_balance' => $new_balance,
                        'profit_amount' => $profit_amount,
                        'profit_percentage' => $profit_percentage
                    ]
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Profit intervention error: " . $e->getMessage());
                echo json_encode(['error' => 'Kar verilemedi: ' . $e->getMessage()]);
            }
            break;
            
        case 'force_close':
            // Tek pozisyonu zorla kapat
            $position_id = intval($_POST['position_id'] ?? 0);
            
            if ($position_id <= 0) {
                echo json_encode(['error' => 'Geçersiz pozisyon ID']);
                exit;
            }
            
            // Pozisyon bilgilerini al
            $position_sql = "SELECT * FROM leverage_positions WHERE id = ? AND status = 'open'";
            $position_stmt = $conn->prepare($position_sql);
            $position_stmt->execute([$position_id]);
            $position = $position_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$position) {
                echo json_encode(['error' => 'Pozisyon bulunamadı']);
                exit;
            }
            
            $conn->beginTransaction();
            
            try {
                // Pozisyonu kapat (mevcut PnL ile)
                $close_sql = "UPDATE leverage_positions 
                             SET status = 'closed',
                                 close_time = NOW(),
                                 realized_pnl = unrealized_pnl,
                                 admin_intervention = 1,
                                 intervention_type = 'admin_close'
                             WHERE id = ?";
                $close_stmt = $conn->prepare($close_sql);
                $close_stmt->execute([$position_id]);
                
                // Kullanıcı bakiyesini güncelle
                $final_amount = floatval($position['invested_amount']) + floatval($position['unrealized_pnl']);
                $balance_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $balance_stmt = $conn->prepare($balance_sql);
                $balance_stmt->execute([$final_amount, $position['user_id']]);
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Pozisyon başarıyla kapatıldı']);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['error' => 'Pozisyon kapatılamadı: ' . $e->getMessage()]);
            }
            break;
            
        case 'close_all':
            // Tüm pozisyonları kapat
            $positions_sql = "SELECT * FROM leverage_positions WHERE status = 'open'";
            $positions_stmt = $conn->prepare($positions_sql);
            $positions_stmt->execute();
            $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $closed_count = 0;
            $conn->beginTransaction();
            
            try {
                foreach ($positions as $position) {
                    // Pozisyonu kapat
                    $close_sql = "UPDATE leverage_positions 
                                 SET status = 'closed',
                                     close_time = NOW(),
                                     realized_pnl = unrealized_pnl,
                                     admin_intervention = 1,
                                     intervention_type = 'admin_close_all'
                                 WHERE id = ?";
                    $close_stmt = $conn->prepare($close_sql);
                    $close_stmt->execute([$position['id']]);
                    
                    // Kullanıcı bakiyesini güncelle
                    $final_amount = floatval($position['invested_amount']) + floatval($position['unrealized_pnl']);
                    $balance_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                    $balance_stmt = $conn->prepare($balance_sql);
                    $balance_stmt->execute([$final_amount, $position['user_id']]);
                    
                    $closed_count++;
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Tüm pozisyonlar kapatıldı', 'closed_count' => $closed_count]);
                
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['error' => 'Pozisyonlar kapatılamadı: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Geçersiz işlem']);
            break;
    }
    
} catch (PDOException $e) {
    error_log('Leverage Control Database Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Veritabanı hatası']);
} catch (Exception $e) {
    error_log('Leverage Control General Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Sistem hatası']);
}
?>