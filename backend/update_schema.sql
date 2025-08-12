-- VERİTABANI GÜNCELLEME SCRIPT'İ
-- Mevcut coins tablosunu hibrit sisteme uyarlama

-- Önce mevcut coins tablosunu yedekle (isteğe bağlı)
-- CREATE TABLE coins_backup AS SELECT * FROM coins;

-- Yeni kolonları ekle
ALTER TABLE coins 
ADD COLUMN coingecko_id VARCHAR(100) UNIQUE AFTER kategori_id,
ADD COLUMN logo_url VARCHAR(500) AFTER coin_kodu,
ADD COLUMN aciklama TEXT AFTER logo_url,
ADD COLUMN current_price DECIMAL(16,8) DEFAULT 0 AFTER aciklama,
ADD COLUMN price_change_24h DECIMAL(5,2) DEFAULT 0 AFTER current_price,
ADD COLUMN market_cap BIGINT DEFAULT 0 AFTER price_change_24h,
ADD COLUMN api_aktif BOOLEAN DEFAULT FALSE AFTER market_cap,
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER api_aktif,
ADD COLUMN sira INT DEFAULT 0 AFTER is_active,
ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER sira,
ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Fiyat geçmişi tablosu oluştur
CREATE TABLE IF NOT EXISTS coin_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coin_id INT,
    price DECIMAL(16,8),
    price_change_24h DECIMAL(5,2),
    market_cap BIGINT,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coin_id) REFERENCES coins(id) ON DELETE CASCADE
);

-- Loglar tablosunu güncelle
ALTER TABLE loglar 
MODIFY COLUMN tip ENUM('para_yatirma','coin_islem','api_guncelleme');

-- Ayarlar tablosuna varsayılan değerler ekle
INSERT IGNORE INTO ayarlar (`key`, `value`) VALUES 
('api_guncelleme_aktif', 'false'),
('api_guncelleme_siklik', '300'),
('varsayilan_fiyat_kaynak', 'manuel');

-- Kategori tablosunu kontrol et ve eksikleri ekle
INSERT IGNORE INTO coin_kategorileri (id, kategori_adi) VALUES 
(1, 'Major Coins'),
(2, 'Altcoins'),
(3, 'DeFi'),
(4, 'NFT'),
(5, 'Meme Coins'),
(6, 'Stablecoins');

-- Mevcut coin verilerini temizle (eski format)
DELETE FROM coins WHERE coingecko_id IS NULL;

-- Kaldıraçlı işlemler için pozisyon tablosu
CREATE TABLE IF NOT EXISTS leverage_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coin_symbol VARCHAR(10) NOT NULL,
    position_type ENUM('long', 'short') NOT NULL,
    leverage_ratio DECIMAL(3,1) NOT NULL, -- 1.0, 2.0, 5.0, 10.0
    entry_price DECIMAL(20,8) NOT NULL,
    position_size DECIMAL(20,8) NOT NULL, -- Gerçek pozisyon büyüklüğü (kaldıraçlı)
    invested_amount DECIMAL(20,8) NOT NULL, -- Kullanıcının yatırdığı miktar
    current_price DECIMAL(20,8) DEFAULT NULL,
    unrealized_pnl DECIMAL(20,8) DEFAULT 0,
    status ENUM('open', 'closed', 'liquidated') DEFAULT 'open',
    stop_loss_price DECIMAL(20,8) DEFAULT NULL,
    take_profit_price DECIMAL(20,8) DEFAULT NULL,
    liquidation_price DECIMAL(20,8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    close_price DECIMAL(20,8) DEFAULT NULL,
    realized_pnl DECIMAL(20,8) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_coin_status (coin_symbol, status)
);

-- Pozisyon geçmişi tablosu
CREATE TABLE IF NOT EXISTS position_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('open', 'close', 'liquidate', 'update') NOT NULL,
    price DECIMAL(20,8) NOT NULL,
    pnl DECIMAL(20,8) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES leverage_positions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Risk yönetimi ayarları tablosu
CREATE TABLE IF NOT EXISTS leverage_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    max_leverage DECIMAL(3,1) DEFAULT 10.0,
    default_leverage DECIMAL(3,1) DEFAULT 1.0,
    auto_close_enabled BOOLEAN DEFAULT TRUE,
    max_loss_percentage DECIMAL(5,2) DEFAULT 80.00, -- %80 zarar durumunda otomatik kapat
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SELECT 'Kaldıraçlı işlem sistemi eklendi!' as Status; 