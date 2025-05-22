<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if database connection is successful
if ($db === null) {
    header('Location: register.php?error=Koneksi database gagal. Silakan hubungi administrator.');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$role = $_POST['role'] ?? 'muzakki';
$token = trim($_POST['token'] ?? '');

// Validasi input dasar
if ($username === '' || $password === '' || $nama_lengkap === '') {
    header('Location: register.php?error=Lengkapi data username, password dan nama lengkap!');
    exit;
}

// Khusus untuk admin, wajib menggunakan token
if ($role === 'admin') {
    if (empty($token)) {
        header('Location: register.php?error=Token diperlukan untuk mendaftar sebagai admin!');
        exit;
    }
    
    // Token master untuk admin (dalam kasus nyata, token ini bisa disimpan di database atau env file)
    $master_token = 'admin123token'; // Token master untuk admin
    
    // Validasi Token admin
    if ($token !== $master_token) {
        header('Location: register.php?error=Token admin tidak valid!');
        exit;
    }
}

// Cek username unik
$stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetchColumn() > 0) {
    header('Location: register.php?error=Username sudah terdaftar!');
    exit;
}

// Tidak diperlukan kunci petugas, gunakan token saja
$key_id = null;

// Log informasi pendaftaran (bisa disimpan jika ada tabel untuk logging)
try {
    $log_message = "Pendaftaran akun baru: {$username} dengan role {$role}";
    error_log($log_message);
} catch (Exception $e) {
    // Lanjutkan meskipun logging gagal
    error_log("Gagal mencatat aktivitas: " . $e->getMessage());
}

// Hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Simpan user
$stmt = $db->prepare('INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)');
$stmt->execute([$username, $hashed, $nama_lengkap, $role]);
$user_id = $db->lastInsertId();

// Buat pesan sukses yang sesuai berdasarkan role
$roleLabels = [
    'muzakki' => 'Muzakki (Pengunjung)',
    'admin' => 'Admin'
];

$roleLabel = $roleLabels[$role] ?? $role;

// Redirect ke halaman login dengan pesan sukses
header("Location: login.php?register=success&message=Pendaftaran akun {$roleLabel} berhasil! Silakan login.");
exit;