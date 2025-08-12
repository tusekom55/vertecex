<?php
// IBAN sütunu ekleme scripti
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Config dosyasını yükle
    require_once 'config.php';
    
    $conn = db_connect();
    
    echo json_encode(['step' => 1, 'message' => 'Veritabanı bağlantısı başarılı']);
    
    // IBAN sütunu var mı kontrol et
    $check_sql = "SHOW COLUMNS FROM users LIKE 'iban'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        echo "\n" . json_encode(['step' => 2, 'message' => 'IBAN sütunu zaten mevcut']);
    } else {
        echo "\n" . json_encode(['step' => 2, 'message' => 'IBAN sütunu yok, ekleniyor...']);
        
        // IBAN sütunu ekle
        $alter_sql = "ALTER TABLE `users` ADD COLUMN `iban` VARCHAR(50) DEFAULT NULL AFTER `tc_no`";
        if ($conn->query($alter_sql)) {
            echo "\n" . json_encode(['step' => 3, 'message' => 'IBAN sütunu başarıyla eklendi']);
            
            // Mevcut kullanıcılara IBAN'lar ekle
            $updates = [
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 44' WHERE `id` = 11",
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 45' WHERE `id` = 12", 
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 46' WHERE `id` = 13",
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 47' WHERE `id` = 6",
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 48' WHERE `id` = 1",
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 49' WHERE `id` = 2",
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 50' WHERE `id` = 3",
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 51' WHERE `id` = 4",
                "UPDATE `users` SET `iban` = 'TR63 0006 4000 0019 3001 9751 52' WHERE `id` = 5"
            ];
            
            $updated_count = 0;
            foreach ($updates as $update_sql) {
                if ($conn->query($update_sql)) {
                    $updated_count++;
                }
            }
            
            echo "\n" . json_encode(['step' => 4, 'message' => "$updated_count kullanıcıya IBAN eklendi"]);
        } else {
            throw new Exception('IBAN sütunu eklenemedi: ' . $conn->error);
        }
    }
    
    // Test sorgusu çalıştır
    $test_sql = "SELECT id, username, email, ad_soyad, tc_no, iban FROM users LIMIT 3";
    $test_result = $conn->query($test_sql);
    
    $users = [];
    while ($row = $test_result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo "\n" . json_encode([
        'success' => true,
        'message' => 'IBAN sütunu işlemi tamamlandı',
        'test_users' => $users
    ]);
    
} catch (Exception $e) {
    echo "\n" . json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 