<?php
// Database Schema Güncelleme Script'i
// Para yatırma sistemi için eksik kolonları ekler

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session yönetimi - çakışma önleme
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>🔧 Database Schema Güncelleme</h2>\n";

// Config dosyası path'ini esnek şekilde bulma
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    dirname(__DIR__) . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        echo "📁 Config dosyası bulundu: " . htmlspecialchars($path) . "<br>\n";
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    echo "❌ Config dosyası hiçbir yerde bulunamadı. Aranan yerler:<br>\n";
    foreach ($config_paths as $path) {
        echo "   - " . htmlspecialchars($path) . "<br>\n";
    }
    exit;
}

try {
    $conn = db_connect();
    echo "✅ Database bağlantısı başarılı<br>\n";
    
    // Mevcut kolonları kontrol et
    echo "<h3>1. Mevcut Kolon Kontrolü</h3>\n";
    
    $check_columns = [
        'onay_tarihi' => 'DATETIME NULL',
        'onaylayan_admin_id' => 'INT NULL', 
        'aciklama' => 'TEXT'
    ];
    
    $existing_columns = [];
    
    foreach ($check_columns as $column => $definition) {
        $sql = "SHOW COLUMNS FROM para_yatirma_talepleri LIKE '$column'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "✅ '$column' kolonu zaten mevcut<br>\n";
            $existing_columns[] = $column;
        } else {
            echo "⚠️ '$column' kolonu eksik<br>\n";
        }
    }
    
    // Eksik kolonları ekle
    echo "<h3>2. Eksik Kolonları Ekleme</h3>\n";
    
    $updates = [];
    
    if (!in_array('onay_tarihi', $existing_columns)) {
        $updates[] = "ALTER TABLE para_yatirma_talepleri ADD COLUMN onay_tarihi DATETIME NULL AFTER durum";
    }
    
    if (!in_array('onaylayan_admin_id', $existing_columns)) {
        $updates[] = "ALTER TABLE para_yatirma_talepleri ADD COLUMN onaylayan_admin_id INT NULL AFTER onay_tarihi";
    }
    
    if (!in_array('aciklama', $existing_columns)) {
        $updates[] = "ALTER TABLE para_yatirma_talepleri ADD COLUMN aciklama TEXT AFTER detay_bilgiler";
    }
    
    // Foreign key kontrolü ve ekleme
    $fk_sql = "SELECT COUNT(*) as fk_count 
               FROM information_schema.KEY_COLUMN_USAGE 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'para_yatirma_talepleri' 
               AND REFERENCED_TABLE_NAME = 'users'
               AND COLUMN_NAME = 'onaylayan_admin_id'";
    $fk_stmt = $conn->prepare($fk_sql);
    $fk_stmt->execute();
    $fk_exists = $fk_stmt->fetchColumn() > 0;
    
    if (!$fk_exists && !in_array('onaylayan_admin_id', $existing_columns)) {
        $updates[] = "ALTER TABLE para_yatirma_talepleri ADD FOREIGN KEY (onaylayan_admin_id) REFERENCES users(id) ON DELETE SET NULL";
    }
    
    // Güncellemeleri uygula
    if (empty($updates)) {
        echo "✅ Tüm kolonlar zaten mevcut - güncelleme gerekmiyor<br>\n";
    } else {
        foreach ($updates as $update_sql) {
            try {
                $conn->exec($update_sql);
                echo "✅ Başarılı: " . htmlspecialchars($update_sql) . "<br>\n";
            } catch (PDOException $e) {
                echo "❌ Hata: " . htmlspecialchars($update_sql) . "<br>\n";
                echo "   Detay: " . $e->getMessage() . "<br>\n";
            }
        }
    }
    
    // Sonuç kontrolü
    echo "<h3>3. Final Kontrol</h3>\n";
    $final_sql = "DESCRIBE para_yatirma_talepleri";
    $final_stmt = $conn->prepare($final_sql);
    $final_stmt->execute();
    $columns = $final_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Kolon</th><th>Tip</th><th>Null</th><th>Default</th></tr>\n";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>✅ Schema Güncelleme Tamamlandı!</h3>\n";
    echo "<p>Artık para yatırma sistemi düzgün çalışmalı.</p>\n";
    
} catch (Exception $e) {
    echo "❌ Schema güncelleme hatası: " . $e->getMessage() . "<br>\n";
}
?>