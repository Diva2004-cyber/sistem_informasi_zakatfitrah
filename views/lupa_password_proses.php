<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();

$role = $_POST['role'] ?? '';
$username = trim($_POST['username'] ?? '');
$kunci_reset = trim($_POST['kunci_reset'] ?? '');

if ($username === '') {
    header('Location: lupa_password.php?error=Lengkapi username Anda!');
    exit;
}

// Enforce only muzakki role for password reset
if ($role !== 'muzakki') {
    header('Location: lupa_password.php?error=Hanya Muzakki (pengunjung) yang diperbolehkan reset password!');
    exit;
}

// Cek user
$stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND status = "aktif"');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: lupa_password.php?error=Username tidak ditemukan atau akun nonaktif!');
    exit;
}

// Hanya proses muzakki
session_start();
$_SESSION['reset_user_id'] = $user['id'];
$_SESSION['reset_role'] = $role;
header('Location: reset_password.php');
exit;