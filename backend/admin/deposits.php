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

// Security utils - opsiyonel
$security_paths = [
    __DIR__ . '/../utils/security.php',
    __DIR__ . '/utils/security.php'
];

foreach ($security_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
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

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';

// Database bağlantısını kur
$conn = db_connect();

switch ($action) {
    case 'list':
        // Para yatırma taleplerini listele
        $sql = "SELECT 
                    pyt.*, 
                    u.username, 
                    u.email, 
                    u.ad_soyad,
                    u.balance
                FROM para_yatirma_talepleri pyt
                JOIN users u ON pyt.user_id = u.id
                ORDER BY pyt.tarih DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $deposits]);
        break;
        
    case 'approve':
        // Para yatırma talebini onayla
        $deposit_id = intval($_POST['deposit_id'] ?? 0);
        $aciklama = $_POST['aciklama'] ?? 'Admin tarafından onaylandı';
        
        if ($deposit_id <= 0) {
            echo json_encode(['error' => 'Geçersiz talep ID']);
            exit;
        }
        
        // Talebi getir
        $sql = "SELECT pyt.*, u.balance, u.username 
                FROM para_yatirma_talepleri pyt
                JOIN users u ON pyt.user_id = u.id
                WHERE pyt.id = ? AND pyt.durum = 'beklemede'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$deposit_id]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deposit) {
            echo json_encode(['error' => 'Talep bulunamadı veya zaten işlenmiş']);
            exit;
        }
        
        try {
            $conn->beginTransaction();
            
            // Talebi onayla
            $update_sql = "UPDATE para_yatirma_talepleri SET 
                          durum = 'onaylandi', 
                          onay_tarihi = NOW(), 
                          onaylayan_admin_id = ?,
                          aciklama = ?
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$_SESSION['user_id'] ?? 1, $aciklama, $deposit_id]);
            
            // Kullanıcının bakiyesini güncelle
            $balance_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
            $balance_stmt = $conn->prepare($balance_sql);
            $balance_stmt->execute([$deposit['tutar'], $deposit['user_id']]);
            
            // Yeni bakiyeyi al
            $new_balance_sql = "SELECT balance FROM users WHERE id = ?";
            $new_balance_stmt = $conn->prepare($new_balance_sql);
            $new_balance_stmt->execute([$deposit['user_id']]);
            $new_balance = $new_balance_stmt->fetchColumn();
            
            // İşlem geçmişine ekle
            $history_sql = "INSERT INTO kullanici_islem_gecmisi 
                           (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye) 
                           VALUES (?, 'para_yatirma', ?, ?, ?, ?)";
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->execute([
                $deposit['user_id'],
                "Para yatırma onaylandı - {$deposit['yontem']} - ₺{$deposit['tutar']}",
                $deposit['tutar'],
                $deposit['balance'],
                $new_balance
            ]);
            
            // Admin log kaydı
            $admin_log_sql = "INSERT INTO admin_islem_loglari 
                             (admin_id, islem_tipi, hedef_id, islem_detayi) 
                             VALUES (?, 'para_onaylama', ?, ?)";
            $admin_log_stmt = $conn->prepare($admin_log_sql);
            $admin_log_stmt->execute([
                $_SESSION['user_id'] ?? 1, 
                $deposit_id, 
                "Para yatırma onaylandı: {$deposit['username']} - ₺{$deposit['tutar']}"
            ]);
            
            // Sistem log kaydı
            $log_sql = "INSERT INTO loglar (user_id, tip, detay) VALUES (?, 'para_yatirma', ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->execute([
                $deposit['user_id'], 
                "Para yatırma onaylandı: ID:{$deposit_id}, Tutar:₺{$deposit['tutar']}, Yeni Bakiye:₺{$new_balance}"
            ]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Para yatırma talebi onaylandı',
                'data' => [
                    'old_balance' => $deposit['balance'],
                    'new_balance' => $new_balance,
                    'deposit_amount' => $deposit['tutar']
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'İşlem başarısız: ' . $e->getMessage()]);
        }
        break;
        
    case 'reject':
        // Para yatırma talebini reddet
        $deposit_id = intval($_POST['deposit_id'] ?? 0);
        $aciklama = $_POST['aciklama'] ?? 'Admin tarafından reddedildi';
        
        if ($deposit_id <= 0) {
            echo json_encode(['error' => 'Geçersiz talep ID']);
            exit;
        }
        
        // Talebi getir
        $sql = "SELECT pyt.*, u.username 
                FROM para_yatirma_talepleri pyt
                JOIN users u ON pyt.user_id = u.id
                WHERE pyt.id = ? AND pyt.durum = 'beklemede'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$deposit_id]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deposit) {
            echo json_encode(['error' => 'Talep bulunamadı veya zaten işlenmiş']);
            exit;
        }
        
        try {
            $conn->beginTransaction();
            
            // Talebi reddet
            $update_sql = "UPDATE para_yatirma_talepleri SET 
                          durum = 'reddedildi', 
                          onay_tarihi = NOW(), 
                          onaylayan_admin_id = ?,
                          aciklama = ?
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$_SESSION['user_id'] ?? 1, $aciklama, $deposit_id]);
            
            // Admin log kaydı
            $admin_log_sql = "INSERT INTO admin_islem_loglari 
                             (admin_id, islem_tipi, hedef_id, islem_detayi) 
                             VALUES (?, 'para_onaylama', ?, ?)";
            $admin_log_stmt = $conn->prepare($admin_log_sql);
            $admin_log_stmt->execute([
                $_SESSION['user_id'] ?? 1, 
                $deposit_id, 
                "Para yatırma reddedildi: {$deposit['username']} - ₺{$deposit['tutar']} - Sebep: {$aciklama}"
            ]);
            
            // Sistem log kaydı
            $log_sql = "INSERT INTO loglar (user_id, tip, detay) VALUES (?, 'para_yatirma', ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->execute([
                $deposit['user_id'], 
                "Para yatırma reddedildi: ID:{$deposit_id}, Tutar:₺{$deposit['tutar']}, Sebep:{$aciklama}"
            ]);
            
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'Para yatırma talebi reddedildi']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'İşlem başarısız: ' . $e->getMessage()]);
        }
        break;
        
    case 'detail':
        // Para yatırma talebi detayları
        $deposit_id = intval($_GET['deposit_id'] ?? 0);
        
        if ($deposit_id <= 0) {
            echo json_encode(['error' => 'Geçersiz talep ID']);
            exit;
        }
        
        $sql = "SELECT 
                    pyt.*, 
                    u.username, 
                    u.email, 
                    u.ad_soyad, 
                    u.telefon, 
                    u.balance,
                    admin.username as admin_username
                FROM para_yatirma_talepleri pyt
                JOIN users u ON pyt.user_id = u.id
                LEFT JOIN users admin ON pyt.onaylayan_admin_id = admin.id
                WHERE pyt.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$deposit_id]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deposit) {
            echo json_encode(['error' => 'Talep bulunamadı']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $deposit]);
        break;
        
    case 'stats':
        // Para yatırma istatistikleri
        $stats_sql = "SELECT 
                        COUNT(*) as total_deposits,
                        COUNT(CASE WHEN durum = 'beklemede' THEN 1 END) as pending_deposits,
                        COUNT(CASE WHEN durum = 'onaylandi' THEN 1 END) as approved_deposits,
                        COUNT(CASE WHEN durum = 'reddedildi' THEN 1 END) as rejected_deposits,
                        SUM(CASE WHEN durum = 'onaylandi' THEN tutar ELSE 0 END) as total_approved_amount,
                        AVG(CASE WHEN durum = 'onaylandi' THEN tutar ELSE NULL END) as avg_approved_amount
                      FROM para_yatirma_talepleri";
        
        $stmt = $conn->prepare($stats_sql);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Günlük istatistikler
        $daily_sql = "SELECT 
                        COUNT(*) as today_deposits,
                        SUM(CASE WHEN durum = 'onaylandi' THEN tutar ELSE 0 END) as today_approved_amount
                      FROM para_yatirma_talepleri 
                      WHERE DATE(tarih) = CURDATE()";
        
        $daily_stmt = $conn->prepare($daily_sql);
        $daily_stmt->execute();
        $daily_stats = $daily_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'data' => array_merge($stats, $daily_stats)
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Geçersiz işlem']);
        break;
}
?>