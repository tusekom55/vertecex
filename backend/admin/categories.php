<?php
require_once __DIR__ . '/../auth.php';
if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}
$conn = db_connect();
$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $sql = 'SELECT * FROM coin_kategorileri';
    $result = $conn->query($sql);
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    echo json_encode(['success' => true, 'categories' => $categories]);
}
elseif ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kategori_adi = $_POST['kategori_adi'] ?? '';
    $stmt = $conn->prepare('INSERT INTO coin_kategorileri (kategori_adi) VALUES (?)');
    $stmt->bind_param('s', $kategori_adi);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}
elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $stmt = $conn->prepare('DELETE FROM coin_kategorileri WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}
elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $kategori_adi = $_POST['kategori_adi'] ?? '';
    $stmt = $conn->prepare('UPDATE coin_kategorileri SET kategori_adi = ? WHERE id = ?');
    $stmt->bind_param('si', $kategori_adi, $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}
else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
}
$conn->close(); 