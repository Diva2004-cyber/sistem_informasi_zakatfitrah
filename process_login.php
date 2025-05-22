<?php
require_once 'config/auth.php';

$auth = new Auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($auth->login($username, $password)) {
        // Get user role for specific message
        $userRole = $auth->getUserRole();
        
        // Set session for successful login message
        $_SESSION['login_success'] = true;
        
        // Create specific message based on user role
        $roleMessages = [
            'admin' => 'Login berhasil! Selamat datang kembali, Admin.',
            'petugas' => 'Login berhasil! Selamat datang kembali, Petugas.',
            'staff' => 'Login berhasil! Selamat datang kembali, Staff.',
            'muzakki' => 'Login berhasil! Selamat datang kembali, Muzakki.',
            'viewer' => 'Login berhasil! Selamat datang kembali.'
        ];
        
        $message = isset($roleMessages[$userRole]) ? $roleMessages[$userRole] : 'Login berhasil! Selamat datang kembali.';
        
        header('Location: index.php?login=success&message=' . urlencode($message));
        exit;
    } else {
        header('Location: views/login.php?error=Username atau password salah');
        exit;
    }
} else {
    header('Location: views/login.php');
    exit;
}
// End of file