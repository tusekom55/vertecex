-- Veritabanı şeması güncellemeleri
-- Fiyat yönetim sistemi için gerekli sütunlar

-- Coins tablosuna yeni sütunlar ekle (tek tek)
ALTER TABLE coins ADD COLUMN price_source VARCHAR(20) DEFAULT 'manual';
ALTER TABLE coins ADD COLUMN last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE coins ADD COLUMN is_api_coin BOOLEAN DEFAULT FALSE;

-- Fiyat güncelleme logları için tablo
CREATE TABLE IF NOT EXISTS price_update_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    api_success BOOLEAN DEFAULT FALSE,
    manual_success BOOLEAN DEFAULT FALSE,
    updated_coins_count INT DEFAULT 0,
    error_message TEXT NULL
);

-- API coinlerini işaretle
UPDATE coins SET is_api_coin = TRUE, price_source = 'api' 
WHERE coin_kodu IN ('BTC', 'ETH', 'BNB', 'XRP', 'USDT', 'ADA', 'SOL', 'DOGE', 'MATIC', 'DOT');

-- Manuel coinleri işaretle
UPDATE coins SET is_api_coin = FALSE, price_source = 'manual' 
WHERE coin_kodu IN ('T', 'SEX', 'TTT');

-- Cache klasörü için izinler (PHP tarafından oluşturulacak)
-- mkdir backend/cache (PHP kodu ile otomatik oluşturulacak)

-- Örnek veri eklemeleri (eğer yoksa)
INSERT IGNORE INTO coins (coin_adi, coin_kodu, current_price, is_active, is_api_coin, price_source) VALUES
('Tugaycoin', 'T', 150000, 1, 0, 'manual'),
('SEX Coin', 'SEX', 120, 1, 0, 'manual'),
('Dolar Coin', 'TTT', 34.50, 1, 0, 'manual');

-- İndeksler
CREATE INDEX IF NOT EXISTS idx_coins_api ON coins(is_api_coin);
CREATE INDEX IF NOT EXISTS idx_coins_source ON coins(price_source);
CREATE INDEX IF NOT EXISTS idx_coins_update ON coins(last_update);
