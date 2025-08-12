<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CORS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config.php';

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Oturum bulunamadı']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    // Config.php'deki db_connect fonksiyonunu kullan
    $pdo = db_connect();
    
    // Admin paneli ile uyumlu para_cekme_talepleri tablosunu kontrol et ve oluştur
    $checkTable = $pdo->query("SHOW TABLES LIKE 'para_cekme_talepleri'");
    if ($checkTable->rowCount() == 0) {
        $createTable = "
        CREATE TABLE para_cekme_talepleri (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            yontem VARCHAR(50) NOT NULL,
            tutar DECIMAL(15,2) NOT NULL,
            banka_adi VARCHAR(100),
            iban VARCHAR(50),
            hesap_sahibi VARCHAR(100),
            papara_no VARCHAR(50),
            detay_bilgiler TEXT,
            aciklama TEXT,
            durum ENUM('beklemede', 'onaylandi', 'reddedildi') DEFAULT 'beklemede',
            tarih TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            onay_tarihi TIMESTAMP NULL,
            onaylayan_admin_id INT NULL,
            admin_aciklama TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (onaylayan_admin_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $pdo->exec($createTable);
    } else {
        // Tablo varsa eksik kolonları kontrol et ve ekle
        $columns = $pdo->query("SHOW COLUMNS FROM para_cekme_talepleri")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('banka_adi', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN banka_adi VARCHAR(100) AFTER tutar");
        }
        if (!in_array('iban', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN iban VARCHAR(50) AFTER banka_adi");
        }
        if (!in_array('hesap_sahibi', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN hesap_sahibi VARCHAR(100) AFTER iban");
        }
        if (!in_array('papara_no', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN papara_no VARCHAR(50) AFTER hesap_sahibi");
        }
        if (!in_array('detay_bilgiler', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN detay_bilgiler TEXT AFTER papara_no");
        }
        if (!in_array('onay_tarihi', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN onay_tarihi TIMESTAMP NULL AFTER tarih");
        }
        if (!in_array('onaylayan_admin_id', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN onaylayan_admin_id INT NULL AFTER onay_tarihi");
        }
        if (!in_array('admin_aciklama', $columns)) {
            $pdo->exec("ALTER TABLE para_cekme_talepleri ADD COLUMN admin_aciklama TEXT AFTER onaylayan_admin_id");
        }
    }

    switch ($action) {
        case 'create':
            // Para çekme talebi oluştur
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Sadece POST metoduna izin verilir');
            }

            $yontem = $_POST['yontem'] ?? '';
            $tutar = floatval($_POST['tutar'] ?? 0);
            $detay_bilgiler = $_POST['detay_bilgiler'] ?? '{}';
            $aciklama = $_POST['aciklama'] ?? '';

            // Validasyon
            if (empty($yontem)) {
                throw new Exception('Çekme yöntemi gereklidir');
            }

            if ($tutar < 100) {
                throw new Exception('Minimum çekme tutarı ₺100\'dür');
            }

            if ($tutar > 50000) {
                throw new Exception('Maksimum çekme tutarı ₺50,000\'dir');
            }

            // Kullanıcı bakiyesini kontrol et
            $userStmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $userStmt->execute([$user_id]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Kullanıcı bulunamadı');
            }

            if ($user['balance'] < $tutar) {
                throw new Exception('Yetersiz bakiye');
            }

            // Detay bilgilerini parse et
            $detay = json_decode($detay_bilgiler, true);
            $banka_adi = '';
            $iban = '';
            $hesap_sahibi = '';
            $papara_no = '';
            
            if ($yontem === 'bank_transfer') {
                $banka_adi = $detay['bank_name'] ?? '';
                $iban = $detay['iban_number'] ?? '';
                $hesap_sahibi = $detay['recipient_name'] ?? '';
            } elseif ($yontem === 'papara') {
                $papara_no = $detay['papara_number'] ?? '';
                $hesap_sahibi = $detay['account_name'] ?? '';
            }

            // Admin paneli ile uyumlu para_cekme_talepleri tablosuna kaydet
            $stmt = $pdo->prepare("
                INSERT INTO para_cekme_talepleri (
                    user_id, yontem, tutar, banka_adi, iban, hesap_sahibi, papara_no, detay_bilgiler, aciklama
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $user_id, 
                $yontem, 
                $tutar, 
                $banka_adi,
                $iban,
                $hesap_sahibi,
                $papara_no,
                $detay_bilgiler, 
                $aciklama
            ]);

            if ($result) {
                $withdrawal_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Para çekme talebi başarıyla oluşturuldu',
                    'data' => [
                        'id' => $withdrawal_id,
                        'tutar' => $tutar,
                        'yontem' => $yontem,
                        'durum' => 'beklemede'
                    ]
                ]);
            } else {
                throw new Exception('Para çekme talebi oluşturulamadı');
            }
            break;

        case 'list':
            // Kullanıcının para çekme taleplerini listele (admin paneli ile uyumlu)
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    yontem,
                    tutar,
                    banka_adi,
                    iban,
                    hesap_sahibi,
                    papara_no,
                    detay_bilgiler,
                    aciklama,
                    durum,
                    tarih,
                    onay_tarihi,
                    admin_aciklama
                FROM para_cekme_talepleri 
                WHERE user_id = ? 
                ORDER BY tarih DESC 
                LIMIT 20
            ");
            
            $stmt->execute([$user_id]);
            $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // JSON formatı düzenle
            foreach ($withdrawals as &$withdrawal) {
                $withdrawal['tutar'] = floatval($withdrawal['tutar']);
                $withdrawal['tarih_formatted'] = date('d.m.Y H:i', strtotime($withdrawal['tarih']));
                
                if ($withdrawal['onay_tarihi']) {
                    $withdrawal['onay_tarihi_formatted'] = date('d.m.Y H:i', strtotime($withdrawal['onay_tarihi']));
                }
            }

            echo json_encode([
                'success' => true,
                'data' => $withdrawals,
                'count' => count($withdrawals)
            ]);
            break;

        case 'status':
            // Belirli bir talebin durumunu getir (admin paneli ile uyumlu)
            $withdrawal_id = $_GET['id'] ?? 0;
            
            if (!$withdrawal_id) {
                throw new Exception('Talep ID gereklidir');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    yontem,
                    tutar,
                    banka_adi,
                    iban,
                    hesap_sahibi,
                    papara_no,
                    detay_bilgiler,
                    aciklama,
                    durum,
                    tarih,
                    onay_tarihi,
                    admin_aciklama
                FROM para_cekme_talepleri 
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$withdrawal_id, $user_id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$withdrawal) {
                throw new Exception('Talep bulunamadı');
            }

            $withdrawal['tutar'] = floatval($withdrawal['tutar']);
            $withdrawal['tarih_formatted'] = date('d.m.Y H:i', strtotime($withdrawal['tarih']));
            
            if ($withdrawal['onay_tarihi']) {
                $withdrawal['onay_tarihi_formatted'] = date('d.m.Y H:i', strtotime($withdrawal['onay_tarihi']));
            }

            echo json_encode([
                'success' => true,
                'data' => $withdrawal
            ]);
            break;

        case 'cancel':
            // Para çekme talebini iptal et
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Sadece POST metoduna izin verilir');
            }

            $withdrawal_id = $_POST['withdrawal_id'] ?? 0;

            if (!$withdrawal_id) {
                throw new Exception('Talep ID gereklidir');
            }

            // Kullanıcının beklemede olan talebini kontrol et
            $stmt = $pdo->prepare("
                SELECT * FROM para_cekme_talepleri 
                WHERE id = ? AND user_id = ? AND durum = 'beklemede'
            ");
            $stmt->execute([$withdrawal_id, $user_id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$withdrawal) {
                throw new Exception('İptal edilebilir talep bulunamadı. Sadece beklemede olan talepler iptal edilebilir.');
            }

            // Talebi iptal et (sil)
            $stmt = $pdo->prepare("DELETE FROM para_cekme_talepleri WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$withdrawal_id, $user_id]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Para çekme talebi başarıyla iptal edildi',
                    'data' => [
                        'cancelled_amount' => floatval($withdrawal['tutar']),
                        'cancelled_method' => $withdrawal['yontem']
                    ]
                ]);
            } else {
                throw new Exception('Talep iptal edilemedi');
            }
            break;

        default:
            throw new Exception('Geçersiz işlem');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>
