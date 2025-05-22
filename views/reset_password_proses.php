<?php
session_start();
require_once '../config/database.php';
$db = (new Database())->getConnection();

// Check if database connection is successful
if ($db === null) {
    header('Location: lupa_password.php?error=Koneksi database gagal. Silakan hubungi administrator.');
    exit;
}

if (!isset($_SESSION['reset_user_id'])) {
    header('Location: lupa_password.php?error=Akses tidak valid!');
    exit;
}

$user_id = $_SESSION['reset_user_id'];
$role = $_SESSION['reset_role'] ?? '';
$key_id = $_SESSION['reset_key_id'] ?? null;

$password_baru = $_POST['password_baru'] ?? '';
$password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

if ($password_baru === '' || $password_konfirmasi === '') {
    header('Location: reset_password.php?error=Lengkapi semua data!');
    exit;
}
if ($password_baru !== $password_konfirmasi) {
    header('Location: reset_password.php?error=Konfirmasi password tidak cocok!');
    exit;
}
if (strlen($password_baru) < 6) {
    header('Location: reset_password.php?error=Password minimal 6 karakter!');
    exit;
}

$hashed = password_hash($password_baru, PASSWORD_DEFAULT);
$stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
$stmt->execute([$hashed, $user_id]);

// Jika petugas, update status kunci reset
if ($role === 'petugas' && $key_id) {
    $stmt = $db->prepare('UPDATE kunci_reset_petugas SET status = "sudah", used_by = ?, used_at = NOW() WHERE id = ?');
    $stmt->execute([$user_id, $key_id]);
}

// Hapus session reset
unset($_SESSION['reset_user_id'], $_SESSION['reset_role'], $_SESSION['reset_key_id']);

header('Location: login.php?reset=success&message=Password berhasil direset! Silakan login dengan password baru Anda.');
exit;