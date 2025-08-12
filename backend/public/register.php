<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Debug için
    error_log("Register attempt - Username: $username, Email: $email");
    
    // Temel validasyon
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı adı zorunludur']);
        exit;
    }
    
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Şifre zorunludur']);
        exit;
    }
    
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Şifre en az 8 karakter olmalıdır']);
        exit;
    }
    
    // Username uzunluk kontrolü
    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı adı en az 3 karakter olmalıdır']);
        exit;
    }
    
    if (strlen($username) > 50) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı adı en fazla 50 karakter olabilir']);
        exit;
    }
    
    // Email validasyonu (eğer girilmişse)
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Geçerli bir email adresi girin']);
        exit;
    }
    
    try {
        if (register_user($username, $email, $password)) {
            echo json_encode(['success' => true, 'message' => 'Kayıt başarılı! Giriş sayfasına yönlendiriliyorsunuz...']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Kayıt başarısız - Beklenmeyen hata']);
        }
    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
}
