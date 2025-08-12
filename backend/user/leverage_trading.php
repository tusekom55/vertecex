<?php
// Hata raporlamayı kapat (JSON çıktısını bozmasın)
error_reporting(0);
ini_set('display_errors', 0);

// Output buffering başlat (beklenmeyen çıktıları yakala)
ob_start();

// Step by step debug
error_log('Step 1: Starting leverage_trading.php');

try {
    require_once '../config.php';
    error_log('Step 2: Config.php loaded successfully');
} catch (Exception $e) {
    error_log('Config.php error: ' . $e->getMessage());
    ob_clean();
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Config load failed']));
}

try {
    require_once '../utils/security.php';
    error_log('Step 3: Security.php loaded successfully');
} catch (Exception $e) {
    error_log('Security.php error: ' . $e->getMessage());
    ob_clean();
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Security load failed']));
}

try {
    require_once '../utils/log.php';
    error_log('Step 4: Log.php loaded successfully');
} catch (Exception $e) {
    error_log('Log.php error: ' . $e->getMessage());
    ob_clean();
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Log load failed']));
}

// Buffer'ı temizle ve header'ları ayarla
ob_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

error_log('Step 5: All includes loaded, buffer cleaned and headers set');

// Session tabanlı authentication check
// session_start(); // Config.php'de zaten başlatılıyor

// Debug: Session bilgilerini log'la
error_log('Session kontrol: ' . print_r($_SESSION, true));
error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Request URI: ' . $_SERVER['REQUEST_URI']);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    error_log('Session authentication failed');
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required - Please login']);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log('Step 6: User authenticated, user_id: ' . $user_id);

