<?php
// Hosting ortamında hata ayıklama
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'php_info':
            // PHP bilgilerini kontrol et
            $info = [
                'php_version' => PHP_VERSION,
                'extensions' => get_loaded_extensions(),
                'error_reporting' => error_reporting(),
                'display_errors' => ini_get('display_errors'),
                'log_errors' => ini_get('log_errors'),
                'error_log' => ini_get('error_log'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize')
            ];
            echo json_encode(['success' => true, 'php_info' => $info]);
            break;
            
        case 'file_check':
            // Dosya varlığını kontrol et
            $files = [
                'config.php' => file_exists('../config.php'),
                'security.php' => file_exists('../utils/security.php'),
                'invoice.php' => file_exists('invoice.php'),
                'current_dir' => __DIR__,
                'parent_dir' => dirname(__DIR__)
            ];
            echo json_encode(['success' => true, 'files' => $files]);
            break;
            
        case 'db_test':
            // Veritabanı bağlantısını test et
            try {
                require_once '../config.php';
                $conn = db_connect();
                echo json_encode(['success' => true, 'message' => 'Veritabanı bağlantısı başarılı']);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Veritabanı hatası: ' . $e->getMessage()]);
            }
            break;
            
        case 'table_check':
            // Tabloları kontrol et
            try {
                require_once '../config.php';
                $conn = db_connect();
                
                $tables = ['users', 'faturalar', 'sistem_ayarlari', 'para_cekme_talepleri'];
                $table_status = [];
                
                foreach ($tables as $table) {
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    $table_status[$table] = $result->num_rows > 0;
                }
                
                echo json_encode(['success' => true, 'tables' => $table_status]);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Tablo kontrolü hatası: ' . $e->getMessage()]);
            }
            break;
            
        case 'user_check':
            // Kullanıcıları kontrol et
            try {
                require_once '../config.php';
                $conn = db_connect();
                
                $result = $conn->query("SELECT COUNT(*) as count FROM users");
                $user_count = $result->fetch_assoc()['count'];
                
                $result = $conn->query("SELECT id, username, email FROM users LIMIT 5");
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                
                echo json_encode([
                    'success' => true, 
                    'user_count' => $user_count,
                    'users' => $users
                ]);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Kullanıcı kontrolü hatası: ' . $e->getMessage()]);
            }
            break;
            
        case 'simple_test':
            // Basit test
            echo json_encode(['success' => true, 'message' => 'Debug API çalışıyor', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
            
        default:
            echo json_encode(['error' => 'Geçersiz action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Genel hata: ' . $e->getMessage()]);
}
?> 