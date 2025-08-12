<?php
// Web tarayıcısından populate test etmek için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Çıktıyı HTML formatında göster
echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>Coin Populate Test</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#00ff00;padding:20px;}</style>";
echo "</head><body>";

echo "<h2>🪙 COIN VERİTABANI POPULATE TEST</h2>";
echo "<hr>";

// Populate script'ini include et ve çalıştır
require_once __DIR__ . '/utils/populate_coins.php';

// Test fonksiyonunu çağır
echo "<h3>🔍 Şema Kontrolü</h3>";
$conn = db_connect();
if (checkSchema($conn)) {
    echo "✅ Şema güncel!<br>";
    
    echo "<h3>🚀 Manuel Coin Ekleme Testi</h3>";
    addManualCoins($conn);
    
} else {
    echo "❌ Şema güncel değil!<br>";
    echo "📋 update_schema.sql dosyasını phpMyAdmin'de çalıştırın.<br>";
}

$conn->close();

echo "<hr>";
echo "<p>Test tamamlandı!</p>";
echo "</body></html>";
?> 