<?php
/**
 * Admin Panel - Fiyat Kontrol Sistemi
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../utils/price_manager.php';

// AJAX istekleri için JSON response
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Admin kontrolü
    if (!is_logged_in() || !is_admin()) {
        echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
        exit;
    }
    
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_coins':
            try {
                $conn = db_connect();
                $sql = "SELECT id, coin_adi, coin_kodu, current_price, price_change_24h, 
                               COALESCE(price_source, 'manuel') as kaynak, 
                               COALESCE(last_update, created_at) as updated_at,
                               is_active, coin_type, created_at, logo_url
                        FROM coins 
                        WHERE is_active = 1 
                        ORDER BY 
                            CASE 
                                WHEN coin_kodu IN ('T', 'SEX', 'TTT') THEN 1 
                                ELSE 2 
                            END, coin_adi";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Kaynak bilgisini düzenle
                foreach ($coins as &$coin) {
                    // Önce coin_type alanını kontrol et
                    if (isset($coin['coin_type']) && $coin['coin_type'] === 'manual') {
                        $coin['kaynak'] = 'Manuel';
                    } 
                    // Sonra price_source alanını kontrol et
                    else if (isset($coin['price_source']) && $coin['price_source'] === 'manuel') {
                        $coin['kaynak'] = 'Manuel';
                    }
                    // Hard-coded manuel coinler (backward compatibility)
                    else if (in_array($coin['coin_kodu'], ['T', 'SEX', 'TTT'])) {
                        $coin['kaynak'] = 'Manuel';
                    } 
                    // Admin tarafından fiyat değiştirilen coinler
                    else if (isset($coin['price_source']) && $coin['price_source'] === 'admin') {
                        $coin['kaynak'] = 'Admin';
                    }
                    // Diğerleri API
                    else {
                        $coin['kaynak'] = 'API';
                    }
                    
                    // Değişim bilgisi yoksa 0 yap
                    if (is_null($coin['price_change_24h'])) {
                        $coin['price_change_24h'] = 0;
                    }
                    
                    // Fiyat bilgisi yoksa 0 yap
                    if (is_null($coin['current_price'])) {
                        $coin['current_price'] = 0;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'coins' => $coins
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Veritabanı hatası: ' . $e->getMessage()
                ]);
                exit;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Geçersiz işlem']);
            exit;
    }
}

// POST istekleri için JSON response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Admin kontrolü
    if (!is_logged_in() || !is_admin()) {
        echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
        exit;
    }
    
    // Action parametresini al (POST veya GET'ten)
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $priceManager = new PriceManager();
    
    switch ($action) {
        case 'add_manual_coin':
            $coin_name = trim($_POST['coin_name'] ?? '');
            $coin_code = strtoupper(trim($_POST['coin_code'] ?? ''));
            $initial_price = floatval($_POST['initial_price'] ?? 0);
            
            // Validasyon
            if (empty($coin_name) || empty($coin_code)) {
                echo json_encode(['success' => false, 'error' => 'Coin adı ve kodu gerekli']);
                exit;
            }
            
            if ($initial_price <= 0) {
                echo json_encode(['success' => false, 'error' => 'Geçerli bir başlangıç fiyatı girin']);
                exit;
            }
            
            if (strlen($coin_code) > 10) {
                echo json_encode(['success' => false, 'error' => 'Coin kodu 10 karakterden uzun olamaz']);
                exit;
            }
            
            if (!preg_match('/^[A-Z0-9]+$/', $coin_code)) {
                echo json_encode(['success' => false, 'error' => 'Coin kodu sadece büyük harf ve rakam içerebilir']);
                exit;
            }
            
            try {
                $conn = db_connect();
                
                // API coinleri ile çakışma kontrolü
                $api_coins = ['BTC', 'ETH', 'BNB', 'XRP', 'USDT', 'ADA', 'SOL', 'DOGE', 'MATIC', 'DOT'];
                if (in_array($coin_code, $api_coins)) {
                    echo json_encode(['success' => false, 'error' => 'Bu kod API coinleri için ayrılmış']);
                    exit;
                }
                
                // Coin kodu benzersizlik kontrolü
                $sql = "SELECT COUNT(*) FROM coins WHERE coin_kodu = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$coin_code]);
                
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'error' => 'Bu coin kodu zaten mevcut']);
                    exit;
                }
                
                // Logo upload işlemi
                $logo_path = null;
                if (isset($_FILES['coin_logo']) && $_FILES['coin_logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../coin_logos/';
                    
                    // Dizin yoksa oluştur
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['coin_logo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $logo_filename = $coin_code . '_logo.' . $file_extension;
                        $logo_path = $upload_dir . $logo_filename;
                        
                        if (move_uploaded_file($_FILES['coin_logo']['tmp_name'], $logo_path)) {
                            $logo_path = 'coin_logos/' . $logo_filename; // Relative path for database
                        } else {
                            $logo_path = null;
                        }
                    }
                }
                
                // Yeni manual coin ekle (logo ile)
                $sql = "INSERT INTO coins (coin_adi, coin_kodu, current_price, coin_type, price_source, logo_url, is_active, created_at) 
                        VALUES (?, ?, ?, 'manual', 'manuel', ?, 1, NOW())";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$coin_name, $coin_code, $initial_price, $logo_path]);
                
                if ($result) {
                    // PriceManager'ın manuel coin listesini güncelle
                    $priceManager->addManualCoin($coin_code);
                    
                    $response_data = [
                        'success' => true,
                        'message' => $coin_name . ' (' . $coin_code . ') başarıyla eklendi',
                        'data' => [
                            'coin_name' => $coin_name,
                            'coin_code' => $coin_code,
                            'initial_price' => $initial_price
                        ]
                    ];
                    
                    if ($logo_path) {
                        $response_data['data']['logo_path'] = $logo_path;
                        $response_data['message'] .= ' (Logo yüklendi)';
                    }
                    
                    echo json_encode($response_data);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Coin eklenemedi']);
                }
                exit;
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
                exit;
            }
            break;

        case 'increase_price':
            $coin_code = trim($_POST['coin_code'] ?? '');
            $increase_percent = floatval($_POST['increase_percent'] ?? 0);
            
            if (empty($coin_code)) {
                echo json_encode(['success' => false, 'error' => 'Coin kodu gerekli']);
                exit;
            }
            
            if ($increase_percent <= 0 || $increase_percent > 1000) {
                echo json_encode(['success' => false, 'error' => 'Geçersiz artış oranı (1-1000 arası)']);
                exit;
            }
            
            try {
                $conn = db_connect();
                
                // Mevcut fiyat ve price_change_24h'ı al
                $sql = "SELECT coin_adi, current_price, price_change_24h FROM coins WHERE coin_kodu = ? AND is_active = 1";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$coin_code]);
                $coin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$coin) {
                    echo json_encode(['success' => false, 'error' => 'Coin bulunamadı']);
                    exit;
                }
                
                $old_price = floatval($coin['current_price']);
                $increase_amount = $old_price * ($increase_percent / 100);
                $new_price = $old_price + $increase_amount;
                
                // Yeni price_change_24h hesapla
                // Admin artırması = anlık yüzde değişimi olarak gösterelim
                $new_price_change_24h = $increase_percent;
                
                // Fiyat ve yüzde değişimini güncelle
                $sql = "UPDATE coins SET 
                        current_price = ?, 
                        price_change_24h = ?,
                        price_source = 'admin',
                        last_update = NOW() 
                        WHERE coin_kodu = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$new_price, $new_price_change_24h, $coin_code]);
                
                echo json_encode([
                    'success' => true,
                    'message' => $coin['coin_adi'] . ' fiyatı %' . $increase_percent . ' artırıldı (24s değişim: +%' . $increase_percent . ')',
                    'details' => [
                        'coin_name' => $coin['coin_adi'],
                        'old_price' => $old_price,
                        'new_price' => $new_price,
                        'increase_percent' => $increase_percent,
                        'new_price_change_24h' => $new_price_change_24h
                    ]
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
                exit;
            }
            break;
            
        case 'update_all_prices':
            try {
                $priceManager->updateAllPrices();
                echo json_encode([
                    'success' => true,
                    'message' => 'Tüm fiyatlar güncellendi',
                    'updated_count' => 10 // Örnek sayı
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Güncelleme hatası: ' . $e->getMessage()]);
                exit;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Geçersiz işlem']);
            exit;
    }
}

// HTML sayfası için eski kod
$priceManager = new PriceManager();
$message = '';
$error = '';

// Fiyat artırma işlemi
if (isset($_POST['action']) && $_POST['action'] === 'increase_price' && isset($_POST['coin_code'], $_POST['increase_percent'])) {
    $coin_code = trim($_POST['coin_code']);
    $increase_percent = floatval($_POST['increase_percent']);
    
    if ($increase_percent > 0 && $increase_percent <= 1000) {
        $result = $priceManager->increasePriceByPercent($coin_code, $increase_percent);
        
        if ($result['success']) {
            $message = $result['message'] . " (₺" . number_format($result['old_price'], 2) . " → ₺" . number_format($result['new_price'], 2) . ")";
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Geçersiz artış oranı. 0-1000 arasında olmalıdır.";
    }
}

// Manuel fiyat güncelleme
if (isset($_POST['action']) && $_POST['action'] === 'update_manual_prices') {
    $priceManager->updateAllPrices();
    $message = "Tüm fiyatlar güncellendi (API + Manuel dalgalanma)";
}

// Coin listesini al
try {
    $conn = db_connect();
    
    // coin_type kolonu var mı kontrol et
    $sql_check = "SHOW COLUMNS FROM coins LIKE 'coin_type'";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute();
    $has_coin_type = $stmt_check->rowCount() > 0;
    
    if ($has_coin_type) {
        $sql = "SELECT id, coin_adi, coin_kodu, current_price, price_change_24h, price_source, last_update, coin_type
                FROM coins 
                WHERE is_active = 1 
                ORDER BY 
                    CASE 
                        WHEN coin_kodu IN ('T', 'SEX', 'TTT') THEN 1 
                        ELSE 2 
                    END, coin_adi";
    } else {
        $sql = "SELECT id, coin_adi, coin_kodu, current_price, price_change_24h, price_source, last_update,
                       CASE 
                           WHEN coin_kodu IN ('T', 'SEX', 'TTT') THEN 'manual'
                           ELSE 'api'
                       END as coin_type
                FROM coins 
                WHERE is_active = 1 
                ORDER BY 
                    CASE 
                        WHEN coin_kodu IN ('T', 'SEX', 'TTT') THEN 1 
                        ELSE 2 
                    END, coin_adi";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $coins = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiyat Kontrol - Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .price-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .control-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .control-box h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .coins-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .coins-table th,
        .coins-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .coins-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .coins-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .price-source {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .source-api { background: #d1ecf1; color: #0c5460; }
        .source-manual { background: #fff3cd; color: #856404; }
        .source-admin { background: #d4edda; color: #155724; }
        
        .price-change {
            font-weight: bold;
        }
        .positive { color: #28a745; }
        .negative { color: #dc3545; }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Fiyat Kontrol Sistemi</h1>
            <div>
                <a href="coins.php" class="btn btn-primary">Coin Yönetimi</a>
                <a href="../admin-panel.html" class="btn btn-secondary">Ana Panel</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="quick-actions">
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="update_manual_prices">
                <button type="submit" class="btn btn-success">🔄 Tüm Fiyatları Güncelle</button>
            </form>
            <a href="?update_prices=1" class="btn btn-warning">⚡ API Fiyatlarını Çek</a>
        </div>

        <div class="price-controls">
            <div class="control-box">
                <h3>➕ Yeni Manuel Coin Ekle</h3>
                <form method="post" id="addCoinForm">
                    <input type="hidden" name="action" value="add_manual_coin">
                    
                    <div class="form-group">
                        <label>Coin Adı:</label>
                        <input type="text" name="coin_name" class="form-control" 
                               placeholder="Örn: Tugay Coin" required maxlength="100">
                        <small>Maksimum 100 karakter</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Coin Kodu:</label>
                        <input type="text" name="coin_code" class="form-control" 
                               placeholder="Örn: TGY" required maxlength="10" style="text-transform: uppercase;">
                        <small>Sadece büyük harf ve rakam, maksimum 10 karakter</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Başlangıç Fiyatı (₺):</label>
                        <input type="number" name="initial_price" class="form-control" 
                               step="0.01" min="0.01" placeholder="Örn: 100.00" required>
                        <small>Minimum 0.01 TL</small>
                    </div>
                    
                    <button type="submit" class="btn btn-success">➕ Coin Ekle</button>
                </form>
            </div>

            <div class="control-box">
                <h3>📈 Manuel Fiyat Artırma</h3>
                <form method="post">
                    <input type="hidden" name="action" value="increase_price">
                    
                    <div class="form-group">
                        <label>Coin Seç:</label>
                        <select name="coin_code" class="form-control" required>
                            <option value="">Coin Seçin...</option>
                            <?php foreach ($coins as $coin): ?>
                                <?php if ($coin['coin_type'] === 'manual' || in_array($coin['coin_kodu'], ['T', 'SEX', 'TTT'])): ?>
                                    <option value="<?= $coin['coin_kodu'] ?>">
                                        <?= $coin['coin_adi'] ?> (<?= $coin['coin_kodu'] ?>) - ₺<?= number_format($coin['current_price'], 2) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Artış Oranı (%):</label>
                        <input type="number" name="increase_percent" class="form-control" 
                               min="1" max="1000" step="0.1" placeholder="Örn: 50" required>
                        <small>1-1000 arasında değer girin (50 = %50 artış)</small>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">🚀 Fiyatı Artır</button>
                </form>
            </div>
        </div>

        <div class="price-controls">
            <div class="control-box">
                <h3>ℹ️ Sistem Bilgileri</h3>
                <p><strong>API Coinleri:</strong> BTC, ETH, BNB, XRP, USDT, ADA, SOL, DOGE, MATIC, DOT</p>
                <p><strong>Manuel Coinler:</strong> 
                    <?php 
                    $manual_coins_list = [];
                    foreach ($coins as $coin) {
                        if ($coin['coin_type'] === 'manual' || in_array($coin['coin_kodu'], ['T', 'SEX', 'TTT'])) {
                            $manual_coins_list[] = $coin['coin_kodu'];
                        }
                    }
                    echo implode(', ', $manual_coins_list);
                    ?>
                </p>
                <p><strong>Dalgalanma Aralığı:</strong> -%5 ile +%30</p>
                <p><strong>Güncelleme Sıklığı:</strong> Her 5 dakika (Cron Job)</p>
                
                <div style="margin-top: 15px;">
                    <strong>Son Güncelleme:</strong><br>
                    <span id="lastUpdate"><?= date('d.m.Y H:i:s') ?></span>
                </div>
            </div>

            <div class="control-box">
                <h3>🎯 Coin Durumu</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <strong>Toplam Coin:</strong><br>
                        <span style="font-size: 24px; color: #007bff;"><?= count($coins) ?></span>
                    </div>
                    <div>
                        <strong>Manuel Coin:</strong><br>
                        <span style="font-size: 24px; color: #28a745;"><?= count($manual_coins_list) ?></span>
                    </div>
                    <div>
                        <strong>API Coin:</strong><br>
                        <span style="font-size: 24px; color: #ffc107;"><?= count($coins) - count($manual_coins_list) ?></span>
                    </div>
                    <div>
                        <strong>Aktif Coin:</strong><br>
                        <span style="font-size: 24px; color: #17a2b8;"><?= count(array_filter($coins, function($c) { return $c['is_active']; })) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <h2>💰 Coin Fiyat Listesi</h2>
        <table class="coins-table">
            <thead>
                <tr>
                    <th>Coin</th>
                    <th>Kod</th>
                    <th>Güncel Fiyat</th>
                    <th>24s Değişim</th>
                    <th>Kaynak</th>
                    <th>Son Güncelleme</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coins as $coin): ?>
                    <tr>
                        <td><?= htmlspecialchars($coin['coin_adi']) ?></td>
                        <td><strong><?= htmlspecialchars($coin['coin_kodu']) ?></strong></td>
                        <td>₺<?= number_format($coin['current_price'], 2) ?></td>
                        <td>
                            <?php 
                            $change = floatval($coin['price_change_24h']);
                            $class = $change >= 0 ? 'positive' : 'negative';
                            $sign = $change >= 0 ? '+' : '';
                            ?>
                            <span class="price-change <?= $class ?>">
                                <?= $sign ?><?= number_format($change, 2) ?>%
                            </span>
                        </td>
                        <td>
                            <?php 
                            // Kaynak belirleme (API response ile aynı mantık)
                            if (isset($coin['coin_type']) && $coin['coin_type'] === 'manual') {
                                $sourceText = 'Manuel';
                                $sourceClass = 'source-manual';
                            } 
                            else if (isset($coin['price_source']) && $coin['price_source'] === 'manuel') {
                                $sourceText = 'Manuel';
                                $sourceClass = 'source-manual';
                            }
                            else if (in_array($coin['coin_kodu'], ['T', 'SEX', 'TTT'])) {
                                $sourceText = 'Manuel';
                                $sourceClass = 'source-manual';
                            } 
                            else if (isset($coin['price_source']) && $coin['price_source'] === 'admin') {
                                $sourceText = 'Admin';
                                $sourceClass = 'source-admin';
                            }
                            else {
                                $sourceText = 'API';
                                $sourceClass = 'source-api';
                            }
                            ?>
                            <span class="price-source <?= $sourceClass ?>"><?= $sourceText ?></span>
                        </td>
                        <td>
                            <?= $coin['last_update'] ? date('d.m.Y H:i', strtotime($coin['last_update'])) : 'Bilinmiyor' ?>
                        </td>
                        <td>
                            <?php if (in_array($coin['coin_kodu'], ['T', 'SEX', 'TTT'])): ?>
                                <button onclick="quickIncrease('<?= $coin['coin_kodu'] ?>', 50)" class="btn btn-warning btn-sm">+50%</button>
                                <button onclick="quickIncrease('<?= $coin['coin_kodu'] ?>', 100)" class="btn btn-danger btn-sm">+100%</button>
                            <?php else: ?>
                                <span style="color: #6c757d;">API Coin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function quickIncrease(coinCode, percent) {
            if (confirm(`${coinCode} coin fiyatını %${percent} artırmak istediğinizden emin misiniz?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="increase_price">
                    <input type="hidden" name="coin_code" value="${coinCode}">
                    <input type="hidden" name="increase_percent" value="${percent}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Sayfa yenilenme zamanlayıcısı
        setInterval(() => {
            document.getElementById('lastUpdate').textContent = new Date().toLocaleString('tr-TR');
        }, 60000);
    </script>
</body>
</html>
