<?php
require_once __DIR__ . '/../auth.php';
logout_user();
echo json_encode(['success' => true, 'message' => 'Çıkış yapıldı']); 