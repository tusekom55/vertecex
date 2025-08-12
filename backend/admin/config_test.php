<?php
// Config dosyası testi
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    echo json_encode([
        'step' => 1,
        'message' => 'Config test başladı',
        'current_dir' => __DIR__,
        'parent_dir' => dirname(__DIR__)
    ]);
    
    // Config dosyasını kontrol et
    $config_path = __DIR__ . '/../config.php';
    
    if (!file_exists($config_path)) {
        throw new Exception('Config dosyası bulunamadı: ' . $config_path);
    }
    
    echo "\n" . json_encode([
        'step' => 2,
        'message' => 'Config dosyası bulundu',
        'path' => $config_path
    ]);
    
    // Config dosyasını yükle
    require_once $config_path;
    
    echo "\n" . json_encode([
        'step' => 3,
        'message' => 'Config dosyası yüklendi'
    ]);
    
    // Fonksiyonları kontrol et
    if (function_exists('db_connect')) {
        echo "\n" . json_encode([
            'step' => 4,
            'message' => 'db_connect fonksiyonu mevcut'
        ]);
        
        // Veritabanı bağlantısını test et
        $conn = db_connect();
        
        echo "\n" . json_encode([
            'step' => 5,
            'message' => 'Veritabanı bağlantısı başarılı',
            'connection_type' => get_class($conn)
        ]);
        
    } else {
        throw new Exception('db_connect fonksiyonu bulunamadı');
    }
    
    echo "\n" . json_encode([
        'success' => true,
        'message' => 'Tüm testler başarılı'
    ]);
    
} catch (Exception $e) {
    echo "\n" . json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    echo "\n" . json_encode([
        'fatal_error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 