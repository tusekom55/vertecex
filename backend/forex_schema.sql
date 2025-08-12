-- BORSA/FOREX KALDIRAÇLI İŞLEM SİSTEMİ
-- Kapsamlı veritabanı şeması

-- Forex/Borsa çiftleri
CREATE TABLE forex_pairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) UNIQUE NOT NULL, -- EURUSD, GBPUSD, USDJPY vb.
    base_currency VARCHAR(3) NOT NULL, -- EUR, GBP, USD
    quote_currency VARCHAR(3) NOT NULL, -- USD, JPY, GBP
    display_name VARCHAR(20) NOT NULL, -- EUR/USD, GBP/USD
    category ENUM('major', 'minor', 'exotic', 'indices', 'commodities') DEFAULT 'major',
    current_price DECIMAL(10,5) DEFAULT 0,
    bid_price DECIMAL(10,5) DEFAULT 0,
    ask_price DECIMAL(10,5) DEFAULT 0,
    spread DECIMAL(6,5) DEFAULT 0,
    pip_value DECIMAL(8,5) DEFAULT 0.0001, -- 1 pip değeri
    min_trade_size DECIMAL(10,2) DEFAULT 0.01, -- Minimum lot boyutu
    max_leverage INT DEFAULT 100, -- Maksimum kaldıraç
    is_active BOOLEAN DEFAULT TRUE,
    trading_hours VARCHAR(100), -- "24/5", "09:30-16:00" vb.
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Kaldıraçlı işlemler/pozisyonlar
CREATE TABLE leverage_trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pair_id INT NOT NULL,
    trade_type ENUM('long', 'short') NOT NULL,
    lot_size DECIMAL(10,2) NOT NULL, -- 0.01, 0.1, 1.0 vb.
    leverage_ratio INT NOT NULL, -- 10, 50, 100, 500
    entry_price DECIMAL(10,5) NOT NULL,
    current_price DECIMAL(10,5) DEFAULT 0,
    stop_loss DECIMAL(10,5) DEFAULT NULL,
    take_profit DECIMAL(10,5) DEFAULT NULL,
    margin_required DECIMAL(16,2) NOT NULL, -- Gerekli margin
    margin_used DECIMAL(16,2) NOT NULL, -- Kullanılan margin
    unrealized_pnl DECIMAL(16,2) DEFAULT 0, -- Gerçekleşmemiş kar/zarar
    realized_pnl DECIMAL(16,2) DEFAULT 0, -- Gerçekleşmiş kar/zarar
    commission DECIMAL(10,2) DEFAULT 0, -- Komisyon
    swap_fee DECIMAL(10,2) DEFAULT 0, -- Overnight fee
    status ENUM('open', 'closed', 'margin_call', 'liquidated') DEFAULT 'open',
    close_reason ENUM('manual', 'stop_loss', 'take_profit', 'margin_call', 'liquidation') DEFAULT NULL,
    opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (pair_id) REFERENCES forex_pairs(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_pair_status (pair_id, status)
);

-- İşlem geçmişi (kapatılan pozisyonlar)
CREATE TABLE trade_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trade_id INT NOT NULL, -- leverage_trades tablosundan
    user_id INT NOT NULL,
    pair_symbol VARCHAR(10) NOT NULL,
    trade_type ENUM('long', 'short') NOT NULL,
    lot_size DECIMAL(10,2) NOT NULL,
    leverage_ratio INT NOT NULL,
    entry_price DECIMAL(10,5) NOT NULL,
    exit_price DECIMAL(10,5) NOT NULL,
    duration_minutes INT DEFAULT 0, -- Pozisyon açık kalma süresi
    pnl DECIMAL(16,2) NOT NULL, -- Toplam kar/zarar
    commission DECIMAL(10,2) DEFAULT 0,
    swap_fee DECIMAL(10,2) DEFAULT 0,
    close_reason ENUM('manual', 'stop_loss', 'take_profit', 'margin_call', 'liquidation'),
    opened_at DATETIME NOT NULL,
    closed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, closed_at),
    INDEX idx_pair_date (pair_symbol, closed_at)
);

-- Margin çağrıları ve liquidation
CREATE TABLE margin_calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trade_id INT NOT NULL,
    margin_level DECIMAL(8,2) NOT NULL, -- Margin seviyesi %
    required_margin DECIMAL(16,2) NOT NULL,
    available_margin DECIMAL(16,2) NOT NULL,
    call_type ENUM('warning', 'margin_call', 'liquidation') NOT NULL,
    message TEXT,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trade_id) REFERENCES leverage_trades(id) ON DELETE CASCADE
);

