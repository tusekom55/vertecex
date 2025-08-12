<?php
// Basit invoice test
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? 'test';

try {
    switch ($action) {
        case 'test':
            echo json_encode([
                'success' => true, 
                'message' => 'Basit invoice test başarılı', 
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $action
            ]);
            break;
            
        case 'config_test':
            // Config dosyasını yükle
            require_once __DIR__ . '/../config.php';
            
            if (function_exists('db_connect')) {
                $conn = db_connect();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Config yüklendi ve DB bağlantısı başarılı',
                    'connection_type' => get_class($conn)
                ]);
            } else {
                echo json_encode(['error' => 'db_connect fonksiyonu bulunamadı']);
            }
            break;
            
        case 'user_test':
            require_once __DIR__ . '/../config.php';
            $conn = db_connect();
            
            $result = $conn->query("SELECT COUNT(*) as count FROM users");
            $count = $result->fetch_assoc()['count'];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Kullanıcı tablosu erişimi başarılı',
                'user_count' => $count
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Geçersiz action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Hata: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    echo json_encode([
        'fatal_error' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 