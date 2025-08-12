<?php
session_start();
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
        // Para çekme taleplerini listele
        $sql = "SELECT 
                    pct.*, 
                    u.username, u.email, u.telefon, u.ad_soyad,
                    a.username as admin_username
                FROM para_cekme_talepleri pct
                JOIN users u ON pct.user_id = u.id
                LEFT JOIN users a ON pct.onaylayan_admin_id = a.id
                ORDER BY pct.tarih DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $withdrawals]);
        break;
        
    case 'approve':
        // Para çekme talebini onayla
        $withdrawal_id = $_POST['withdrawal_id'] ?? 0;
        $aciklama = $_POST['aciklama'] ?? '';
        
        // Talebi getir
        $sql = "SELECT * FROM para_cekme_talepleri WHERE id = ? AND durum = 'beklemede'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$withdrawal_id]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$withdrawal) {
            echo json_encode(['error' => 'Talep bulunamadı veya zaten işlenmiş']);
            exit;
        }
        
        // Kullanıcının bakiyesini kontrol et
        $sql = "SELECT balance FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$withdrawal['user_id']]);
        $user_balance = $stmt->fetchColumn();
        
        if ($user_balance < $withdrawal['tutar']) {
            echo json_encode(['error' => 'Kullanıcının yeterli bakiyesi yok']);
            exit;
        }
        
        // Transaction başlat
        $conn->beginTransaction();
        
        try {
            // Talebi onayla
            $sql = "UPDATE para_cekme_talepleri SET 
                    durum = 'onaylandi', 
                    onay_tarihi = NOW(), 
                    onaylayan_admin_id = ?,
                    aciklama = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['user_id'] ?? 1, $aciklama, $withdrawal_id]);
            
            // Kullanıcının bakiyesini güncelle
            $sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$withdrawal['tutar'], $withdrawal['user_id']]);
            
            // İşlem geçmişine ekle
            $sql = "INSERT INTO kullanici_islem_gecmisi 
                    (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye) 
                    VALUES (?, 'para_cekme', ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $withdrawal['user_id'],
                "Para çekme onaylandı - {$withdrawal['yontem']}",
                $withdrawal['tutar'],
                $user_balance,
                $user_balance - $withdrawal['tutar']
            ]);
            
            // Admin log kaydı
            $sql = "INSERT INTO admin_islem_loglari 
                    (admin_id, islem_tipi, hedef_id, islem_detayi) 
                    VALUES (?, 'para_onaylama', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $_SESSION['user_id'], 
                $withdrawal_id, 
                "Para çekme onaylandı: {$withdrawal['tutar']} TL"
            ]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Para çekme talebi onaylandı']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'İşlem başarısız: ' . $e->getMessage()]);
        }
        break;
        
    case 'reject':
        // Para çekme talebini reddet
        $withdrawal_id = $_POST['withdrawal_id'] ?? 0;
        $aciklama = $_POST['aciklama'] ?? '';
        
        // Talebi getir
        $sql = "SELECT * FROM para_cekme_talepleri WHERE id = ? AND durum = 'beklemede'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$withdrawal_id]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$withdrawal) {
            echo json_encode(['error' => 'Talep bulunamadı veya zaten işlenmiş']);
            exit;
        }
        
        // Talebi reddet
        $sql = "UPDATE para_cekme_talepleri SET 
                durum = 'reddedildi', 
                onay_tarihi = NOW(), 
                onaylayan_admin_id = ?,
                aciklama = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([$_SESSION['user_id'], $aciklama, $withdrawal_id]);
        
        if ($result) {
            // Admin log kaydı
            $sql = "INSERT INTO admin_islem_loglari 
                    (admin_id, islem_tipi, hedef_id, islem_detayi) 
                    VALUES (?, 'para_onaylama', ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $_SESSION['user_id'] ?? 1, 
                $withdrawal_id, 
                "Para çekme reddedildi: {$withdrawal['tutar']} TL"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Para çekme talebi reddedildi']);
        } else {
            echo json_encode(['error' => 'İşlem başarısız']);
        }
        break;
        
    case 'detail':
        // Talep detayları
        $withdrawal_id = $_GET['withdrawal_id'] ?? 0;
        
        $sql = "SELECT 
                    pct.*, 
                    u.username, u.email, u.telefon, u.ad_soyad, u.balance,
                    a.username as admin_username
                FROM para_cekme_talepleri pct
                JOIN users u ON pct.user_id = u.id
                LEFT JOIN users a ON pct.onaylayan_admin_id = a.id
                WHERE pct.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$withdrawal_id]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$withdrawal) {
            echo json_encode(['error' => 'Talep bulunamadı']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => $withdrawal]);
        break;
        
    default:
        echo json_encode(['error' => 'Geçersiz işlem']);
        break;
}
?>