-- Forex fiyat geçmişi (grafik verileri için)
CREATE TABLE forex_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pair_id INT NOT NULL,
    timeframe ENUM('1m', '5m', '15m', '30m', '1h', '4h', '1d') NOT NULL,
    open_price DECIMAL(10,5) NOT NULL,
    high_price DECIMAL(10,5) NOT NULL,
    low_price DECIMAL(10,5) NOT NULL,
    close_price DECIMAL(10,5) NOT NULL,
    volume BIGINT DEFAULT 0,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (pair_id) REFERENCES forex_pairs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_candle (pair_id, timeframe, timestamp),
    INDEX idx_pair_timeframe_time (pair_id, timeframe, timestamp)
);

-- Trading ayarları (kullanıcı bazlı)
CREATE TABLE user_trading_settings (
    user_id INT PRIMARY KEY,
    default_leverage INT DEFAULT 100,
    default_lot_size DECIMAL(10,2) DEFAULT 0.01,
    auto_close_profit DECIMAL(8,2) DEFAULT NULL, -- %50 kar otomatik kapat
    auto_close_loss DECIMAL(8,2) DEFAULT NULL, -- %20 zarar otomatik kapat
    max_open_positions INT DEFAULT 10,
    risk_per_trade DECIMAL(8,2) DEFAULT 2.0, -- Trade başına risk %
    notifications_enabled BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- BAŞLANGIÇ VERİLERİ

-- Major Forex Pairs
INSERT INTO forex_pairs (symbol, base_currency, quote_currency, display_name, category, current_price, pip_value, max_leverage) VALUES
('EURUSD', 'EUR', 'USD', 'EUR/USD', 'major', 1.08500, 0.0001, 500),
('GBPUSD', 'GBP', 'USD', 'GBP/USD', 'major', 1.26800, 0.0001, 500),
('USDJPY', 'USD', 'JPY', 'USD/JPY', 'major', 148.250, 0.01, 500),
('USDCHF', 'USD', 'CHF', 'USD/CHF', 'major', 0.90200, 0.0001, 500),
('AUDUSD', 'AUD', 'USD', 'AUD/USD', 'major', 0.65800, 0.0001, 400),
('USDCAD', 'USD', 'CAD', 'USD/CAD', 'major', 1.42100, 0.0001, 400),
('NZDUSD', 'NZD', 'USD', 'NZD/USD', 'major', 0.58200, 0.0001, 400);

-- Minor Pairs
INSERT INTO forex_pairs (symbol, base_currency, quote_currency, display_name, category, current_price, pip_value, max_leverage) VALUES
('EURGBP', 'EUR', 'GBP', 'EUR/GBP', 'minor', 0.85600, 0.0001, 200),
('EURJPY', 'EUR', 'JPY', 'EUR/JPY', 'minor', 160.800, 0.01, 200),
('GBPJPY', 'GBP', 'JPY', 'GBP/JPY', 'minor', 187.900, 0.01, 200);

-- Indices
INSERT INTO forex_pairs (symbol, base_currency, quote_currency, display_name, category, current_price, pip_value, max_leverage) VALUES
('US30', 'USD', 'USD', 'Dow Jones', 'indices', 42580.50, 1.0, 100),
('SPX500', 'USD', 'USD', 'S&P 500', 'indices', 5820.75, 1.0, 100),
('NAS100', 'USD', 'USD', 'NASDAQ', 'indices', 19850.25, 1.0, 100),
('GER40', 'EUR', 'EUR', 'DAX', 'indices', 19420.80, 1.0, 100);

-- Commodities
INSERT INTO forex_pairs (symbol, base_currency, quote_currency, display_name, category, current_price, pip_value, max_leverage) VALUES
('XAUUSD', 'XAU', 'USD', 'Gold', 'commodities', 2685.50, 0.01, 200),
('XAGUSD', 'XAG', 'USD', 'Silver', 'commodities', 30.85, 0.001, 200),
('USOIL', 'USD', 'USD', 'Oil (WTI)', 'commodities', 70.25, 0.01, 100);

-- Varsayılan ayarlar
INSERT INTO ayarlar (`key`, `value`) VALUES 
('forex_margin_level_warning', '150'), -- %150 altında uyarı
('forex_margin_level_call', '100'), -- %100 altında margin call
('forex_margin_level_liquidation', '50'), -- %50 altında liquidation
('forex_commission_rate', '0.0003'), -- %0.03 komisyon
('forex_swap_rate_long', '-0.05'), -- Long pozisyon overnight fee
('forex_swap_rate_short', '0.02'); -- Short pozisyon overnight fee

SELECT 'Forex/Borsa sistemi tabloları oluşturuldu!' as Status; 