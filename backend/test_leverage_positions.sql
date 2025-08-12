-- Test leverage pozisyonları
INSERT INTO leverage_positions (user_id, coin_symbol, position_type, leverage_ratio, entry_price, position_size, invested_amount, current_price, unrealized_pnl, status, liquidation_price, created_at) VALUES
(2, 'BTC', 'long', 2.0, 1350000.00, 0.001, 675.00, 1350000.00, 0.00, 'open', 675000.00, NOW() - INTERVAL 1 DAY),
(2, 'ETH', 'short', 3.0, 85000.00, 0.01, 283.33, 85000.00, 0.00, 'open', 113333.33, NOW() - INTERVAL 2 DAY),
(3, 'BNB', 'long', 1.5, 4500.00, 0.1, 300.00, 4500.00, 0.00, 'open', 3000.00, NOW() - INTERVAL 3 DAY),
(4, 'ADA', 'long', 5.0, 2.50, 100, 50.00, 2.50, 0.00, 'open', 2.00, NOW() - INTERVAL 4 DAY);

-- Test coins verisi
INSERT INTO coins (coingecko_id, coin_adi, coin_kodu, current_price, price_change_24h, market_cap, api_aktif, is_active, sira) VALUES
('bitcoin', 'Bitcoin', 'BTC', 1350000.00, 2.5, 2500000000000, TRUE, TRUE, 1),
('ethereum', 'Ethereum', 'ETH', 85000.00, -1.2, 1000000000000, TRUE, TRUE, 2),
('binancecoin', 'Binance Coin', 'BNB', 4500.00, 0.8, 75000000000, TRUE, TRUE, 3),
('cardano', 'Cardano', 'ADA', 2.50, 1.5, 35000000000, TRUE, TRUE, 4); 