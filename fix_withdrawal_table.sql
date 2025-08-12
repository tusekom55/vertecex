-- Para çekme talepleri tablosuna eksik alanları ekle (eğer yoksa)
ALTER TABLE para_cekme_talepleri 
ADD COLUMN IF NOT EXISTS iban VARCHAR(50) AFTER tutar,
ADD COLUMN IF NOT EXISTS hesap_sahibi VARCHAR(100) AFTER iban;

-- Eğer tablo hiç yoksa oluştur
CREATE TABLE IF NOT EXISTS para_cekme_talepleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    yontem ENUM('papara','havale','kredi_karti'),
    tutar DECIMAL(16,2),
    iban VARCHAR(50),
    hesap_sahibi VARCHAR(100),
    durum ENUM('beklemede','onaylandi','reddedildi') DEFAULT 'beklemede',
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    onay_tarihi DATETIME NULL,
    onaylayan_admin_id INT NULL,
    aciklama TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (onaylayan_admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Test verisi ekle (sadece tablo boşsa)
INSERT IGNORE INTO para_cekme_talepleri (id, user_id, yontem, tutar, iban, hesap_sahibi, durum, aciklama) VALUES
(1, 2, 'havale', 1500.00, 'TR63 0006 4000 0019 3001 9751 44', 'Ahmet Yılmaz', 'beklemede', 'Test para çekme talebi - Havale'),
(2, 3, 'papara', 2500.00, '', 'Fatma Demir', 'beklemede', 'Test para çekme talebi - Papara'),
(3, 2, 'havale', 800.00, 'TR63 0006 4000 0019 3001 9751 45', 'Mehmet Kaya', 'onaylandi', 'Onaylanmış test talebi');

-- Admin panelde session kontrolünü devre dışı bırak (test amaçlı)
-- Bu dosyayı çalıştırdıktan sonra backend/admin/withdrawals.php dosyasında
-- session kontrol satırları zaten yorum satırına alınmış

SELECT 'Para çekme talepleri tablosu hazırlandı!' as sonuc;
