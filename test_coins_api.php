<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coins API Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        .coin-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin: 10px;
            display: inline-block;
            min-width: 200px;
        }
        .coin-price {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .coin-change {
            font-weight: bold;
        }
        .positive { color: #28a745; }
        .negative { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Coins API Test</h1>
        
        <div>
            <button class="btn" onclick="testCoinsAPI()">Coins API Test Et</button>
            <button class="btn" onclick="testDirectDB()">Veritabanı Direkt Test</button>
            <button class="btn" onclick="clearResults()">Temizle</button>
        </div>
        
        <div id="results"></div>
        
        <div id="coins-display"></div>
    </div>

    <script>
        async function testCoinsAPI() {
            const resultsDiv = document.getElementById('results');
            const coinsDiv = document.getElementById('coins-display');
            
            resultsDiv.innerHTML = '<div class="result">🔄 API test ediliyor...</div>';
            
            try {
                const response = await fetch('backend/user/coins.php');
                const data = await response.json();
                
                resultsDiv.innerHTML = `
                    <div class="result">
                        <h3>📊 API Response:</h3>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
                
                if (data.success && data.coins) {
                    displayCoins(data.coins);
                } else {
                    coinsDiv.innerHTML = '<div class="result">❌ Coin verisi alınamadı: ' + (data.message || 'Bilinmeyen hata') + '</div>';
                }
                
            } catch (error) {
                resultsDiv.innerHTML = `
                    <div class="result">
                        <h3>❌ API Hatası:</h3>
                        <pre>${error.message}</pre>
                    </div>
                `;
            }
        }
        
        async function testDirectDB() {
            const resultsDiv = document.getElementById('results');
            
            resultsDiv.innerHTML = '<div class="result">🔄 Veritabanı test ediliyor...</div>';
            
            try {
                const response = await fetch('test_db_direct.php');
                const text = await response.text();
                
                resultsDiv.innerHTML = `
                    <div class="result">
                        <h3>🗄️ Veritabanı Direkt Test:</h3>
                        <pre>${text}</pre>
                    </div>
                `;
                
            } catch (error) {
                resultsDiv.innerHTML = `
                    <div class="result">
                        <h3>❌ Veritabanı Hatası:</h3>
                        <pre>${error.message}</pre>
                    </div>
                `;
            }
        }
        
        function displayCoins(coins) {
            const coinsDiv = document.getElementById('coins-display');
            
            let html = '<h3>💰 Bulunan Coinler (' + coins.length + ' adet):</h3>';
            
            coins.forEach(coin => {
                const changeClass = coin.price_change_24h >= 0 ? 'positive' : 'negative';
                const changeSign = coin.price_change_24h >= 0 ? '+' : '';
                
                html += `
                    <div class="coin-card">
                        <h4>${coin.coin_adi} (${coin.coin_kodu})</h4>
                        <div class="coin-price">₺${parseFloat(coin.current_price).toLocaleString('tr-TR', {minimumFractionDigits: 2})}</div>
                        <div class="coin-change ${changeClass}">${changeSign}${coin.price_change_24h}%</div>
                        <small>Tip: ${coin.coin_type || 'N/A'} | Kaynak: ${coin.price_source || 'N/A'}</small><br>
                        <small>Kategori: ${coin.kategori_adi}</small>
                    </div>
                `;
            });
            
            coinsDiv.innerHTML = html;
        }
        
        function clearResults() {
            document.getElementById('results').innerHTML = '';
            document.getElementById('coins-display').innerHTML = '';
        }
        
        // Sayfa yüklendiğinde otomatik test
        window.onload = function() {
            testCoinsAPI();
        };
    </script>
</body>
</html>
