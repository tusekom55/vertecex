-- Test coin ekleme
-- Önce kategorileri ekleyelim
INSERT INTO coin_kategorileri (id, kategori_adi) VALUES 
(1, 'Major Coins'),
(2, 'Altcoins'),
(3, 'DeFi'),
(4, 'NFT'),
(5, 'Meme Coins'),
(6, 'Stablecoins')
ON DUPLICATE KEY UPDATE kategori_adi = VALUES(kategori_adi);

-- Test coin ekle
INSERT INTO coins (coin_adi, coin_kodu, current_price, aciklama, kategori_id, is_active) 
VALUES ('Bitcoin Test', 'BTCTEST', 50000.00, 'Test coin', 1, 1);

-- Coinleri listele
SELECT c.id, c.coin_adi, c.coin_kodu, c.current_price, c.aciklama, 
       ck.kategori_adi, c.created_at
FROM coins c
LEFT JOIN coin_kategorileri ck ON c.kategori_id = ck.id
ORDER BY c.created_at DESC;