-- Portfolio test data ekleme ve kontrol scripti

-- 1. Mevcut coins tablosunu kontrol et
SELECT 'Current coins:' as info;
SELECT id, coin_adi, coin_kodu, current_price, is_active FROM coins WHERE is_active = 1;

-- 2. Coin işlemlerini kontrol et
SELECT 'Current coin transactions:' as info;
SELECT * FROM coin_islemleri ORDER BY tarih DESC LIMIT 10;

-- 3. Test kullanıcısının portföyünü hesapla
SELECT 'Portfolio calculation for user 1:' as info;
SELECT 
    ci.coin_id,
    c.coin_adi,
    c.coin_kodu,
    c.current_price,
    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
    AVG(CASE WHEN ci.islem = 'al' THEN ci.fiyat ELSE NULL END) as avg_buy_price
FROM coin_islemleri ci
JOIN coins c ON ci.coin_id = c.id
WHERE ci.user_id = 1 AND c.is_active = 1
GROUP BY ci.coin_id
HAVING net_miktar > 0
ORDER BY (net_miktar * c.current_price) DESC;

-- 4. Eğer coin yok ise sample coinler ekle
INSERT INTO coins (coin_adi, coin_kodu, current_price, price_change_24h, is_active, logo_url) VALUES 
('Bitcoin', 'BTC', 50000.00, 2.5, 1, 'https://cryptologos.cc/logos/bitcoin-btc-logo.png'),
('Ethereum', 'ETH', 3000.00, -1.2, 1, 'https://cryptologos.cc/logos/ethereum-eth-logo.png'),
('Binance Coin', 'BNB', 300.00, 0.8, 1, 'https://cryptologos.cc/logos/binance-coin-bnb-logo.png')
ON DUPLICATE KEY UPDATE current_price = VALUES(current_price);

-- 5. Sample işlemler ekle (kullanıcı ID 1 için)
INSERT INTO coin_islemleri (user_id, coin_id, islem, miktar, fiyat, tarih) VALUES 
(1, 1, 'al', 0.001, 48000.00, NOW()),
(1, 2, 'al', 0.5, 2800.00, NOW()),
(1, 3, 'al', 2, 290.00, NOW()),
(1, 2, 'sat', 0.1, 2900.00, NOW())
ON DUPLICATE KEY UPDATE tarih = VALUES(tarih);

-- 6. Portfolio kontrol et (sonuç)
SELECT 'Final portfolio check:' as info;
SELECT 
    ci.coin_id,
    c.coin_adi,
    c.coin_kodu,
    c.current_price,
    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
    AVG(CASE WHEN ci.islem = 'al' THEN ci.fiyat ELSE NULL END) as avg_buy_price,
    (SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) * c.current_price) as current_value
FROM coin_islemleri ci
JOIN coins c ON ci.coin_id = c.id
WHERE ci.user_id = 1 AND c.is_active = 1
GROUP BY ci.coin_id
HAVING net_miktar > 0
ORDER BY current_value DESC;