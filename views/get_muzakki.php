<?php
require_once '../config/database.php';

// Untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Tentukan direktori log yang konsisten
$log_dir = dirname(__FILE__) . '/../logs/';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$log_file = $log_dir . 'muzakki_api_debug.log';

// Log seluruh request dan semua parameter untuk debugging
$timestamp = date('Y-m-d H:i:s');
file_put_contents($log_file, "$timestamp - GET params: " . json_encode($_GET) . "\n", FILE_APPEND);
file_put_contents($log_file, "$timestamp - POST params: " . json_encode($_POST) . "\n", FILE_APPEND);
file_put_contents($log_file, "$timestamp - REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

// Handle both GET and POST requests for flexibility
$id = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = trim($_GET['id']);
} elseif (isset($_POST['id']) && !empty($_POST['id'])) {
    $id = trim($_POST['id']);
}

// Log ID yang diperoleh
file_put_contents($log_file, "$timestamp - ID yang diperoleh: " . ($id !== null ? $id : 'null') . "\n", FILE_APPEND);

// Validasi ID
if ($id === null) {
    file_put_contents($log_file, "$timestamp - ERROR: ID tidak ada di request\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'ID muzakki tidak ada dalam request']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Pastikan ID adalah angka valid dan positif
    $id_muzakki = filter_var($id, FILTER_VALIDATE_INT);
    
    // Log hasil validasi ID
    file_put_contents($log_file, "$timestamp - Hasil validasi ID: " . ($id_muzakki === false ? 'invalid' : $id_muzakki) . "\n", FILE_APPEND);
    
    if ($id_muzakki === false || $id_muzakki <= 0) {
        throw new Exception("ID harus berupa angka positif: {$id}");
    }
    
    // Log ID yang dicari
    file_put_contents($log_file, "$timestamp - Mencari muzakki dengan ID: $id_muzakki\n", FILE_APPEND);
    
    // Periksa koneksi database
    if (!$db) {
        throw new Exception("Koneksi database gagal");
    }
    
    // Gunakan query yang eksplisit menyebutkan semua kolom
    $query = "SELECT id_muzakki, nama_muzakki, jumlah_tanggungan, alamat, keterangan FROM muzakki WHERE id_muzakki = ?";
    file_put_contents($log_file, "$timestamp - Query: $query\n", FILE_APPEND);
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id_muzakki, PDO::PARAM_INT);
    $stmt->execute();
    
    // Log hasil execute
    $rowCount = $stmt->rowCount();
    file_put_contents($log_file, "$timestamp - Jumlah baris hasil query: $rowCount\n", FILE_APPEND);
    
    $muzakki = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log hasil query
    if ($muzakki) {
        file_put_contents($log_file, "$timestamp - Data ditemukan untuk ID $id_muzakki\n", FILE_APPEND);
        file_put_contents($log_file, "$timestamp - Data: " . json_encode($muzakki, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        // Pastikan semua field ada dengan nilai default jika null
        $result = [
            'id_muzakki' => $muzakki['id_muzakki'],
            'nama_muzakki' => $muzakki['nama_muzakki'] ?? '',
            'jumlah_tanggungan' => $muzakki['jumlah_tanggungan'] ?? 1,
            'alamat' => $muzakki['alamat'] ?? '',
            'keterangan' => $muzakki['keterangan'] ?? ''
        ];
        
        // Log data yang akan dikirim ke client
        file_put_contents($log_file, "$timestamp - Mengirim data: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        echo json_encode($result);
    } else {
        file_put_contents($log_file, "$timestamp - Data TIDAK ditemukan untuk ID $id_muzakki\n", FILE_APPEND);
        http_response_code(404);
        echo json_encode(['error' => 'Data muzakki tidak ditemukan']);
    }
} catch (Exception $e) {
    // Log error
    file_put_contents($log_file, "$timestamp - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}