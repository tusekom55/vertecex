<?php
/**
 * Admin Session Kontrolü
 */

session_start();
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Session kontrolü
    if (!is_logged_in()) {
        echo json_encode([
            'success' => false,
            'is_admin' => false,
            'message' => 'Oturum bulunamadı'
        ]);
        exit;
    }
    
    // Admin kontrolü
    if (!is_admin()) {
        echo json_encode([
            'success' => false,
            'is_admin' => false,
            'message' => 'Admin yetkisi yok'
        ]);
        exit;
    }
    
    // Admin bilgilerini döndür
    echo json_encode([
        'success' => true,
        'is_admin' => true,
        'admin_info' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'is_admin' => false,
        'error' => $e->getMessage()
    ]);
}
?>
