-- Users tablosuna eksik sütunları ekle
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS son_giris DATETIME NULL,
ADD COLUMN IF NOT EXISTS ip_adresi VARCHAR(45) NULL;

-- Mevcut kullanıcıları aktif yap
UPDATE users SET is_active = TRUE WHERE is_active IS NULL;
