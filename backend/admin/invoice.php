<?php
// JSON header'ı en başta ayarla
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Hata raporlamayı kapat (JSON bozulmasını önlemek için)
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../utils/security.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Dosya yükleme hatası: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

// Database bağlantısını kur - MySQLi connection kullanılıyor  
// (Bu dosya MySQLi syntax kullanıyor, diğer dosyalar PDO kullanıyor)
require_once __DIR__ . '/../config.php';

try {
    switch ($action) {
        case 'test':
            // API bağlantı testi
            echo json_encode(['success' => true, 'message' => 'API bağlantısı başarılı', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
        
        case 'test_db':
            // Veritabanı bağlantı testi
            try {
                global $conn;
                echo json_encode(['success' => true, 'message' => 'Veritabanı bağlantısı başarılı', 'timestamp' => date('Y-m-d H:i:s')]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Veritabanı bağlantısı başarısız: ' . $e->getMessage()]);
            }
            break;
            
        case 'test_user':
            // Kullanıcı testi
            $user_id = $_GET['user_id'] ?? 2; // Default test user
            try {
                global $conn;
                $sql = "SELECT id, username, email, ad_soyad, tc_no, iban FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if ($user) {
                    echo json_encode(['success' => true, 'message' => 'Kullanıcı bulundu', 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı (ID: ' . $user_id . ')']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Kullanıcı sorgusu başarısız: ' . $e->getMessage()]);
            }
            break;
            
        case 'create':
            try {
                // JSON verilerini al
                $raw_input = file_get_contents('php://input');
                $input = json_decode($raw_input, true);
                
                $user_id = $input['user_id'] ?? $_POST['user_id'] ?? null;
                $islem_tipi = $input['islem_tipi'] ?? $_POST['islem_tipi'] ?? 'para_cekme';
                $islem_id = $input['islem_id'] ?? $_POST['islem_id'] ?? 0;
                $tutar = floatval($input['tutar'] ?? $_POST['tutar'] ?? 0);
                $aciklama = $input['aciklama'] ?? $_POST['aciklama'] ?? '';
                
                // Parametreleri kontrol et
                if (empty($user_id) || empty($tutar) || $tutar <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Geçersiz parametreler: user_id ve tutar gerekli']);
                    break;
                }
                
                // Global MySQLi bağlantısını kullan
                global $conn;
                
                // Kullanıcı bilgilerini al
                $sql = "SELECT id, username, email, ad_soyad, tc_no, iban FROM users WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if (!$user) {
                    echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı (ID: ' . $user_id . ')']);
                    break;
                }
                
                // Fatura numarası oluştur
                $fatura_no = 'FTR-' . date('Ymd') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // KDV hesapla (varsayılan %18)
                $kdv_orani = 18;
                $kdv_tutari = $tutar * ($kdv_orani / 100);
                $toplam_tutar = $tutar + $kdv_tutari;
                
                // Faturalar tablosunu kontrol et, yoksa oluştur
                $create_table_sql = "CREATE TABLE IF NOT EXISTS faturalar (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    islem_tipi VARCHAR(50) NOT NULL,
                    islem_id INT DEFAULT 0,
                    fatura_no VARCHAR(100) UNIQUE NOT NULL,
                    tutar DECIMAL(10,2) NOT NULL,
                    kdv_orani DECIMAL(5,2) DEFAULT 18.00,
                    kdv_tutari DECIMAL(10,2) DEFAULT 0.00,
                    toplam_tutar DECIMAL(10,2) NOT NULL,
                    aciklama TEXT,
                    tarih TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_fatura_no (fatura_no)
                )";
                $conn->query($create_table_sql);
                
                // Faturayı veritabanına kaydet
                $sql = "INSERT INTO faturalar (user_id, islem_tipi, islem_id, fatura_no, tutar, kdv_orani, kdv_tutari, toplam_tutar, aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('isisddds', $user_id, $islem_tipi, $islem_id, $fatura_no, $tutar, $kdv_orani, $kdv_tutari, $toplam_tutar, $aciklama);
                
                if ($stmt->execute()) {
                    $fatura_id = $conn->insert_id;
                    
                    $fatura_data = [
                        'id' => $fatura_id,
                        'fatura_no' => $fatura_no,
                        'user_id' => $user_id,
                        'tutar' => $tutar,
                        'kdv_tutari' => $kdv_tutari,
                        'toplam_tutar' => $toplam_tutar,
                        'tarih' => date('Y-m-d H:i:s')
                    ];
                    
                    echo json_encode(['success' => true, 'data' => $fatura_data, 'message' => 'Fatura başarıyla oluşturuldu']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Fatura oluşturulamadı: ' . $conn->error]);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Fatura oluşturma hatası: ' . $e->getMessage()]);
            }
            break;
            
        case 'generate_pdf':
            // Fatura HTML görüntüleme
            $fatura_id = $_GET['id'] ?? 0;
            
            if (empty($fatura_id)) {
                header('Content-Type: text/html');
                echo '<h1>Hata: Fatura ID gerekli</h1>';
                break;
            }
            
            try {
                global $conn;
                
                // Fatura bilgilerini al
                $sql = "SELECT f.*, u.username, u.email, u.ad_soyad, u.tc_no, u.iban 
                        FROM faturalar f 
                        JOIN users u ON f.user_id = u.id 
                        WHERE f.id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $fatura_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $fatura = $result->fetch_assoc();
                
                if (!$fatura) {
                    header('Content-Type: text/html');
                    echo '<h1>Hata: Fatura bulunamadı</h1>';
                    break;
                }
                
                // Sistem ayarlarını al (tablo yoksa varsayılan değerler kullan)
                $fatura_ayarlari = [
                    'fatura_sirket_adi' => 'Crypto Finance Ltd.',
                    'fatura_adres' => 'İstanbul, Türkiye',
                    'fatura_telefon' => '+90 212 555 0123',
                    'fatura_email' => 'info@cryptofinance.com',
                    'fatura_vergi_no' => '1234567890'
                ];
                
                $ayarlar_sql = "SELECT ayar_adi, ayar_degeri FROM sistem_ayarlari";
                $ayarlar_result = $conn->query($ayarlar_sql);
                if ($ayarlar_result) {
                    while ($row = $ayarlar_result->fetch_assoc()) {
                        $fatura_ayarlari[$row['ayar_adi']] = $row['ayar_degeri'];
                    }
                }
                
                // HTML fatura oluştur
                $html = generateInvoiceHTML($fatura, $fatura_ayarlari);
                
                header('Content-Type: text/html; charset=utf-8');
                echo $html;
                
            } catch (Exception $e) {
                header('Content-Type: text/html');
                echo '<h1>Hata: Fatura oluşturulamadı - ' . htmlspecialchars($e->getMessage()) . '</h1>';
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Geçersiz işlem: ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Sistem hatası: ' . $e->getMessage()]);
}

function generateInvoiceHTML($fatura, $fatura_ayarlari) {
    $html = '<!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fatura - ' . htmlspecialchars($fatura['fatura_no']) . '</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
            
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                background: #f8fafc; 
                padding: 20px; 
                color: #1e293b;
                line-height: 1.6;
            }
            
            .invoice-container { 
                max-width: 850px; 
                margin: 0 auto; 
                background: white; 
                border-radius: 16px; 
                overflow: hidden; 
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                border: 1px solid #e2e8f0;
            }
            
            .invoice-header { 
                background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%); 
                color: white; 
                padding: 40px; 
                position: relative;
                overflow: hidden;
            }
            
            .invoice-header::before {
                content: "";
                position: absolute;
                top: 0;
                right: 0;
                width: 200px;
                height: 200px;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 50%;
                transform: translate(50px, -50px);
            }
            
            .header-content { 
                display: flex; 
                justify-content: space-between; 
                align-items: flex-start; 
                position: relative;
                z-index: 2;
            }
            
            .company-section {
                flex: 1;
            }
            
            .company-logo { 
                width: 80px; 
                height: 80px; 
                background: rgba(255, 255, 255, 0.95); 
                border-radius: 20px; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                font-size: 32px; 
                font-weight: 700; 
                color: #1e40af; 
                margin-bottom: 20px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            }
            
            .company-details h1 { 
                font-size: 32px; 
                font-weight: 700; 
                margin-bottom: 8px; 
                letter-spacing: -0.025em;
            }
            
            .company-details p { 
                opacity: 0.9; 
                margin: 4px 0; 
                font-size: 16px;
                font-weight: 400;
            }
            
            .invoice-meta {
                text-align: right;
                min-width: 200px;
            }
            
            .invoice-title {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 16px;
                letter-spacing: -0.025em;
            }
            
            .qr-section {
                background: rgba(255, 255, 255, 0.95);
                border-radius: 12px;
                padding: 16px;
                text-align: center;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            
            .qr-code {
                width: 120px;
                height: 120px;
                margin: 0 auto 12px;
                background: white;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #e2e8f0;
                font-size: 14px;
                color: #64748b;
                font-weight: 500;
            }
            
            .qr-label {
                font-size: 12px;
                color: #64748b;
                font-weight: 500;
            }
            
            .invoice-details {
                padding: 40px;
            }
            
            .details-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 40px;
                margin-bottom: 40px;
            }
            
            .detail-section {
                background: #f8fafc;
                padding: 24px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
            }
            
            .detail-section h3 {
                color: #1e40af;
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .detail-section h3::before {
                content: "";
                width: 4px;
                height: 20px;
                background: #3b82f6;
                border-radius: 2px;
            }
            
            .detail-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 12px 0;
                padding: 8px 0;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .detail-row:last-child {
                border-bottom: none;
            }
            
            .detail-label {
                font-weight: 500;
                color: #475569;
                font-size: 14px;
            }
            
            .detail-value {
                font-weight: 600;
                color: #1e293b;
                font-size: 14px;
            }
            
            .items-section {
                margin: 40px 0;
            }
            
            .section-title {
                font-size: 20px;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .section-title::before {
                content: "";
                width: 6px;
                height: 24px;
                background: linear-gradient(135deg, #3b82f6, #1e40af);
                border-radius: 3px;
            }
            
            .invoice-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
                border: 1px solid #e2e8f0;
            }
            
            .invoice-table th {
                background: linear-gradient(135deg, #1e40af, #3b82f6);
                color: white;
                padding: 20px 16px;
                text-align: left;
                font-weight: 600;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .invoice-table td {
                padding: 20px 16px;
                border-bottom: 1px solid #f1f5f9;
                font-size: 15px;
            }
            
            .invoice-table tbody tr:hover {
                background: #f8fafc;
            }
            
            .invoice-table tbody tr:last-child td {
                border-bottom: none;
            }
            
            .totals-section {
                background: #f8fafc;
                padding: 32px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                margin-top: 32px;
            }
            
            .total-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 12px 0;
                padding: 12px 0;
                font-size: 16px;
            }
            
            .total-row.subtotal {
                color: #64748b;
                font-weight: 500;
            }
            
            .total-row.tax {
                color: #64748b;
                font-weight: 500;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 16px;
                margin-bottom: 16px;
            }
            
            .total-row.final {
                background: linear-gradient(135deg, #1e40af, #3b82f6);
                color: white;
                font-weight: 700;
                font-size: 20px;
                padding: 20px 24px;
                border-radius: 8px;
                margin: 20px 0 0 0;
            }
            
            .invoice-footer {
                background: #f1f5f9;
                padding: 32px;
                text-align: center;
                border-top: 1px solid #e2e8f0;
            }
            
            .footer-content {
                max-width: 600px;
                margin: 0 auto;
            }
            
            .footer-title {
                font-size: 16px;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 12px;
            }
            
            .footer-text {
                color: #64748b;
                font-size: 14px;
                line-height: 1.6;
            }
            
            .footer-details {
                display: flex;
                justify-content: center;
                gap: 32px;
                margin-top: 20px;
                flex-wrap: wrap;
            }
            
            .footer-item {
                font-size: 13px;
                color: #64748b;
            }
            
            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #dcfce7;
                color: #166534;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .status-badge::before {
                content: "";
                width: 6px;
                height: 6px;
                background: #22c55e;
                border-radius: 50%;
            }
            
            @media print {
                body { background: white; padding: 0; }
                .invoice-container { box-shadow: none; border: none; }
            }
            
            @media (max-width: 768px) {
                .details-grid { grid-template-columns: 1fr; gap: 20px; }
                .header-content { flex-direction: column; gap: 24px; text-align: center; }
                .invoice-meta { text-align: center; min-width: auto; }
                .invoice-table { font-size: 13px; }
                .invoice-table th, .invoice-table td { padding: 12px 8px; }
                .footer-details { flex-direction: column; gap: 8px; }
                .invoice-details { padding: 24px; }
                .totals-section { padding: 24px; }
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="invoice-header">
                <div class="header-content">
                    <div class="company-section">
                        <div class="company-logo">₿</div>
                        <div class="company-details">
                            <h1>' . ($fatura_ayarlari['fatura_sirket_adi'] ?? 'Crypto Finance Ltd.') . '</h1>
                            <p>📍 ' . ($fatura_ayarlari['fatura_adres'] ?? 'Maslak Mahallesi, Büyükdere Caddesi No:255, 34398 Sarıyer/İstanbul') . '</p>
                            <p>📞 ' . ($fatura_ayarlari['fatura_telefon'] ?? '+90 212 555 0123') . '</p>
                            <p>✉️ ' . ($fatura_ayarlari['fatura_email'] ?? 'info@cryptofinance.com') . '</p>
                            <p>🌐 www.cryptofinance.com</p>
                        </div>
                    </div>
                    <div class="invoice-meta">
                        <div class="invoice-title">FATURA</div>
                        <div class="qr-section">
                            <div class="qr-code">
                                QR Kod<br>Alanı
                            </div>
                            <div class="qr-label">Dijital Doğrulama</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="invoice-details">
                <div class="details-grid">
                    <div class="detail-section">
                        <h3>Müşteri Bilgileri</h3>
                        <div class="detail-row">
                            <span class="detail-label">Ad Soyad:</span>
                            <span class="detail-value">' . htmlspecialchars($fatura['ad_soyad'] ?? $fatura['username']) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">TC No:</span>
                            <span class="detail-value">' . htmlspecialchars($fatura['tc_no'] ?? 'Belirtilmemiş') . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">E-posta:</span>
                            <span class="detail-value">' . htmlspecialchars($fatura['email']) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">IBAN:</span>
                            <span class="detail-value">' . htmlspecialchars($fatura['iban'] ?? 'Belirtilmemiş') . '</span>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h3>Fatura Bilgileri</h3>
                        <div class="detail-row">
                            <span class="detail-label">Fatura No:</span>
                            <span class="detail-value">' . htmlspecialchars($fatura['fatura_no']) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Tarih:</span>
                            <span class="detail-value">' . date('d.m.Y H:i', strtotime($fatura['tarih'])) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">İşlem Tipi:</span>
                            <span class="detail-value">' . ucfirst(str_replace('_', ' ', $fatura['islem_tipi'])) . '</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Durum:</span>
                            <span class="detail-value"><span class="status-badge">Tamamlandı</span></span>
                        </div>
                    </div>
                </div>
                
                <div class="items-section">
                    <h2 class="section-title">Fatura Detayları</h2>
                    <table class="invoice-table">
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
                                <td>' . ucfirst(str_replace('_', ' ', $fatura['islem_tipi'])) . ' İşlemi</td>
                                <td>1</td>
                                <td>₺' . number_format($fatura['tutar'], 2, ',', '.') . '</td>
                                <td>₺' . number_format($fatura['tutar'], 2, ',', '.') . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="totals-section">
                    <div class="total-row subtotal">
                        <span>Ara Toplam:</span>
                        <span>₺' . number_format($fatura['tutar'], 2, ',', '.') . '</span>
                    </div>
                    <div class="total-row tax">
                        <span>KDV (%' . ($fatura['kdv_orani'] ?? 0) . '):</span>
                        <span>₺' . number_format($fatura['kdv_tutari'] ?? 0, 2, ',', '.') . '</span>
                    </div>
                    <div class="total-row final">
                        <span>Genel Toplam:</span>
                        <span>₺' . number_format($fatura['toplam_tutar'] ?? $fatura['tutar'], 2, ',', '.') . '</span>
                    </div>
                </div>
            </div>
            
            <div class="invoice-footer">
                <div class="footer-content">
                    <div class="footer-title">Teşekkür Ederiz</div>
                    <p class="footer-text">Bu belge elektronik ortamda oluşturulmuştur ve yasal geçerliliğe sahiptir.</p>
                    <div class="footer-details">
                        <span class="footer-item">Vergi No: ' . ($fatura_ayarlari['fatura_vergi_no'] ?? '1234567890') . '</span>
                        <span class="footer-item">Mersis No: 0123456789012345</span>
                        <span class="footer-item">KEP: info@cryptofinance.hs01.kep.tr</span>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>
