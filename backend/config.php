<?php
// Oturum yönetimi - çakışmaları önle
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Veritabanı bağlantı ayarları
$DB_HOST = 'localhost';
$DB_USER = 'u225998063_yenip';
$DB_PASS = '123456Tubb';
$DB_NAME = 'u225998063_yenip';

// PDO Bağlantı fonksiyonu (hata kontrolü ile)
function db_connect() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    try {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $conn = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        return $conn;
        
    } catch (PDOException $e) {
        // Production'da gerçek hata mesajını gösterme
        error_log('Database connection error: ' . $e->getMessage());
        
        // Test modu için detaylı hata, production'da generic mesaj
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            die(json_encode(['error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()]));
        } else {
            die(json_encode(['error' => 'Veritabanı bağlantısı kurulamadı']));
        }
    }
}

// MySQLi bağlantı değişkeni (global kullanım için)
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// MySQLi bağlantı kontrolü
if ($conn->connect_error) {
    error_log('MySQLi connection error: ' . $conn->connect_error);
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        die(json_encode(['error' => 'MySQLi bağlantı hatası: ' . $conn->connect_error]));
    } else {
        die(json_encode(['error' => 'Veritabanı bağlantısı kurulamadı']));
    }
}

// UTF-8 karakter seti ayarla
$conn->set_charset("utf8mb4");

// Debug modu (production'da false yapın)
define('DEBUG_MODE', true); // Test için geçici olarak true

// Error reporting - JSON API'leri için kapalı
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Sadece debug modunda test session değerleri
if (!isset($_SESSION['user_id']) && defined('DEBUG_MODE') && DEBUG_MODE) {
    $_SESSION['user_id'] = 1; // Test kullanıcısı
    $_SESSION['role'] = 'user';
}
