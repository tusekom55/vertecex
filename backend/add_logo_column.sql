-- Coins tablosuna logo_path sütunu ekle
ALTER TABLE coins ADD COLUMN logo_path VARCHAR(255) NULL AFTER aciklama;

-- Mevcut coinlere varsayılan logo path'leri ekle (opsiyonel)
UPDATE coins SET logo_path = CONCAT('coin_logos/', LOWER(coin_kodu), '_logo.png') WHERE logo_path IS NULL;

-- Index ekle (performans için)
CREATE INDEX idx_coins_logo_path ON coins(logo_path);