// Database connection test
try {
    global $conn;
    if (!$conn) {
        throw new Exception('MySQLi connection not available');
    }
    
    // Test query
    $test_result = $conn->query("SELECT 1");
    if (!$test_result) {
        throw new Exception('Database test query failed: ' . $conn->error);
    }
    error_log('Step 7: Database connection test successful');
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Admin kontrolü (opsiyonel)
if ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    error_log('Method: ' . $method);
    
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        error_log('POST input: ' . $input);
        $data = json_decode($input, true);
        error_log('Decoded data: ' . print_r($data, true));
    }
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'positions':
                        getOpenPositions($user_id);
                        break;
                    case 'position_history':
                        getPositionHistory($user_id);
                        break;
                    case 'leverage_settings':
                        getLeverageSettings($user_id);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                }
            } else {
                getOpenPositions($user_id);
            }
            break;
            
        case 'POST':
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'open_position':
                        openPosition($user_id, $data);
                        break;
                    case 'close_position':
                        closePosition($user_id, $data);
                        break;
                    case 'update_prices':
                        updatePositionPrices($user_id, $data);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid action']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Action required']);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            updateLeverageSettings($user_id, $input);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("LEVERAGE TRADING ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    logError("Leverage trading error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

// Açık pozisyonları getir
function getOpenPositions($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT lp.*, 
               COALESCE(c.current_price, lp.entry_price) as market_price,
               (CASE 
                   WHEN lp.position_type = 'long' THEN 
                       ((COALESCE(c.current_price, lp.entry_price) - lp.entry_price) / lp.entry_price) * lp.invested_amount * lp.leverage_ratio
                   ELSE 
                       ((lp.entry_price - COALESCE(c.current_price, lp.entry_price)) / lp.entry_price) * lp.invested_amount * lp.leverage_ratio
               END) as unrealized_pnl,
               (CASE 
                   WHEN lp.position_type = 'long' THEN 
                       ((COALESCE(c.current_price, lp.entry_price) - lp.entry_price) / lp.entry_price) * 100 * lp.leverage_ratio
                   ELSE 
                       ((lp.entry_price - COALESCE(c.current_price, lp.entry_price)) / lp.entry_price) * 100 * lp.leverage_ratio
               END) as pnl_percentage
        FROM leverage_positions lp
        LEFT JOIN coins c ON lp.coin_symbol = c.coin_kodu
        WHERE lp.user_id = ? AND lp.status = 'open'
        ORDER BY lp.created_at DESC
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $positions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Update unrealized PnL in database
    foreach ($positions as $position) {
        updatePositionPnL($position['id'], $position['unrealized_pnl'], $position['market_price']);
    }
    
    echo json_encode(['success' => true, 'positions' => $positions]);
}

// Pozisyon geçmişini getir
function getPositionHistory($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT lp.*, ph.action_type, ph.timestamp as action_timestamp
        FROM leverage_positions lp
        LEFT JOIN position_history ph ON lp.id = ph.position_id
        WHERE lp.user_id = ?
        ORDER BY lp.created_at DESC, ph.timestamp DESC
        LIMIT 100
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
}

// Kaldıraç ayarlarını getir
function getLeverageSettings($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM leverage_settings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
    
    if (!$settings) {
        // Default settings oluştur
        $stmt = $conn->prepare("
            INSERT INTO leverage_settings (user_id, max_leverage, default_leverage, auto_close_enabled, max_loss_percentage)
            VALUES (?, 10.0, 1.0, TRUE, 80.00)
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $settings = [
            'user_id' => $user_id,
            'max_leverage' => 10.0,
            'default_leverage' => 1.0,
            'auto_close_enabled' => true,
            'max_loss_percentage' => 80.00
        ];
    }
    
    echo json_encode(['success' => true, 'settings' => $settings]);
}

// Pozisyon aç
function openPosition($user_id, $data) {
    global $conn;
    
    error_log("openPosition called with user_id: $user_id");
    error_log("openPosition data: " . print_r($data, true));
    
    $coin_symbol = $data['coin_symbol'] ?? '';
    $position_type = $data['position_type'] ?? 'long'; // long or short
    $leverage_ratio = floatval($data['leverage_ratio'] ?? 1.0);
    $invested_amount = floatval($data['invested_amount'] ?? 0);
    $entry_price = floatval($data['entry_price'] ?? 0);
    
    error_log("Parsed values: symbol=$coin_symbol, type=$position_type, leverage=$leverage_ratio, amount=$invested_amount, price=$entry_price");
    
    // Validations
    if (empty($coin_symbol) || $invested_amount <= 0 || $entry_price <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }
    
    if (!in_array($leverage_ratio, [1.0, 2.0, 5.0, 10.0])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid leverage ratio']);
        return;
    }
    
    // Kullanıcının bakiyesini kontrol et
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user || $user['balance'] < $invested_amount) {
        http_response_code(400);
        echo json_encode(['error' => 'Insufficient balance']);
        return;
    }
    
    // Pozisyon büyüklüğünü hesapla (DÜZELTME: Doğru formül)
    $position_size = ($invested_amount * $leverage_ratio) / $entry_price;
    
    // Liquidation price hesapla (DÜZELTME: Kaldıraç etkisi dahil)
    $liquidation_percentage = 0.8; // %80 zarar durumunda liquidation
    $margin_call_threshold = $liquidation_percentage / $leverage_ratio; // Kaldıraç oranına göre threshold
    
    if ($position_type === 'long') {
        // Long pozisyon: Fiyat düştüğünde liquidation
        $liquidation_price = $entry_price * (1 - $margin_call_threshold);
    } else {
        // Short pozisyon: Fiyat yükseldiğinde liquidation  
        $liquidation_price = $entry_price * (1 + $margin_call_threshold);
    }
    
    $conn->begin_transaction();
    
    try {
        // Kullanıcının bakiyesinden düş
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $invested_amount, $user_id);
        $stmt->execute();
        
        // Pozisyon oluştur
        $stmt = $conn->prepare("
            INSERT INTO leverage_positions 
            (user_id, coin_symbol, position_type, leverage_ratio, entry_price, position_size, invested_amount, current_price, liquidation_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issdddddd", $user_id, $coin_symbol, $position_type, $leverage_ratio, $entry_price, $position_size, $invested_amount, $entry_price, $liquidation_price);
        $stmt->execute();
        
        $position_id = $conn->insert_id;
        
        // Position history'ye kaydet
        $stmt = $conn->prepare("
            INSERT INTO position_history (position_id, user_id, action_type, price)
            VALUES (?, ?, 'open', ?)
        ");
        $stmt->bind_param("iis", $position_id, $user_id, $entry_price);
        $stmt->execute();
        
        $conn->commit();
        
        logActivity($user_id, "Opened leverage position: $coin_symbol $position_type {$leverage_ratio}x");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Position opened successfully',
            'position_id' => $position_id,
            'position_size' => $position_size,
            'liquidation_price' => $liquidation_price
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Pozisyon kapat
function closePosition($user_id, $data) {
    global $conn;
    
    $position_id = intval($data['position_id'] ?? 0);
    $close_price = floatval($data['close_price'] ?? 0);
    
    if ($position_id <= 0 || $close_price <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }
    
    // Pozisyonu getir
    $stmt = $conn->prepare("
        SELECT * FROM leverage_positions 
        WHERE id = ? AND user_id = ? AND status = 'open'
    ");
    $stmt->bind_param("ii", $position_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $position = $result->fetch_assoc();
    
    if (!$position) {
        http_response_code(404);
        echo json_encode(['error' => 'Position not found']);
        return;
    }
    
    // PnL hesapla (DÜZELTME: Kaldıraç etkisi dahil)
    $price_change_percentage = 0;
    if ($position['position_type'] === 'long') {
        $price_change_percentage = ($close_price - $position['entry_price']) / $position['entry_price'];
    } else {
        $price_change_percentage = ($position['entry_price'] - $close_price) / $position['entry_price'];
    }
    
    // Kaldıraçlı PnL = Fiyat değişim yüzdesi × Yatırılan miktar × Kaldıraç oranı
    $pnl = $price_change_percentage * $position['invested_amount'] * $position['leverage_ratio'];
    
    $final_amount = $position['invested_amount'] + $pnl;
    
    $conn->begin_transaction();
    
    try {
        // Pozisyonu kapat
        $stmt = $conn->prepare("
            UPDATE leverage_positions 
            SET status = 'closed', close_price = ?, realized_pnl = ?, closed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ddi", $close_price, $pnl, $position_id);
        $stmt->execute();
        
        // Kullanıcının bakiyesine ekle
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $final_amount, $user_id);
        $stmt->execute();
        
        // Position history'ye kaydet
        $stmt = $conn->prepare("
            INSERT INTO position_history (position_id, user_id, action_type, price, pnl)
            VALUES (?, ?, 'close', ?, ?)
        ");
        $stmt->bind_param("iisd", $position_id, $user_id, $close_price, $pnl);
        $stmt->execute();
        
        $conn->commit();
        
        logActivity($user_id, "Closed leverage position: {$position['coin_symbol']} PnL: $pnl");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Position closed successfully',
            'pnl' => $pnl,
            'final_amount' => $final_amount
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Pozisyon fiyatlarını güncelle
function updatePositionPrices($user_id, $data) {
    global $conn;
    
    $prices = $data['prices'] ?? [];
    
    foreach ($prices as $coin_symbol => $current_price) {
        // Açık pozisyonları güncelle
        $stmt = $conn->prepare("
            UPDATE leverage_positions 
            SET current_price = ?,
                unrealized_pnl = CASE 
                    WHEN position_type = 'long' THEN 
                        (? - entry_price) * position_size
                    ELSE 
                        (entry_price - ?) * position_size
                END
            WHERE user_id = ? AND coin_symbol = ? AND status = 'open'
        ");
        $stmt->bind_param("dddis", $current_price, $current_price, $current_price, $user_id, $coin_symbol);
        $stmt->execute();
        
        // Liquidation kontrolü
        checkLiquidation($user_id, $coin_symbol, $current_price);
    }
    
    echo json_encode(['success' => true, 'message' => 'Prices updated']);
}

// Pozisyon PnL güncelle
function updatePositionPnL($position_id, $unrealized_pnl, $current_price) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE leverage_positions 
        SET unrealized_pnl = ?, current_price = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("ddi", $unrealized_pnl, $current_price, $position_id);
    $stmt->execute();
}

// Liquidation kontrolü
function checkLiquidation($user_id, $coin_symbol, $current_price) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM leverage_positions 
        WHERE user_id = ? AND coin_symbol = ? AND status = 'open'
        AND ((position_type = 'long' AND ? <= liquidation_price) 
             OR (position_type = 'short' AND ? >= liquidation_price))
    ");
    $stmt->bind_param("isdd", $user_id, $coin_symbol, $current_price, $current_price);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($position = $result->fetch_assoc()) {
        liquidatePosition($position['id'], $current_price);
    }
}

// Pozisyonu liquidate et
function liquidatePosition($position_id, $liquidation_price) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM leverage_positions WHERE id = ?");
    $stmt->bind_param("i", $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $position = $result->fetch_assoc();
    
    if (!$position) return;
    
    // PnL hesapla (DÜZELTME: Liquidation'da kaldıraçlı zarar)
    $price_change_percentage = 0;
    if ($position['position_type'] === 'long') {
        $price_change_percentage = ($liquidation_price - $position['entry_price']) / $position['entry_price'];
    } else {
        $price_change_percentage = ($position['entry_price'] - $liquidation_price) / $position['entry_price'];
    }
    
    // Liquidation'da genelde %80 zarar (yatırılan miktarın %80'i)
    $pnl = $price_change_percentage * $position['invested_amount'] * $position['leverage_ratio'];
    
    // Liquidation'da maksimum zarar yatırılan miktarın %80'i olmalı
    $max_loss = -($position['invested_amount'] * 0.8);
    if ($pnl < $max_loss) {
        $pnl = $max_loss;
    }
    
    $conn->begin_transaction();
    
    try {
        // Pozisyonu liquidate et
        $stmt = $conn->prepare("
            UPDATE leverage_positions 
            SET status = 'liquidated', close_price = ?, realized_pnl = ?, closed_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ddi", $liquidation_price, $pnl, $position_id);
        $stmt->execute();
        
        // Position history'ye kaydet
        $stmt = $conn->prepare("
            INSERT INTO position_history (position_id, user_id, action_type, price, pnl)
            VALUES (?, ?, 'liquidate', ?, ?)
        ");
        $stmt->bind_param("iisd", $position_id, $position['user_id'], $liquidation_price, $pnl);
        $stmt->execute();
        
        $conn->commit();
        
        logActivity($position['user_id'], "Position liquidated: {$position['coin_symbol']} PnL: $pnl");
        
    } catch (Exception $e) {
        $conn->rollback();
        logError("Liquidation error: " . $e->getMessage());
    }
}

// Kaldıraç ayarlarını güncelle
function updateLeverageSettings($user_id, $data) {
    global $conn;
    
    $max_leverage = floatval($data['max_leverage'] ?? 10.0);
    $default_leverage = floatval($data['default_leverage'] ?? 1.0);
    $auto_close_enabled = $data['auto_close_enabled'] ?? true;
    $max_loss_percentage = floatval($data['max_loss_percentage'] ?? 80.0);
    
    $stmt = $conn->prepare("
        INSERT INTO leverage_settings (user_id, max_leverage, default_leverage, auto_close_enabled, max_loss_percentage)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        max_leverage = VALUES(max_leverage),
        default_leverage = VALUES(default_leverage),
        auto_close_enabled = VALUES(auto_close_enabled),
        max_loss_percentage = VALUES(max_loss_percentage)
    ");
    $stmt->bind_param("iddid", $user_id, $max_leverage, $default_leverage, $auto_close_enabled, $max_loss_percentage);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Settings updated']);
}

// Tüm output'u yakalayıp temizleyelim
$output = '';
if (ob_get_level()) {
    $output = ob_get_clean();
}

// Output'da JSON var mı kontrol et, sadece JSON'u gönder
$json_start = strpos($output, '{');
$json_end = strrpos($output, '}');

if ($json_start !== false && $json_end !== false) {
    $json_output = substr($output, $json_start, $json_end - $json_start + 1);
    echo trim($json_output);
} else {
    // JSON bulunamadıysa direkt output'u gönder ama temizle
    echo trim($output);
}
?>
