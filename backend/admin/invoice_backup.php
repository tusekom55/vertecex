<?php
// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Debug log
error_log("=== INVOICE API CALLED ===");
error_log("Action: " . ($_GET['action'] ?? 'none'));
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));

try {
    // Dosya yollarını kontrol et
    $config_path = __DIR__ . '/../config.php';
    $security_path = __DIR__ . '/../utils/security.php';
    
    error_log("Config path: " . $config_path);
    error_log("Security path: " . $security_path);
    error_log("Config exists: " . (file_exists($config_path) ? 'yes' : 'no'));
    error_log("Security exists: " . (file_exists($security_path) ? 'yes' : 'no'));
    
    if (!file_exists($config_path)) {
        throw new Exception('config.php dosyası bulunamadı: ' . $config_path);
    }
    
    if (!file_exists($security_path)) {
        throw new Exception('security.php dosyası bulunamadı: ' . $security_path);
    }
    
    require_once $config_path;
    require_once $security_path;
    
    error_log("Dosyalar başarıyla yüklendi");
    
    // Fonksiyon kontrolü
    if (!function_exists('db_connect')) {
        throw new Exception('db_connect fonksiyonu bulunamadı');
    }
    
    error_log("db_connect fonksiyonu mevcut");
    
} catch (Exception $e) {
    error_log("Dosya yükleme hatası: " . $e->getMessage());
    echo json_encode(['error' => 'Dosya yükleme hatası: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    error_log("PHP Fatal Error: " . $e->getMessage());
    echo json_encode(['error' => 'PHP Fatal Error: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'test':
            // API bağlantı testi
            error_log("Test action called");
            echo json_encode(['success' => true, 'message' => 'API bağlantısı başarılı', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
        
    case 'test_db':
        // Veritabanı bağlantı testi
        try {
            $conn = db_connect();
            echo json_encode(['success' => true, 'message' => 'Veritabanı bağlantısı başarılı', 'timestamp' => date('Y-m-d H:i:s')]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Veritabanı bağlantısı başarısız: ' . $e->getMessage()]);
        }
        break;
        
    case 'test_user':
        // Kullanıcı testi
        $user_id = $_GET['user_id'] ?? 0;
        try {
            $conn = db_connect();
            $sql = "SELECT id, username, email, ad_soyad FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user) {
                echo json_encode(['success' => true, 'message' => 'Kullanıcı bulundu', 'user' => $user]);
            } else {
                echo json_encode(['error' => 'Kullanıcı bulunamadı (ID: ' . $user_id . ')']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Kullanıcı sorgusu başarısız: ' . $e->getMessage()]);
        }
        break;
        
    case 'create':
        try {
            error_log("=== CREATE CASE STARTED ===");
            
            // JSON verilerini al
            $raw_input = file_get_contents('php://input');
            error_log("Raw input: " . $raw_input);
            
            $input = json_decode($raw_input, true);
            error_log("Decoded input: " . print_r($input, true));
            
            $user_id = $input['user_id'] ?? $_POST['user_id'] ?? 0;
            $islem_tipi = $input['islem_tipi'] ?? $_POST['islem_tipi'] ?? '';
            $islem_id = $input['islem_id'] ?? $_POST['islem_id'] ?? 0;
            $tutar = $input['tutar'] ?? $_POST['tutar'] ?? 0;
            
            error_log("Creating invoice with data: user_id=$user_id, islem_tipi=$islem_tipi, islem_id=$islem_id, tutar=$tutar");
            
            // Parametreleri kontrol et
            if (empty($user_id) || empty($islem_tipi) || empty($tutar)) {
                throw new Exception('Eksik parametreler: user_id, islem_tipi ve tutar gerekli');
            }
            
            // Veritabanı bağlantısını oluştur
            error_log("Attempting database connection...");
            $conn = db_connect();
            error_log("Database connection successful");
            
        } catch (Exception $e) {
            error_log("Create case error: " . $e->getMessage());
            echo json_encode(['error' => 'Create hatası: ' . $e->getMessage()]);
            exit;
        }
        
        // Kullanıcı bilgilerini al
        $sql = "SELECT * FROM users WHERE id = ?";
        error_log("Executing user query: $sql with user_id=$user_id");
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("User query prepare failed: " . $conn->error);
            echo json_encode(['error' => 'Kullanıcı sorgusu hazırlanamadı: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        error_log("User query result: " . print_r($user, true));
        
        if (!$user) {
            error_log("User not found for ID: $user_id");
            echo json_encode(['error' => 'Kullanıcı bulunamadı']);
            exit;
        }
        
        error_log("User found: " . $user['username']);
        
        // Fatura ayarlarını al
        $sql = "SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari WHERE ayar_adi LIKE 'fatura_%'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $fatura_ayarlari = [];
        while ($row = $result->fetch_assoc()) {
            $fatura_ayarlari[$row['ayar_adi']] = $row['ayar_degeri'];
        }
        
        // Fatura numarası oluştur
        $fatura_no = 'FTR-' . date('Ymd') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        
        // KDV hesapla
        $kdv_orani = 18; // %18 KDV
        $kdv_tutari = $tutar * ($kdv_orani / 100);
        $toplam_tutar = $tutar + $kdv_tutari;
        
        // Faturayı veritabanına kaydet
        $sql = "INSERT INTO faturalar (user_id, islem_tipi, islem_id, fatura_no, tutar, kdv_orani, kdv_tutari, toplam_tutar) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        error_log("Executing invoice insert: $sql");
        error_log("Parameters: user_id=$user_id, islem_tipi=$islem_tipi, islem_id=$islem_id, fatura_no=$fatura_no, tutar=$tutar, kdv_orani=$kdv_orani, kdv_tutari=$kdv_tutari, toplam_tutar=$toplam_tutar");
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Invoice insert prepare failed: " . $conn->error);
            echo json_encode(['error' => 'SQL hazırlama hatası: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param('issisdd', $user_id, $islem_tipi, $islem_id, $fatura_no, $tutar, $kdv_orani, $kdv_tutari, $toplam_tutar);
        $result = $stmt->execute();
        
        if ($result) {
            $fatura_id = $conn->insert_id;
            error_log("Invoice created successfully with ID: $fatura_id");
            
            // Fatura verilerini hazırla
            $fatura_data = [
                'fatura_id' => $fatura_id,
                'fatura_no' => $fatura_no,
                'tarih' => date('Y-m-d H:i:s'),
                'username' => $user['username'],
                'email' => $user['email'],
                'ad_soyad' => $user['ad_soyad'] ?? $user['username'],
                'tc_no' => $user['tc_no'] ?? '',
                'telefon' => $user['telefon'] ?? '',
                'iban' => $user['iban'] ?? '',
                'tutar' => $tutar,
                'kdv_orani' => $kdv_orani,
                'kdv_tutari' => $kdv_tutari,
                'toplam_tutar' => $toplam_tutar,
                'islem_tipi' => $islem_tipi,
                'fatura_ayarlari' => $fatura_ayarlari
            ];
            
            error_log("Sending success response: " . json_encode(['success' => true, 'data' => $fatura_data]));
            echo json_encode(['success' => true, 'data' => $fatura_data]);
        } else {
            error_log("Invoice insert failed: " . $stmt->error);
            echo json_encode(['error' => 'Fatura oluşturulamadı: ' . $stmt->error]);
        }
        break;
        
    case 'get':
        // Fatura getir
        $fatura_id = $_GET['fatura_id'] ?? 0;
        
        // Veritabanı bağlantısını oluştur
        $conn = db_connect();
        
        $sql = "SELECT f.*, u.username, u.email, u.telefon, u.ad_soyad, u.tc_no, u.iban 
                FROM faturalar f
                JOIN users u ON f.user_id = u.id
                WHERE f.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $fatura_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $fatura = $result->fetch_assoc();
        
        if (!$fatura) {
            echo json_encode(['error' => 'Fatura bulunamadı']);
            exit;
        }
        
        // Fatura ayarlarını al
        $sql = "SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari WHERE ayar_adi LIKE 'fatura_%'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $fatura_ayarlari = [];
        while ($row = $result->fetch_assoc()) {
            $fatura_ayarlari[$row['ayar_adi']] = $row['ayar_degeri'];
        }
        
        $fatura['fatura_ayarlari'] = $fatura_ayarlari;
        
        echo json_encode(['success' => true, 'data' => $fatura]);
        break;
        
    case 'list':
        // Fatura listesi
        $user_id = $_GET['user_id'] ?? 0;
        
        // Veritabanı bağlantısını oluştur
        $conn = db_connect();
        
        $sql = "SELECT f.*, u.username, u.email 
                FROM faturalar f
                JOIN users u ON f.user_id = u.id";
        
        if ($user_id > 0) {
            $sql .= " WHERE f.user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
        } else {
            $sql .= " ORDER BY f.tarih DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }
        
        $result = $stmt->get_result();
        $faturalar = [];
        while ($row = $result->fetch_assoc()) {
            $faturalar[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $faturalar]);
        break;
        
    case 'generate_pdf':
        // PDF fatura oluştur
        $fatura_id = $_GET['fatura_id'] ?? 0;
        
        // Fatura verilerini al
        // Veritabanı bağlantısını oluştur
        $conn = db_connect();
        
        $sql = "SELECT f.*, u.username, u.email, u.telefon, u.ad_soyad 
                FROM faturalar f
                JOIN users u ON f.user_id = u.id
                WHERE f.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $fatura_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $fatura = $result->fetch_assoc();
        
        if (!$fatura) {
            echo json_encode(['error' => 'Fatura bulunamadı']);
            exit;
        }
        
        // Fatura ayarlarını al
        $sql = "SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari WHERE ayar_adi LIKE 'fatura_%'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $fatura_ayarlari = [];
        while ($row = $result->fetch_assoc()) {
            $fatura_ayarlari[$row['ayar_adi']] = $row['ayar_degeri'];
        }
        
        // HTML fatura şablonu oluştur
        $html = generateInvoiceHTML($fatura, $fatura_ayarlari);
        
        // PDF oluştur (basit HTML döndür, gerçek PDF için TCPDF veya mPDF kullanılabilir)
        header('Content-Type: text/html');
        echo $html;
        break;
        
        default:
            error_log("Geçersiz action: " . $action);
            echo json_encode(['error' => 'Geçersiz işlem: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Invoice API genel hata: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['error' => 'Sistem hatası: ' . $e->getMessage()]);
}

function generateInvoiceHTML($fatura, $fatura_ayarlari) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>E-Fatura - ' . $fatura['fatura_no'] . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Segoe UI", Arial, sans-serif; background: #f8f9fa; color: #333; }
            .invoice-container { max-width: 800px; margin: 20px auto; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
            
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; position: relative; }
            .company-logo { display: flex; align-items: center; margin-bottom: 20px; }
            .logo-icon { width: 50px; height: 50px; background: linear-gradient(45deg, #ffd700, #ffed4e); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; color: #333; margin-right: 15px; }
            .company-name { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
            .company-info { font-size: 14px; opacity: 0.9; }
            .qr-code { position: absolute; top: 30px; right: 30px; text-align: center; }
            .qr-placeholder { width: 80px; height: 80px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 5px; font-size: 12px; color: #333; }
            
            .content { padding: 30px; }
            .section { margin-bottom: 30px; }
            .section-title { font-size: 18px; font-weight: 600; color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; }
            .section-title i { margin-right: 10px; color: #667eea; }
            
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .info-item { margin-bottom: 15px; }
            .info-label { font-size: 12px; color: #7f8c8d; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; }
            .info-value { font-size: 16px; font-weight: 500; color: #2c3e50; }
            
            .amount-highlight { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; }
            .amount-label { font-size: 14px; opacity: 0.9; margin-bottom: 5px; }
            .amount-value { font-size: 32px; font-weight: 700; }
            
            .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .items-table th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; text-align: left; font-weight: 600; }
            .items-table td { padding: 15px; border-bottom: 1px solid #ecf0f1; }
            .items-table tr:hover { background: #f8f9fa; }
            
            .payment-methods { display: flex; justify-content: center; gap: 15px; margin: 30px 0; }
            .payment-method { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; }
            .payment-btc { background: linear-gradient(45deg, #f7931a, #ffd700); }
            .payment-eth { background: linear-gradient(45deg, #627eea, #764ba2); }
            .payment-usdt { background: linear-gradient(45deg, #26a69a, #4db6ac); }
            
            .footer { background: #ecf0f1; padding: 20px; text-align: center; color: #7f8c8d; font-size: 12px; }
            
            .invoice-number { background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 10px; display: inline-block; margin-bottom: 20px; }
            .invoice-number h2 { font-size: 24px; font-weight: 600; margin: 0; }
            
            .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
            .status-paid { background: #d4edda; color: #155724; }
            
            .total-section { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
            .total-row:last-child { border-top: 2px solid #667eea; padding-top: 10px; margin-top: 10px; font-weight: 700; font-size: 18px; }
            
            @media print {
                body { background: white; }
                .invoice-container { box-shadow: none; margin: 0; }
            }
            
            @media (max-width: 768px) {
                .invoice-container { margin: 10px; border-radius: 15px; }
                .header { padding: 25px; }
                .content { padding: 25px; }
                .company-name { font-size: 24px; }
                .amount-value { font-size: 32px; }
                .info-grid { grid-template-columns: 1fr; gap: 15px; }
                .qr-code { position: static; margin-top: 20px; }
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="header">
                <div class="company-logo">
                    <div class="logo-icon">COINOVA</div>
                    <div>
                        <div class="company-name">' . ($fatura_ayarlari['fatura_sirket_adi'] ?? 'Crypto Finance Ltd.') . '</div>
                        <div class="company-info">
                            ' . ($fatura_ayarlari['fatura_adres'] ?? 'İstanbul, Türkiye') . '<br>
                            Tel: ' . ($fatura_ayarlari['fatura_telefon'] ?? '+90 212 555 0123') . ' | 
                            E-posta: ' . ($fatura_ayarlari['fatura_email'] ?? 'info@cryptofinance.com') . '
                        </div>
                    </div>
                </div>
                <div class="qr-code">
                    <div class="qr-placeholder">QR</div>
                    <div style="font-size: 10px; opacity: 0.8;">coinovamarket.com</div>
                    <div style="font-size: 8px; opacity: 0.6;">Gi</div>
                </div>
            </div>
            
            <div class="content">
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-user"></i>
                        Müşteri Bilgileri
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Ad Soyad</div>
                            <div class="info-value">' . ($fatura['ad_soyad'] ?? $fatura['username']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">TC Kimlik No</div>
                            <div class="info-value">' . ($fatura['tc_no'] ?? 'Belirtilmemiş') . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">E-posta</div>
                            <div class="info-value">' . $fatura['email'] . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Telefon</div>
                            <div class="info-value">' . ($fatura['telefon'] ?? 'Belirtilmemiş') . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">IBAN</div>
                            <div class="info-value">' . ($fatura['iban'] ?? 'Belirtilmemiş') . '</div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-file-invoice"></i>
                        Fatura Bilgileri
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Fatura No</div>
                            <div class="info-value">' . $fatura['fatura_no'] . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tarih</div>
                            <div class="info-value">' . date('d.m.Y', strtotime($fatura['tarih'])) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Çekim Saati</div>
                            <div class="info-value">' . date('H:i', strtotime($fatura['tarih'])) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ödenecek Tutar</div>
                            <div class="info-value">' . number_format($fatura['toplam_tutar'], 2, ',', '.') . ' ₺</div>
                        </div>
                    </div>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>AÇIKLAMA</th>
                            <th>MİKTAR</th>
                            <th>BİRİM FİYAT</th>
                            <th>TOPLAM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ödeme açıklaması :0233</td>
                            <td>1</td>
                            <td>' . number_format($fatura['tutar'], 2, ',', '.') . ' ₺</td>
                            <td>' . number_format($fatura['tutar'], 2, ',', '.') . ' ₺</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="total-section">
                    <div class="total-row">
                        <span>Ara Toplam</span>
                        <span>' . number_format($fatura['tutar'], 2, ',', '.') . ' ₺</span>
                    </div>
                    <div class="total-row">
                        <span>Genel Toplam</span>
                        <span>' . number_format($fatura['toplam_tutar'], 2, ',', '.') . ' ₺</span>
                    </div>
                </div>
                
                <div class="footer">
                    <p>Bu fatura elektronik ortamda oluşturulmuştur ve imza gerektirmez.</p>
                    <p>© 2024 ' . ($fatura_ayarlari['fatura_sirket_adi'] ?? 'Crypto Finance Ltd.') . ' - Tüm hakları saklıdır.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
                            <th>TOPLAM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Ödeme açıklaması :' . str_pad($fatura['id'], 4, '0', STR_PAD_LEFT) . '</td>
                            <td>1</td>
                            <td>' . number_format($fatura['tutar'], 2, ',', '.') . ' ₺</td>
                            <td>' . number_format($fatura['tutar'], 2, ',', '.') . ' ₺</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="text-align: right; margin: 20px 0;">
                    <div style="margin: 10px 0;">
                        <span style="font-weight: 600;">Ara Toplam:</span>
                        <span style="margin-left: 20px;">' . number_format($fatura['tutar'], 2, ',', '.') . ' ₺</span>
                    </div>
                    <div style="margin: 10px 0;">
                        <span style="font-weight: 600;">Genel Toplam:</span>
                        <span style="margin-left: 20px; font-size: 18px; font-weight: 700; color: #667eea;">' . number_format($fatura['toplam_tutar'], 2, ',', '.') . ' ₺</span>
                    </div>
                </div>
                
                <div class="payment-methods">
                    <div class="payment-method payment-btc">B</div>
                    <div class="payment-method payment-eth">Ξ</div>
                    <div class="payment-method payment-usdt">T</div>
                </div>
            </div>
            
            <div class="footer">
                <p>Bu belge elektronik ortamda oluşturulmuştur. | Vergi No: ' . ($fatura_ayarlari['fatura_vergi_no'] ?? '1234567890') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?> 