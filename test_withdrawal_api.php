<?php
// Para çekme talepleri API debug testi
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Para Çekme Talepleri API Debug</h2>\n";

// Test 1: Primary API (withdrawals.php)
echo "<h3>Test 1: Primary API Test</h3>\n";
echo "<strong>URL:</strong> backend/admin/withdrawals.php?action=list<br>\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/backend/admin/withdrawals.php?action=list');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response1 = curl_exec($ch);
$http_code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<strong>HTTP Status:</strong> $http_code1<br>\n";
echo "<strong>Response:</strong> <pre>$response1</pre><br>\n";

// Test 2: Fallback API (test_api.php)
echo "<h3>Test 2: Fallback API Test</h3>\n";
echo "<strong>URL:</strong> backend/admin/test_api.php?action=withdrawals<br>\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost/backend/admin/test_api.php?action=withdrawals');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HEADER, false);
curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
$response2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "<strong>HTTP Status:</strong> $http_code2<br>\n";
echo "<strong>Response:</strong> <pre>$response2</pre><br>\n";

// Test 3: Direct Database Query
echo "<h3>Test 3: Direct Database Query</h3>\n";
try {
    require_once 'backend/config.php';
    
    echo "<strong>Database Connection:</strong> ";
    $conn = db_connect();
    echo "✅ Success<br>\n";
    
    echo "<strong>Table Check:</strong> ";
    $check_sql = "SHOW TABLES LIKE 'para_cekme_talepleri'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute();
    $table_exists = $check_stmt->fetchColumn();
    
    if ($table_exists) {
        echo "✅ Table exists<br>\n";
        
        // Count records
        $count_sql = "SELECT COUNT(*) FROM para_cekme_talepleri";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute();
        $count = $count_stmt->fetchColumn();
        echo "<strong>Record Count:</strong> $count<br>\n";
        
        // Table structure
        $desc_sql = "DESCRIBE para_cekme_talepleri";
        $desc_stmt = $conn->prepare($desc_sql);
        $desc_stmt->execute();
        $columns = $desc_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<strong>Table Structure:</strong><br>\n";
        echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
        foreach ($columns as $col) {
            echo "<tr>\n";
            echo "<td>{$col['Field']}</td>\n";
            echo "<td>{$col['Type']}</td>\n";
            echo "<td>{$col['Null']}</td>\n";
            echo "<td>{$col['Key']}</td>\n";
            echo "<td>{$col['Default']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table><br>\n";
        
        // Sample data
        if ($count > 0) {
            echo "<strong>Sample Data:</strong><br>\n";
            $sample_sql = "SELECT 
                            pct.*, 
                            u.username, u.email
                        FROM para_cekme_talepleri pct
                        JOIN users u ON pct.user_id = u.id
                        ORDER BY pct.tarih DESC
                        LIMIT 3";
            $sample_stmt = $conn->prepare($sample_sql);
            $sample_stmt->execute();
            $samples = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
            if (!empty($samples)) {
                // Header
                echo "<tr>";
                foreach (array_keys($samples[0]) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>\n";
                
                // Data
                foreach ($samples as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>\n";
                }
            }
            echo "</table><br>\n";
        }
        
    } else {
        echo "❌ Table does NOT exist<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>\n";
}

// Test 4: API Simulation
echo "<h3>Test 4: Manual API Simulation</h3>\n";
try {
    $manual_sql = "SELECT 
                    pct.*, 
                    u.username, u.email, u.telefon, u.ad_soyad,
                    a.username as admin_username
                FROM para_cekme_talepleri pct
                JOIN users u ON pct.user_id = u.id
                LEFT JOIN users a ON pct.onaylayan_admin_id = a.id
                ORDER BY pct.tarih DESC";
    
    $manual_stmt = $conn->prepare($manual_sql);
    $manual_stmt->execute();
    $manual_result = $manual_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<strong>Manual Query Result:</strong><br>\n";
    echo "<strong>Count:</strong> " . count($manual_result) . "<br>\n";
    echo "<strong>JSON:</strong> <pre>" . json_encode($manual_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre><br>\n";
    
} catch (Exception $e) {
    echo "❌ Manual Query Error: " . $e->getMessage() . "<br>\n";
}

echo "<hr><h3>Sonuç ve Öneriler:</h3>\n";
echo "<ul>\n";
echo "<li>Primary API çalışıyor mu? " . ($http_code1 == 200 ? "✅" : "❌") . "</li>\n";
echo "<li>Fallback API çalışıyor mu? " . ($http_code2 == 200 ? "✅" : "❌") . "</li>\n";
echo "<li>Database'de veri var mı? " . (isset($count) && $count > 0 ? "✅" : "❌") . "</li>\n";
echo "<li>Manual query çalışıyor mu? " . (isset($manual_result) && count($manual_result) > 0 ? "✅" : "❌") . "</li>\n";
echo "</ul>\n";
?>
