<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test - Düzeltmeler</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .loading { background-color: #fff3cd; border-color: #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button { padding: 10px 20px; margin: 5px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🔧 API Düzeltme Testleri</h1>
    
    <div class="test-section">
        <h2>1. Coins API Testi</h2>
        <button onclick="testCoinsAPI()">Coins API Test Et</button>
        <div id="coins-result"></div>
    </div>
    
    <div class="test-section">
        <h2>2. Portfolio API Testi</h2>
        <button onclick="testPortfolioAPI()">Portfolio API Test Et</button>
        <div id="portfolio-result"></div>
    </div>
    
    <div class="test-section">
        <h2>3. Trading API Testi (Health Check)</h2>
        <button onclick="testTradingHealthAPI()">Trading Health Check</button>
        <div id="trading-result"></div>
    </div>

    <script>
        async function testCoinsAPI() {
            const resultDiv = document.getElementById('coins-result');
            resultDiv.innerHTML = '<div class="loading">🔄 Coins API test ediliyor...</div>';
            
            try {
                const response = await fetch('backend/user/coins.php');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h3>✅ Coins API Başarılı!</h3>
                            <p><strong>Coin Sayısı:</strong> ${data.coins ? data.coins.length : 0}</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h3>❌ Coins API Hatası</h3>
                            <p><strong>Hata:</strong> ${data.message}</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h3>❌ Network Hatası</h3>
                        <p><strong>Hata:</strong> ${error.message}</p>
                    </div>
                `;
            }
        }
        
        async function testPortfolioAPI() {
            const resultDiv = document.getElementById('portfolio-result');
            resultDiv.innerHTML = '<div class="loading">🔄 Portfolio API test ediliyor...</div>';
            
            try {
                const response = await fetch('backend/user/trading.php?action=portfolio');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h3>✅ Portfolio API Başarılı!</h3>
                            <p><strong>Portfolio Coin Sayısı:</strong> ${data.data.portfolio ? data.data.portfolio.length : 0}</p>
                            <p><strong>Toplam Değer:</strong> ₺${data.data.summary ? data.data.summary.total_value.toFixed(2) : '0.00'}</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h3>❌ Portfolio API Hatası</h3>
                            <p><strong>Hata:</strong> ${data.message}</p>
                            <p><strong>Hata Tipi:</strong> ${data.error_type || 'Bilinmiyor'}</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h3>❌ Network Hatası</h3>
                        <p><strong>Hata:</strong> ${error.message}</p>
                    </div>
                `;
            }
        }
        
        async function testTradingHealthAPI() {
            const resultDiv = document.getElementById('trading-result');
            resultDiv.innerHTML = '<div class="loading">🔄 Trading Health Check test ediliyor...</div>';
            
            try {
                const response = await fetch('backend/user/trading.php?action=health_check');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">
                            <h3>✅ Trading API Sağlıklı!</h3>
                            <p><strong>Veritabanı:</strong> ${data.checks.database ? '✅' : '❌'}</p>
                            <p><strong>Tablolar:</strong> ${data.checks.tables ? '✅' : '❌'}</p>
                            <p><strong>User Session:</strong> ${data.checks.user_session ? '✅' : '❌'}</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h3>❌ Trading API Hatası</h3>
                            <p><strong>Hata:</strong> ${data.message}</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h3>❌ Network Hatası</h3>
                        <p><strong>Hata:</strong> ${error.message}</p>
                    </div>
                `;
            }
        }
        
        // Sayfa yüklendiğinde otomatik test
        window.onload = function() {
            console.log('🚀 API Test sayfası yüklendi');
        };
    </script>
</body>
</html>
