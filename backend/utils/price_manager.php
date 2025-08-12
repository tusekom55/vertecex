<?php
/**
 * Fiyat Yönetim Sistemi
 * - API coinleri için gerçek zamanlı fiyatlar
 * - Manuel coinler için sahte dalgalanma
 */

require_once __DIR__ . '/../config.php';

class PriceManager {
    private $conn;
    private $api_coins = ['BTC', 'ETH', 'BNB', 'XRP', 'USDT', 'ADA', 'SOL', 'DOGE', 'MATIC', 'DOT'];
    private $manual_coins = ['T', 'SEX', 'TTT']; // Tugaycoin, SEX, dolar
    
    public function __construct() {
        $this->conn = db_connect();
        $this->loadManualCoins(); // Manuel coinleri veritabanından yükle
    }
    
    /**
     * Manuel coinleri veritabanından dinamik olarak yükle
     */
    private function loadManualCoins() {
        try {
            $sql = "SELECT coin_kodu FROM coins WHERE coin_type = 'manual' AND is_active = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $manual_coins_db = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Mevcut manuel coinler ile birleştir
            $this->manual_coins = array_unique(array_merge($this->manual_coins, $manual_coins_db));
            
        } catch (Exception $e) {
            error_log("Manuel coin yükleme hatası: " . $e->getMessage());
        }
    }
    
    /**
     * Yeni manuel coin ekle
     */
    public function addManualCoin($coin_code) {
        if (!in_array($coin_code, $this->manual_coins)) {
            $this->manual_coins[] = $coin_code;
            error_log("Yeni manuel coin eklendi: " . $coin_code);
        }
    }
    
    /**
     * Tüm coin fiyatlarını güncelle
     */
    public function updateAllPrices() {
        $this->updateApiPrices();
        $this->updateManualPrices();
        $this->logPriceUpdate();
    }
    
    /**
     * API'den gerçek fiyatları çek ve güncelle
     */
    private function updateApiPrices() {
        try {
            // CoinGecko API'den fiyatları çek
            $api_url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,binancecoin,ripple,tether,cardano,solana,dogecoin,matic-network,polkadot&vs_currencies=try';
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; CryptoTrader/1.0)'
                ]
            ]);
            
            $response = file_get_contents($api_url, false, $context);
            
            if ($response === false) {
                error_log("API request failed");
                return false;
            }
            
            $data = json_decode($response, true);
            
            if (!$data) {
                error_log("API response parse failed");
                return false;
            }
            
            // Coin mapping
            $coin_mapping = [
                'bitcoin' => 'BTC',
                'ethereum' => 'ETH', 
                'binancecoin' => 'BNB',
                'ripple' => 'XRP',
                'tether' => 'USDT',
                'cardano' => 'ADA',
                'solana' => 'SOL',
                'dogecoin' => 'DOGE',
                'matic-network' => 'MATIC',
                'polkadot' => 'DOT'
            ];
            
            foreach ($coin_mapping as $api_id => $coin_code) {
                if (isset($data[$api_id]['try'])) {
                    $price = floatval($data[$api_id]['try']);
                    $this->updateCoinPrice($coin_code, $price, 'api');
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("API price update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Manuel coinler için sahte dalgalanma oluştur
     */
    private function updateManualPrices() {
        foreach ($this->manual_coins as $coin_code) {
            $current_price = $this->getCurrentPrice($coin_code);
            
            if ($current_price > 0) {
                // %5 ile %30 arasında rastgele dalgalanma
                $min_change = -0.05; // -%5
                $max_change = 0.30;  // +%30
                
                $change_percent = $min_change + mt_rand() / mt_getrandmax() * ($max_change - $min_change);
                $new_price = $current_price * (1 + $change_percent);
                
                // Minimum fiyat kontrolü
                if ($new_price < 0.01) {
                    $new_price = 0.01;
                }
                
                $this->updateCoinPrice($coin_code, $new_price, 'manual', $change_percent * 100);
            }
        }
    }
    
    /**
     * Coin fiyatını güncelle
     */
    private function updateCoinPrice($coin_code, $new_price, $source = 'manual', $change_percent = 0) {
        try {
            $sql = "UPDATE coins SET 
                        current_price = ?, 
                        price_change_24h = ?,
                        last_update = NOW(),
                        price_source = ?
                    WHERE coin_kodu = ? AND is_active = 1";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$new_price, $change_percent, $source, $coin_code]);
            
            if ($result) {
                error_log("Price updated: {$coin_code} = ₺{$new_price} ({$change_percent}%) [{$source}]");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Price update error for {$coin_code}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mevcut fiyatı al
     */
    private function getCurrentPrice($coin_code) {
        try {
            $sql = "SELECT current_price FROM coins WHERE coin_kodu = ? AND is_active = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$coin_code]);
            
            return floatval($stmt->fetchColumn());
            
        } catch (Exception $e) {
            error_log("Get current price error for {$coin_code}: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Admin paneli için manuel fiyat artışı
     */
    public function increasePriceByPercent($coin_code, $increase_percent) {
        try {
            $current_price = $this->getCurrentPrice($coin_code);
            
            if ($current_price <= 0) {
                return ['success' => false, 'message' => 'Coin bulunamadı'];
            }
            
            $multiplier = 1 + ($increase_percent / 100);
            $new_price = $current_price * $multiplier;
            
            // price_change_24h'ı da artırma yüzdesi olarak güncelle
            $result = $this->updateCoinPrice($coin_code, $new_price, 'admin', $increase_percent);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => "{$coin_code} fiyatı %{$increase_percent} artırıldı (24s değişim: +%{$increase_percent})",
                    'old_price' => $current_price,
                    'new_price' => $new_price,
                    'price_change_24h' => $increase_percent
                ];
            } else {
                return ['success' => false, 'message' => 'Fiyat güncellenemedi'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Hata: ' . $e->getMessage()];
        }
    }
    
    /**
     * Fiyat güncelleme logunu kaydet
     */
    private function logPriceUpdate() {
        try {
            $sql = "INSERT INTO price_update_logs (update_time, api_success, manual_success) VALUES (NOW(), 1, 1)";
            $this->conn->prepare($sql)->execute();
        } catch (Exception $e) {
            error_log("Price update log error: " . $e->getMessage());
        }
    }
    
    /**
     * Coin tipini kontrol et (API mi Manuel mi)
     */
    public function isApiCoin($coin_code) {
        return in_array($coin_code, $this->api_coins);
    }
    
    public function isManualCoin($coin_code) {
        return in_array($coin_code, $this->manual_coins);
    }
}

// Cron job için direkt çalıştırma
if (php_sapi_name() === 'cli' || isset($_GET['update_prices'])) {
    $priceManager = new PriceManager();
    $priceManager->updateAllPrices();
    echo "Fiyatlar güncellendi: " . date('Y-m-d H:i:s') . "\n";
}
?>
