<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/security.php';

// Kullanıcı kaydı
function register_user($username, $email, $password) {
    try {
        $conn = db_connect();
        
        // Email boşsa otomatik oluştur
        if (empty($email)) {
            $email = strtolower($username) . '@test.com';
        }
        
        // Username ve email benzersizlik kontrolü
        $check_sql = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$username, $email]);
        
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception('Bu kullanıcı adı veya email zaten kullanılıyor');
        }
        
        // Kullanıcı kaydet (şifre düz metin - test için)
        $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, balance, created_at) VALUES (?, ?, ?, "user", 0, NOW())');
        $result = $stmt->execute([$username, $email, $password]);
        
        if ($result) {
            return true;
        } else {
            throw new Exception('Kayıt işlemi başarısız');
        }
        
    } catch (PDOException $e) {
        error_log('Register error: ' . $e->getMessage());
        throw new Exception('Veritabanı hatası: ' . $e->getMessage());
    } catch (Exception $e) {
        throw $e;
    }
}

// Kullanıcı girişi
function login_user($username, $password) {
    try {
        $conn = db_connect();
        
        $stmt = $conn->prepare('SELECT id, password, role, balance, ad_soyad FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $password === $user['password']) {
            // Session başlat
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $username;
            $_SESSION['balance'] = $user['balance'];
            $_SESSION['ad_soyad'] = $user['ad_soyad'];
            
            // Son giriş zamanını güncelle
            $update_stmt = $conn->prepare('UPDATE users SET son_giris = NOW(), ip_adresi = ? WHERE id = ?');
            $update_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $user['id']]);
            
            // Login log kaydı
            $log_stmt = $conn->prepare('INSERT INTO kullanici_islem_gecmisi (user_id, islem_tipi, islem_detayi, tutar, onceki_bakiye, sonraki_bakiye) VALUES (?, "login", "Kullanıcı giriş yaptı", 0, ?, ?)');
            $log_stmt->execute([$user['id'], $user['balance'], $user['balance']]);
            
            return true;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

// Oturum kontrolü
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Admin kontrolü
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Çıkış
function logout_user() {
    session_destroy();
}
