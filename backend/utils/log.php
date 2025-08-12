<?php
// Config.php zaten main dosyada yükleniyor, tekrar yüklemeye gerek yok

function add_log($user_id, $tip, $detay) {
    try {
        $conn = db_connect(); // PDO connection
        $stmt = $conn->prepare('INSERT INTO loglar (user_id, tip, detay) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $tip, $detay]);
    } catch (Exception $e) {
        error_log('Log error: ' . $e->getMessage());
    }
}

function logError($message) {
    error_log($message);
    // Veritabanına log atmayı dene, hata olursa sadece error_log kullan
    try {
        add_log(0, 'error', $message);
    } catch (Exception $e) {
        error_log('Failed to log to database: ' . $e->getMessage());
    }
}

function logActivity($user_id, $message) {
    error_log("Activity - User $user_id: $message");
    // Veritabanına log atmayı dene, hata olursa sadece error_log kullan
    try {
        add_log($user_id, 'activity', $message);
    } catch (Exception $e) {
        error_log('Failed to log activity to database: ' . $e->getMessage());
    }
} 