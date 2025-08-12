<!DOCTYPE html>
<html>
<head>
    <title>Test Sayfası</title>
</head>
<body>
    <h1>Test Sayfası Çalışıyor!</h1>
    <p>PHP Version: <?php echo phpversion(); ?></p>
    <p>Server: <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
    <p>Time: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <h2>Test Linkleri:</h2>
    <ul>
        <li><a href="backend/test_connection.php">Bağlantı Testi</a></li>
        <li><a href="backend/test_positions.php">Test Pozisyonları</a></li>
        <li><a href="user-panel.html">Ana Sayfa</a></li>
    </ul>
</body>
</html> 