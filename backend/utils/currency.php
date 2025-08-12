<?php
/**
 * Döviz kuru yönetimi
 */

// USD/TRY kurunu çek (önbellek ile)
function getUsdTryRate() {
    $cache_file = __DIR__ . '/../cache/usd_try_rate.json';
    $cache_duration = 3600; // 1 saat
    
    // Cache klasörü yoksa oluştur
    $cache_dir = dirname($cache_file);
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // Cache'den oku
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        if ($cache_data && (time() - $cache_data['timestamp']) < $cache_duration) {
            return $cache_data['rate'];
        }
    }
    
    // API'den çek
    $rate = fetchUsdTryRateFromAPI();
    
    // Cache'e kaydet
    if ($rate > 0) {
        $cache_data = [
            'rate' => $rate,
            'timestamp' => time(),
            'source' => 'api'
        ];
        file_put_contents($cache_file, json_encode($cache_data));
        return $rate;
    }
    
    // Fallback: Sabit kur
    return 30.0;
}

// API'den USD/TRY kurunu çek
function fetchUsdTryRateFromAPI() {
    try {
        // Birden fazla kaynak dene
        $sources = [
            'https://api.exchangerate-api.com/v4/latest/USD',
            'https://api.fixer.io/latest?base=USD&symbols=TRY',
            'https://api.currencyapi.com/v3/latest?apikey=YOUR_API_KEY&currencies=TRY&base_currency=USD'
        ];
        
        foreach ($sources as $url) {
            $rate = tryFetchRate($url);
            if ($rate > 0) {
                error_log("USD/TRY kuru başarıyla alındı: {$rate} (Kaynak: {$url})");
                return $rate;
            }
        }
        
        error_log("Tüm kur API'leri başarısız, sabit kur kullanılıyor");
        return 0; // Başarısız
        
    } catch (Exception $e) {
        error_log('Kur çekme hatası: ' . $e->getMessage());
        return 0;
    }
}

// Tek bir kaynaktan kur çekmeyi dene
function tryFetchRate($url) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TradePro/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === FALSE || $http_code !== 200) {
            return 0;
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            return 0;
        }
        
        // Farklı API formatlarını destekle
        if (isset($data['rates']['TRY'])) {
            return floatval($data['rates']['TRY']);
        } elseif (isset($data['data']['TRY']['value'])) {
            return floatval($data['data']['TRY']['value']);
        } elseif (isset($data['TRY'])) {
            return floatval($data['TRY']);
        }
        
        return 0;
        
    } catch (Exception $e) {
        error_log("Kur çekme hatası ({$url}): " . $e->getMessage());
        return 0;
    }
}

// Manuel kur güncelleme (admin paneli için)
function updateManualRate($rate) {
    if ($rate <= 0) {
        return false;
    }
    
    $cache_file = __DIR__ . '/../cache/usd_try_rate.json';
    $cache_dir = dirname($cache_file);
    
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_data = [
        'rate' => floatval($rate),
        'timestamp' => time(),
        'source' => 'manual'
    ];
    
    return file_put_contents($cache_file, json_encode($cache_data)) !== false;
}

// Kur bilgilerini getir
function getCurrencyInfo() {
    $cache_file = __DIR__ . '/../cache/usd_try_rate.json';
    
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        if ($cache_data) {
            return [
                'rate' => $cache_data['rate'],
                'last_updated' => date('Y-m-d H:i:s', $cache_data['timestamp']),
                'source' => $cache_data['source'] ?? 'unknown',
                'age_minutes' => round((time() - $cache_data['timestamp']) / 60)
            ];
        }
    }
    
    return [
        'rate' => 30.0,
        'last_updated' => 'Hiç güncellenmedi',
        'source' => 'fallback',
        'age_minutes' => 0
    ];
}
?>
