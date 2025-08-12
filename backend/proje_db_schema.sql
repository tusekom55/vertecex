-- Kullanıcılar
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('user','admin') DEFAULT 'user',
    balance DECIMAL(16,2) DEFAULT 0,
    ad_soyad VARCHAR(100),
    tc_no VARCHAR(11),
    telefon VARCHAR(20),
    iban VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Coin kategorileri
CREATE TABLE coin_kategorileri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_adi VARCHAR(100)
);

-- Coinler (Hibrit Sistem)
CREATE TABLE coins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_id INT,
    coingecko_id VARCHAR(100) UNIQUE, -- CoinGecko API ID'si (bitcoin, ethereum vb.)
    coin_adi VARCHAR(100),
    coin_kodu VARCHAR(20), -- BTC, ETH vb.
    logo_url VARCHAR(500), -- Coin logosu URL'i
    aciklama TEXT, -- Coin açıklaması
    current_price DECIMAL(16,8) DEFAULT 0, -- Sabit fiyat (API kapalıysa)
    price_change_24h DECIMAL(5,2) DEFAULT 0, -- 24 saatlik değişim %
    market_cap BIGINT DEFAULT 0, -- Piyasa değeri
    api_aktif BOOLEAN DEFAULT FALSE, -- Fiyat API'den çekilsin mi?
    is_active BOOLEAN DEFAULT TRUE, -- Coin listede görünsün mü?
    sira INT DEFAULT 0, -- Listeleme sırası
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES coin_kategorileri(id) ON DELETE SET NULL
);

-- Coin işlemleri (al/sat)
CREATE TABLE coin_islemleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    coin_id INT,
    islem ENUM('al','sat'),
    miktar DECIMAL(16,8),
    fiyat DECIMAL(16,2),
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coin_id) REFERENCES coins(id) ON DELETE CASCADE
);

-- Para yatırma talepleri
CREATE TABLE para_yatirma_talepleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    yontem ENUM('kredi_karti','papara','havale'),
    tutar DECIMAL(16,2),
    durum ENUM('beklemede','onaylandi','reddedildi') DEFAULT 'beklemede',
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    detay_bilgiler TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ayarlar (API ve Sistem Ayarları)
CREATE TABLE ayarlar (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT
);

-- Loglar
CREATE TABLE loglar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    tip ENUM('para_yatirma','coin_islem','api_guncelleme'),
    detay TEXT,
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Fiyat geçmişi (isteğe bağlı)
CREATE TABLE coin_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coin_id INT,
    price DECIMAL(16,8),
    price_change_24h DECIMAL(5,2),
    market_cap BIGINT,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coin_id) REFERENCES coins(id) ON DELETE CASCADE
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

-- Para çekme talepleri
CREATE TABLE para_cekme_talepleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    yontem ENUM('papara','havale','kredi_karti'),
    tutar DECIMAL(16,2),
    durum ENUM('beklemede','onaylandi','reddedildi') DEFAULT 'beklemede',
    tarih DATETIME DEFAULT CURRENT_TIMESTAMP,
    onay_tarihi DATETIME NULL,
    onaylayan_admin_id INT NULL,
    aciklama TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (onaylayan_admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Sistem ayarları
CREATE TABLE sistem_ayarlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ayar_adi VARCHAR(100) UNIQUE,
    ayar_degeri TEXT,
    aciklama TEXT,
    guncelleme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Kullanıcı işlem geçmişi
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

-- Başlangıç ayarları ekleme
INSERT INTO ayarlar (`key`, `value`) VALUES 
('api_guncelleme_aktif', 'false'),
('api_guncelleme_siklik', '300'), -- 5 dakika (saniye)
('varsayilan_fiyat_kaynak', 'manuel'); -- manuel veya api

-- Örnek kategoriler
INSERT INTO coin_kategorileri (kategori_adi) VALUES 
('Major Coins'),
('Altcoins'),
('DeFi'),
('NFT'),
('Meme Coins'),
('Stablecoins');

-- Örnek kullanıcılar
INSERT INTO users (username, email, password, role, balance, ad_soyad, tc_no, telefon, iban) VALUES 
('admin', 'admin@cryptofinance.com', 'password', 'admin', 10000.00, 'Admin User', '12345678901', '+905551234567', 'TR63 0006 4000 0019 3001 9751 44'),
('user1', 'user1@example.com', 'password', 'user', 5000.00, 'Ahmet Yılmaz', '12345678902', '+905551234568', 'TR63 0006 4000 0019 3001 9751 45'),
('user2', 'user2@example.com', 'password', 'user', 3000.00, 'Fatma Demir', '12345678903', '+905551234569', 'TR63 0006 4000 0019 3001 9751 46');

-- Sistem ayarları
INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, aciklama) VALUES
('papara_numara', '+905551234567', 'Papara numarası'),
('banka_adi', 'Garanti BBVA', 'Banka adı'),
('banka_iban', 'TR63 0006 4000 0019 3001 9751 44', 'Banka IBAN numarası'),
('banka_hesap_sahibi', 'Crypto Finance Ltd.', 'Banka hesap sahibi'),
('kart_komisyon_orani', '2.5', 'Kredi kartı komisyon oranı (%)'),
('papara_komisyon_orani', '1.0', 'Papara komisyon oranı (%)'),
('havale_komisyon_orani', '0.0', 'Havale komisyon oranı (%)'),
('minimum_cekme_tutari', '50', 'Minimum para çekme tutarı'),
('maksimum_cekme_tutari', '10000', 'Maksimum para çekme tutarı'),
('fatura_sirket_adi', 'Crypto Finance Ltd.', 'Fatura şirket adı'),
('fatura_adres', 'İstanbul, Türkiye', 'Fatura adresi'),
('fatura_vergi_no', '1234567890', 'Fatura vergi numarası'),
('fatura_telefon', '+90 212 555 0123', 'Fatura telefon numarası'),
('fatura_email', 'info@cryptofinance.com', 'Fatura email adresi');

-- Örnek para çekme talepleri
INSERT INTO para_cekme_talepleri (user_id, yontem, tutar, durum, aciklama) VALUES
(2, 'papara', 500.00, 'beklemede', 'Test para çekme talebi'),
(3, 'havale', 1000.00, 'onaylandi', 'Onaylanmış test talebi'); 