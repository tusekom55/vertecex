<?php
require_once 'backend/config.php';

try {
    $conn = db_connect();
    
    // logo_url kolonunu ekle
    $sql = "ALTER TABLE coins ADD COLUMN logo_url VARCHAR(255) DEFAULT '' AFTER coin_kodu";
    $conn->exec($sql);
    
    echo "✅ logo_url kolonu başarıyla eklendi!\n";
    
    // Kontrol et
    $check_sql = "DESCRIBE coins";
    $stmt = $conn->prepare($check_sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📋 Coins tablosu kolonları:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️ logo_url kolonu zaten mevcut.\n";
    } else {
        echo "❌ Hata: " . $e->getMessage() . "\n";
    }
}
?>
