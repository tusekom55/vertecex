-- Kaldıraçlı işlem tabloları oluşturma SQL'i

-- 1. Leverage Positions tablosu
CREATE TABLE IF NOT EXISTS leverage_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coin_symbol VARCHAR(10) NOT NULL,
    position_type ENUM('long', 'short') NOT NULL,
    leverage_ratio DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    entry_price DECIMAL(20,8) NOT NULL,
    close_price DECIMAL(20,8) NULL,
    position_size DECIMAL(20,8) NOT NULL,
    invested_amount DECIMAL(15,2) NOT NULL,
    current_price DECIMAL(20,8) NOT NULL,
    liquidation_price DECIMAL(20,8) NOT NULL,
    unrealized_pnl DECIMAL(15,2) DEFAULT 0.00,
    realized_pnl DECIMAL(15,2) NULL,
    status ENUM('open', 'closed', 'liquidated') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_coin_symbol (coin_symbol),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Position History tablosu
CREATE TABLE IF NOT EXISTS position_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('open', 'close', 'liquidate', 'partial_close') NOT NULL,
    price DECIMAL(20,8) NOT NULL,
    pnl DECIMAL(15,2) NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_position_id (position_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (position_id) REFERENCES leverage_positions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Leverage Settings tablosu
CREATE TABLE IF NOT EXISTS leverage_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    max_leverage DECIMAL(5,2) DEFAULT 10.00,
    default_leverage DECIMAL(5,2) DEFAULT 1.00,
    auto_close_enabled BOOLEAN DEFAULT TRUE,
    max_loss_percentage DECIMAL(5,2) DEFAULT 80.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Coins tablosuna eksik sütunları ekle
ALTER TABLE coins ADD COLUMN IF NOT EXISTS current_price DECIMAL(20,8) DEFAULT 0.00;
ALTER TABLE coins ADD COLUMN IF NOT EXISTS price_change_24h DECIMAL(10,4) DEFAULT 0.00;

-- 5. Test coin verileri
INSERT INTO coins (coin_kodu, coin_adi, current_price, price_change_24h) 
VALUES 
    ('BTC', 'Bitcoin', 96480.50, 2.35),
    ('ETH', 'Ethereum', 3420.75, -1.22),
    ('BNB', 'BNB', 685.20, 0.88),
    ('SOL', 'Solana', 238.45, 4.65),
    ('XRP', 'XRP', 2.35, 12.88)
ON DUPLICATE KEY UPDATE 
    current_price = VALUES(current_price),
    price_change_24h = VALUES(price_change_24h);
