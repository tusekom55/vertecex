<?php
// Basit test dosyası - JSON parse hatasını test etmek için
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
                'message' => 'Basit test başarılı',
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            ]);
            break;
            
        case 'error':
            // Test hatası
            throw new Exception('Test hatası');
            break;
            
        case 'empty':
            // Boş yanıt testi
            echo '';
            break;
            
        case 'invalid_json':
            // Geçersiz JSON testi
            echo 'Bu geçersiz JSON';
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Geçersiz action: ' . $action
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Hata: ' . $e->getMessage()
    ]);
}
?> 