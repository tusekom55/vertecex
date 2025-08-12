<?php
// Session yönetimi - çakışma önleme
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Giriş gerekli']);
    exit;
}

try {
    $conn = db_connect();
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare('SELECT id, username, email, role, balance, ad_soyad, telefon, created_at, son_giris FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Session'daki balance'ı güncel tutmak için güncelle
        $_SESSION['balance'] = $user['balance'];
        
        echo json_encode([
            'success' => true, 
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'balance' => floatval($user['balance']),
                'ad_soyad' => $user['ad_soyad'],
                'telefon' => $user['telefon'],
                'created_at' => $user['created_at'],
                'son_giris' => $user['son_giris']
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
    }
    
} catch (PDOException $e) {
    error_log('Profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası']);
} 