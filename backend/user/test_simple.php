<?php
// Basit test API'si
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

ob_clean();

try {
    error_log('Test API called');
    
    // Basit test
    echo json_encode([
        'success' => true,
        'message' => 'Test API working',
        'method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Test API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Test failed: ' . $e->getMessage()]);
}
?>