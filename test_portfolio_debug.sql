-- Portfolio debug ve test scripti

-- 1. Tabloları kontrol et
SELECT 'Checking tables...' as step;

SHOW TABLES LIKE 'users';
SHOW TABLES LIKE 'coins';
SHOW TABLES LIKE 'coin_islemleri';

-- 2. Kullanıcıları kontrol et
SELECT 'Checking users...' as step;
SELECT id, username, balance FROM users LIMIT 5;

-- 3. Coinleri kontrol et  
SELECT 'Checking coins...' as step;
SELECT id, coin_adi, coin_kodu, current_price, is_active FROM coins WHERE is_active = 1 LIMIT 5;

-- 4. Coin işlemlerini kontrol et
SELECT 'Checking coin transactions...' as step;
SELECT ci.*, c.coin_kodu 
FROM coin_islemleri ci 
LEFT JOIN coins c ON ci.coin_id = c.id 
ORDER BY ci.tarih DESC 
LIMIT 10;

-- 5. Portfolio hesaplama testi (user_id = 1 için)
SELECT 'Portfolio calculation test...' as step;
SELECT 
    ci.coin_id,
    c.coin_adi,
    c.coin_kodu,
    c.logo_url,
    c.current_price,
    c.price_change_24h,
    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
    AVG(CASE WHEN ci.islem = 'al' THEN ci.fiyat ELSE NULL END) as avg_buy_price
FROM coin_islemleri ci
JOIN coins c ON ci.coin_id = c.id
WHERE ci.user_id = 1 AND c.is_active = 1
GROUP BY ci.coin_id
HAVING net_miktar > 0
ORDER BY (net_miktar * c.current_price) DESC;

-- 6. Eğer veri yoksa sample data ekle
INSERT IGNORE INTO coins (id, coin_adi, coin_kodu, current_price, price_change_24h, is_active, logo_url) VALUES 
(1, 'Bitcoin', 'BTC', 50000.00, 2.5, 1, 'https://cryptologos.cc/logos/bitcoin-btc-logo.png'),
(2, 'Ethereum', 'ETH', 3000.00, -1.2, 1, 'https://cryptologos.cc/logos/ethereum-eth-logo.png'),
(3, 'Binance Coin', 'BNB', 300.00, 0.8, 1, 'https://cryptologos.cc/logos/binance-coin-bnb-logo.png');

-- 7. Test user'ı kontrol et, yoksa ekle
INSERT IGNORE INTO users (id, username, email, password, balance, role) VALUES 
(1, 'test_user', 'test@example.com', 'test123', 10000.00, 'user');

-- 8. Sample işlemler ekle (sadece yoksa)
INSERT IGNORE INTO coin_islemleri (user_id, coin_id, islem, miktar, fiyat, tarih) VALUES 
(1, 1, 'al', 0.001, 48000.00, NOW()),
(1, 2, 'al', 0.5, 2800.00, NOW()),
(1, 3, 'al', 2, 290.00, NOW()),
(1, 2, 'sat', 0.1, 2900.00, NOW());

-- 9. Final portfolio check
SELECT 'Final portfolio result...' as step;
SELECT 
    ci.coin_id,
    c.coin_adi,
    c.coin_kodu,
    c.current_price,
    SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) as net_miktar,
    AVG(CASE WHEN ci.islem = 'al' THEN ci.fiyat ELSE NULL END) as avg_buy_price,
    (SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) * c.current_price) as current_value,
    (SUM(CASE WHEN ci.islem = 'al' THEN ci.miktar ELSE -ci.miktar END) * AVG(CASE WHEN ci.islem = 'al' THEN ci.fiyat ELSE NULL END)) as invested_value
FROM coin_islemleri ci
JOIN coins c ON ci.coin_id = c.id
WHERE ci.user_id = 1 AND c.is_active = 1
GROUP BY ci.coin_id
HAVING net_miktar > 0
ORDER BY current_value DESC;