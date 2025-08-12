<?php
// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Debug log
error_log("Invoice API called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET data: " . print_r($_GET, true));
error_log("POST data: " . print_r($_POST, true));

try {
    // Dosya yollarını kontrol et
    $config_path = __DIR__ . '/../config.php';
    $security_path = __DIR__ . '/../utils/security.php';
    
    error_log("Config path: " . $config_path);
    error_log("Security path: " . $security_path);
    error_log("Config exists: " . (file_exists($config_path) ? 'yes' : 'no'));
    error_log("Security exists: " . (file_exists($security_path) ? 'yes' : 'no'));
    
    if (!file_exists($config_path)) {
        throw new Exception('config.php dosyası bulunamadı: ' . $config_path);
    }
    
    if (!file_exists($security_path)) {
        throw new Exception('security.php dosyası bulunamadı: ' . $security_path);
    }
    
    require_once $config_path;
    require_once $security_path;
    
    error_log("Dosyalar başarıyla yüklendi");
    
    // Fonksiyon kontrolü
    if (!function_exists('db_connect')) {
        throw new Exception('db_connect fonksiyonu bulunamadı');
    }
    
    error_log("db_connect fonksiyonu mevcut");
    
} catch (Exception $e) {
    error_log("Dosya yükleme hatası: " . $e->getMessage());
    echo json_encode(['error' => 'Dosya yükleme hatası: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    error_log("PHP Fatal Error: " . $e->getMessage());
    echo json_encode(['error' => 'PHP Fatal Error: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'test':
            // API bağlantı testi
            error_log("Test action called");
            echo json_encode(['success' => true, 'message' => 'API bağlantısı başarılı', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
        
        case 'test_db':
            // Veritabanı bağlantı testi
            try {
                $conn = db_connect();
                echo json_encode(['success' => true, 'message' => 'Veritabanı bağlantısı başarılı', 'timestamp' => date('Y-m-d H:i:s')]);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Veritabanı bağlantısı başarısız: ' . $e->getMessage()]);
            }
            break;
            
        case 'test_user':
            // Kullanıcı testi
            $user_id = $_GET['user_id'] ?? 2; // Default test user
            try {
                $conn = db_connect();
                $sql = "SELECT id, username, email, ad_soyad, tc_no, iban FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if ($user) {
                    echo json_encode(['success' => true, 'message' => 'Kullanıcı bulundu', 'user' => $user]);
                } else {
                    echo json_encode(['error' => 'Kullanıcı bulunamadı (ID: ' . $user_id . ')']);
                }
            } catch (Exception $e) {
                echo json_encode(['error' => 'Kullanıcı sorgusu başarısız: ' . $e->getMessage()]);
            }
            break;
            
        case 'create':
            try {
                error_log("=== CREATE CASE STARTED ===");
                
                // JSON verilerini al
                $raw_input = file_get_contents('php://input');
                error_log("Raw input: " . $raw_input);
                
                $input = json_decode($raw_input, true);
                error_log("Decoded input: " . print_r($input, true));
                
                $user_id = $input['user_id'] ?? $_POST['user_id'] ?? 2; // Default test user
                $islem_tipi = $input['islem_tipi'] ?? $_POST['islem_tipi'] ?? 'para_cekme';
                $islem_id = $input['islem_id'] ?? $_POST['islem_id'] ?? 1;
                $tutar = $input['tutar'] ?? $_POST['tutar'] ?? 100.00;
                
                error_log("Creating invoice with data: user_id=$user_id, islem_tipi=$islem_tipi, islem_id=$islem_id, tutar=$tutar");
                
                // Parametreleri kontrol et
                if (empty($user_id) || empty($islem_tipi) || empty($tutar)) {
                    throw new Exception('Eksik parametreler: user_id, islem_tipi ve tutar gerekli');
                }
                
                // Veritabanı bağlantısını oluştur
                error_log("Attempting database connection...");
                $conn = db_connect();
                error_log("Database connection successful");
                
                // Kullanıcı bilgilerini al
                $sql = "SELECT id, username, email, ad_soyad, tc_no, iban FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('User query prepare failed: ' . $conn->error);
                }
                
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if (!$user) {
                    throw new Exception('Kullanıcı bulunamadı (ID: ' . $user_id . ')');
                }
                
                error_log("User found: " . print_r($user, true));
                
                // Fatura numarası oluştur
                $fatura_no = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Faturayı veritabanına kaydet
                $sql = "INSERT INTO faturalar (user_id, islem_tipi, islem_id, fatura_no, tutar, toplam_tutar) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Invoice insert prepare failed: ' . $conn->error);
                }
                
                $stmt->bind_param('isisdd', $user_id, $islem_tipi, $islem_id, $fatura_no, $tutar, $tutar);
                
                if ($stmt->execute()) {
                    $fatura_id = $conn->insert_id;
                    
                    $fatura_data = [
                        'id' => $fatura_id,
                        'fatura_no' => $fatura_no,
                        'user_id' => $user_id,
                        'tutar' => $tutar,
                        'tarih' => date('Y-m-d H:i:s')
                    ];
                    
                    error_log("Invoice created successfully: " . print_r($fatura_data, true));
                    echo json_encode(['success' => true, 'data' => $fatura_data]);
                } else {
                    error_log("Invoice insert failed: " . $stmt->error);
                    echo json_encode(['error' => 'Fatura oluşturulamadı: ' . $stmt->error]);
                }
                
            } catch (Exception $e) {
                error_log("Create case error: " . $e->getMessage());
                echo json_encode(['error' => 'Create hatası: ' . $e->getMessage()]);
            }
            break;
            
        default:
            error_log("Geçersiz action: " . $action);
            echo json_encode(['error' => 'Geçersiz işlem: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Invoice API genel hata: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['error' => 'Sistem hatası: ' . $e->getMessage()]);
}
?> 