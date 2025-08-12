-- Admin Panel için Ek Tablolar

-- Para çekme talepleri
CREATE TABLE para_cekme_talepleri (
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

-- Fatura tablosu
CREATE TABLE faturalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    islem_tipi ENUM('para_yatirma','para_cekme','coin_islem'),
    islem_id INT, -- İlgili işlem tablosundaki ID
    fatura_no VARCHAR(50) UNIQUE,
    tutar DECIMAL(16,2),
    kdv_orani DECIMAL(5,2) DEFAULT 0,
    kdv_tutari DECIMAL(16,2) DEFAULT 0,
    toplam_tutar DECIMAL(16,2),
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sistem ayarları (genişletilmiş)
CREATE TABLE sistem_ayarlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ayar_adi VARCHAR(100) UNIQUE,
    ayar_degeri TEXT,
    aciklama TEXT,
    guncelleme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Ödeme yöntemleri ayarları
INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, aciklama) VALUES
('papara_numara', '', 'Papara numarası'),
('banka_adi', '', 'Banka adı'),
('banka_iban', '', 'Banka IBAN numarası'),
('banka_hesap_sahibi', '', 'Banka hesap sahibi'),
('kart_komisyon_orani', '2.5', 'Kredi kartı komisyon oranı (%)'),
('papara_komisyon_orani', '1.0', 'Papara komisyon oranı (%)'),
('havale_komisyon_orani', '0.0', 'Havale komisyon oranı (%)'),
('minimum_cekme_tutari', '50', 'Minimum para çekme tutarı'),
('maksimum_cekme_tutari', '10000', 'Maksimum para çekme tutarı'),
('fatura_sirket_adi', 'Crypto Trading Platform', 'Fatura şirket adı'),
('fatura_adres', '', 'Fatura adresi'),
('fatura_vergi_no', '', 'Fatura vergi numarası'),
('fatura_telefon', '', 'Fatura telefon numarası'),
('fatura_email', '', 'Fatura email adresi');

-- Kullanıcı işlem geçmişi (genişletilmiş)
CREATE TABLE kullanici_islem_gecmisi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    islem_tipi ENUM('para_yatirma','para_cekme','coin_al','coin_sat','bakiye_guncelleme'),
    islem_detayi TEXT,
    tutar DECIMAL(16,2),
    onceki_bakiye DECIMAL(16,2),
    sonraki_bakiye DECIMAL(16,2),
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_adresi VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Admin işlem logları
CREATE TABLE admin_islem_loglari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    islem_tipi ENUM('kullanici_duzenleme','kullanici_silme','para_onaylama','coin_ekleme','coin_duzenleme','coin_silme','ayar_guncelleme'),
    hedef_id INT, -- İşlem yapılan kayıt ID'si
    islem_detayi TEXT,
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Coin kategorileri (genişletilmiş)
ALTER TABLE coin_kategorileri ADD COLUMN aciklama TEXT AFTER kategori_adi;
ALTER TABLE coin_kategorileri ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER aciklama;
ALTER TABLE coin_kategorileri ADD COLUMN sira INT DEFAULT 0 AFTER is_active;

-- Kullanıcılar tablosuna ek alanlar
ALTER TABLE users ADD COLUMN telefon VARCHAR(20) AFTER email;
ALTER TABLE users ADD COLUMN ad_soyad VARCHAR(100) AFTER telefon;
ALTER TABLE users ADD COLUMN tc_no VARCHAR(11) AFTER ad_soyad;
ALTER TABLE users ADD COLUMN dogum_tarihi DATE AFTER tc_no;
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER role;
ALTER TABLE users ADD COLUMN son_giris DATETIME AFTER is_active;
ALTER TABLE users ADD COLUMN ip_adresi VARCHAR(45) AFTER son_giris;

-- Para yatırma talepleri tablosuna ek alanlar
ALTER TABLE para_yatirma_talepleri ADD COLUMN onay_tarihi DATETIME NULL AFTER durum;
ALTER TABLE para_yatirma_talepleri ADD COLUMN onaylayan_admin_id INT NULL AFTER onay_tarihi;
ALTER TABLE para_yatirma_talepleri ADD COLUMN aciklama TEXT AFTER detay_bilgiler;
ALTER TABLE para_yatirma_talepleri ADD FOREIGN KEY (onaylayan_admin_id) REFERENCES users(id) ON DELETE SET NULL;

-- Örnek admin kullanıcısı
INSERT INTO users (username, email, password, role, balance, ad_soyad, is_active) VALUES
('admin', 'admin@cryptotrading.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0, 'Sistem Yöneticisi', TRUE);

-- Örnek kategoriler
INSERT INTO coin_kategorileri (kategori_adi, aciklama, is_active, sira) VALUES 
('Major Coins', 'Bitcoin, Ethereum gibi büyük coinler', TRUE, 1),
('Altcoins', 'Alternatif coinler', TRUE, 2),
('DeFi', 'Merkeziyetsiz finans coinleri', TRUE, 3),
('Meme Coins', 'Eğlence amaçlı coinler', TRUE, 4),
('Stablecoins', 'Sabit değerli coinler', TRUE, 5); 