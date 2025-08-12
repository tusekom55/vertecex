<?php
// Portfolio sistem test dosyası
session_start();

// Config dosyasını yükle
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Veritabanı bağlantısını test et
    $conn = db_connect();
    
    // Basit test sorgusu
    $test_stmt = $conn->prepare("SELECT 1 as test");
    $test_stmt->execute();
    $test_result = $test_stmt->fetch();
    
    // Tablolar var mı kontrol et
    $tables = ['coins', 'coin_islemleri', 'users'];
    $table_status = [];
    
    foreach ($tables as $table) {
        $check_stmt = $conn->prepare("SHOW TABLES LIKE '" . $table . "'");
        $check_stmt->execute();
        $table_status[$table] = $check_stmt->rowCount() > 0;
    }
    
    // Tek bir JSON response döndür
    echo json_encode([
        'success' => true,
        'message' => 'Portfolio sistem test başarılı',
        'timestamp' => date('Y-m-d H:i:s'),
        'session_info' => [
            'user_id' => $_SESSION['user_id'] ?? 'not_set',
            'session_status' => session_status(),
            'debug_mode' => defined('DEBUG_MODE') ? DEBUG_MODE : false
        ],
        'database_test' => [
            'connection' => 'success',
            'test_query' => $test_result,
            'tables' => $table_status
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Portfolio test hatası: ' . $e->getMessage(),
        'error_details' => [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>
