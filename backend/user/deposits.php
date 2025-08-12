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

// Test modu için session kontrolü esnetildi
if (!isset($_SESSION['user_id']) && (!defined('DEBUG_MODE') || !DEBUG_MODE)) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum açmanız gerekiyor']);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Database bağlantısını kur
$conn = db_connect();

switch ($action) {
    case 'list':
        // Kullanıcının para yatırma taleplerini listele
        $sql = "SELECT 
                    id, 
                    yontem, 
                    tutar, 
                    durum, 
                    tarih, 
                    detay_bilgiler,
                    aciklama
                FROM para_yatirma_talepleri 
                WHERE user_id = ? 
                ORDER BY tarih DESC 
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $deposits]);
        break;
        
    case 'create':
        // Yeni para yatırma talebi oluştur
        $yontem = $_POST['yontem'] ?? '';
        $tutar = floatval($_POST['tutar'] ?? 0);
        $detay_bilgiler = $_POST['detay_bilgiler'] ?? '';
        $aciklama = $_POST['aciklama'] ?? '';
        
        // Validation
        if (!in_array($yontem, ['papara', 'kredi_karti', 'havale'])) {
            echo json_encode(['error' => 'Geçersiz yatırma yöntemi']);
            exit;
        }
        
        if ($tutar < 10) {
            echo json_encode(['error' => 'Minimum yatırım tutarı ₺10\'dur']);
            exit;
        }
        
        if ($tutar > 50000) {
            echo json_encode(['error' => 'Maksimum yatırım tutarı ₺50,000\'dir']);
            exit;
        }
        
        // Günlük limit kontrolü (son 24 saat)
        $daily_limit_sql = "SELECT SUM(tutar) as daily_total 
                           FROM para_yatirma_talepleri 
                           WHERE user_id = ? 
                           AND tarih >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                           AND durum != 'reddedildi'";
        $daily_stmt = $conn->prepare($daily_limit_sql);
        $daily_stmt->execute([$user_id]);
        $daily_total = $daily_stmt->fetchColumn() ?: 0;
        
        if (($daily_total + $tutar) > 50000) {
            echo json_encode(['error' => 'Günlük yatırım limitinizi aşıyorsunuz. Kalan limit: ₺' . (50000 - $daily_total)]);
            exit;
        }
        
        // Bekleyen talep kontrolü (aynı yöntemde)
        $pending_sql = "SELECT COUNT(*) 
                       FROM para_yatirma_talepleri 
                       WHERE user_id = ? 
                       AND yontem = ? 
                       AND durum = 'beklemede'";
        $pending_stmt = $conn->prepare($pending_sql);
        $pending_stmt->execute([$user_id, $yontem]);
        $pending_count = $pending_stmt->fetchColumn();
        
        if ($pending_count > 0) {
            echo json_encode(['error' => 'Bu yöntemde bekleyen bir talebiniz bulunuyor. Lütfen onaylanmasını bekleyin.']);
            exit;
        }
        
        try {
            $conn->beginTransaction();
            
            // Para yatırma talebini ekle
            $sql = "INSERT INTO para_yatirma_talepleri 
                    (user_id, yontem, tutar, durum, detay_bilgiler, aciklama) 
                    VALUES (?, ?, ?, 'beklemede', ?, ?)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$user_id, $yontem, $tutar, $detay_bilgiler, $aciklama]);
            
            if (!$result) {
                throw new Exception('Talep eklenemedi');
            }
            
            $request_id = $conn->lastInsertId();
            
            // Kullanıcı işlem geçmişine ekle
            $history_sql = "INSERT INTO kullanici_islem_gecmisi 
                           (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye) 
                           VALUES (?, 'para_yatirma', ?, ?, ?, ?)";
            
            // Mevcut bakiyeyi al
            $balance_sql = "SELECT balance FROM users WHERE id = ?";
            $balance_stmt = $conn->prepare($balance_sql);
            $balance_stmt->execute([$user_id]);
            $current_balance = $balance_stmt->fetchColumn() ?: 0;
            
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->execute([
                $user_id,
                "Para yatırma talebi oluşturuldu - {$yontem} - ₺{$tutar}",
                $tutar,
                $current_balance,
                $current_balance // Henüz onaylanmadığı için bakiye değişmez
            ]);
            
            // Log kaydı
            $log_sql = "INSERT INTO loglar (user_id, tip, detay) VALUES (?, 'para_yatirma', ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->execute([
                $user_id, 
                "Para yatırma talebi: ID:{$request_id}, Yöntem:{$yontem}, Tutar:₺{$tutar}"
            ]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Para yatırma talebiniz başarıyla oluşturuldu',
                'data' => [
                    'id' => $request_id,
                    'yontem' => $yontem,
                    'tutar' => $tutar,
                    'durum' => 'beklemede'
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'İşlem başarısız: ' . $e->getMessage()]);
        }
        break;
        
    case 'detail':
        // Belirli bir para yatırma talebinin detaylarını getir
        $request_id = intval($_GET['request_id'] ?? 0);
        
        if ($request_id <= 0) {
            echo json_encode(['error' => 'Geçersiz talep ID']);
            exit;
        }
        
        $sql = "SELECT 
                    pyt.*,
                    u.username,
                    u.email
                FROM para_yatirma_talepleri pyt
                JOIN users u ON pyt.user_id = u.id
                WHERE pyt.id = ? AND pyt.user_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$request_id, $user_id]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deposit) {
            echo json_encode(['error' => 'Talep bulunamadı']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $deposit]);
        break;
        
    case 'cancel':
        // Bekleyen talebi iptal et
        $request_id = intval($_POST['request_id'] ?? 0);
        
        if ($request_id <= 0) {
            echo json_encode(['error' => 'Geçersiz talep ID']);
            exit;
        }
        
        // Sadece beklemedeki talepleri iptal edebilir
        $check_sql = "SELECT durum FROM para_yatirma_talepleri WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$request_id, $user_id]);
        $status = $check_stmt->fetchColumn();
        
        if (!$status) {
            echo json_encode(['error' => 'Talep bulunamadı']);
            exit;
        }
        
        if ($status !== 'beklemede') {
            echo json_encode(['error' => 'Sadece beklemedeki talepler iptal edilebilir']);
            exit;
        }
        
        try {
            $sql = "UPDATE para_yatirma_talepleri 
                    SET durum = 'reddedildi', 
                        aciklama = CONCAT(COALESCE(aciklama, ''), ' - Kullanıcı tarafından iptal edildi')
                    WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$request_id, $user_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Talep başariyla iptal edildi']);
            } else {
                echo json_encode(['error' => 'Talep iptal edilemedi']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'İşlem başarısız: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Geçersiz işlem']);
        break;
}
?>