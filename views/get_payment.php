<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID tidak ditemukan']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("SELECT b.*, m.nama_muzakki, m.alamat 
                      FROM bayarzakat b 
                      LEFT JOIN muzakki m ON b.nama_KK = m.nama_muzakki 
                      WHERE b.id_zakat = ?");
$stmt->execute([$_GET['id']]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    http_response_code(404);
    echo json_encode(['error' => 'Data pembayaran tidak ditemukan']);
    exit;
}

echo json_encode($payment); 