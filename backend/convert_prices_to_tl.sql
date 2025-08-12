-- Mevcut USD fiyatlarını TL'ye çevirme scripti
-- Bu script sadece bir kez çalıştırılmalıdır

-- Önce backup alalım (opsiyonel)
-- CREATE TABLE coins_backup AS SELECT * FROM coins;

-- USD fiyatlarını TL'ye çevir (1 USD = 30 TL kuru ile)
UPDATE coins 
SET current_price = current_price * 30.0
WHERE current_price > 0 AND current_price < 1000; -- Sadece USD gibi görünen düşük fiyatları çevir

-- Güncelleme sonrası kontrol sorgusu
SELECT 
    id,
    coin_adi,
    coin_kodu,
    current_price as fiyat_tl,
    ROUND(current_price / 30.0, 8) as fiyat_usd_tahmini,
    updated_at
FROM coins 
WHERE is_active = 1
ORDER BY current_price DESC;

-- Log kaydı
INSERT INTO admin_islem_loglari (admin_id, islem_tipi, hedef_id, islem_detayi, tarih)
VALUES (1, 'sistem_guncelleme', 0, 'Coin fiyatları USD''den TL''ye çevrildi (1 USD = 30 TL)', NOW());
