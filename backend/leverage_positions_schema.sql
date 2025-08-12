-- Kaldıraçlı işlemler için pozisyon tablosu
CREATE TABLE IF NOT EXISTS leverage_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coin_symbol VARCHAR(10) NOT NULL,
    position_type ENUM('long', 'short') NOT NULL,
    leverage_ratio DECIMAL(3,1) NOT NULL, -- 1.0, 2.0, 5.0, 10.0
    entry_price DECIMAL(20,8) NOT NULL,
    position_size DECIMAL(20,8) NOT NULL, -- Gerçek pozisyon büyüklüğü (kaldıraçlı)
    invested_amount DECIMAL(20,8) NOT NULL, -- Kullanıcının yatırdığı miktar
    current_price DECIMAL(20,8) DEFAULT NULL,
    unrealized_pnl DECIMAL(20,8) DEFAULT 0,
    status ENUM('open', 'closed', 'liquidated') DEFAULT 'open',
    stop_loss_price DECIMAL(20,8) DEFAULT NULL,
    take_profit_price DECIMAL(20,8) DEFAULT NULL,
    liquidation_price DECIMAL(20,8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    close_price DECIMAL(20,8) DEFAULT NULL,
    realized_pnl DECIMAL(20,8) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_coin_status (coin_symbol, status)
);

-- Pozisyon geçmişi tablosu
CREATE TABLE IF NOT EXISTS position_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('open', 'close', 'liquidate', 'update') NOT NULL,
    price DECIMAL(20,8) NOT NULL,
    pnl DECIMAL(20,8) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES leverage_positions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Risk yönetimi ayarları tablosu
CREATE TABLE IF NOT EXISTS leverage_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    max_leverage DECIMAL(3,1) DEFAULT 10.0,
    default_leverage DECIMAL(3,1) DEFAULT 1.0,
    auto_close_enabled BOOLEAN DEFAULT TRUE,
    max_loss_percentage DECIMAL(5,2) DEFAULT 80.00, -- %80 zarar durumunda otomatik kapat
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);