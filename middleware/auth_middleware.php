<?php
require_once __DIR__ . '/../config/auth.php';

/**
 * Authentication Middleware
 * 
 * Controls access to pages based on user roles
 */

function redirectTo($url) {
    header("Location: $url");
    exit;
}

class AuthMiddleware {
    private $auth;
    
    public function __construct() {
        $this->auth = new Auth();
    }
    
    public function requireAuth($allowedRoles = ['admin', 'petugas']) {
        // Cek jika user belum login, redirect ke halaman login
        if (!$this->auth->isLoggedIn()) {
            $_SESSION['error'] = 'Anda harus login terlebih dahulu';
            redirectTo('/zakatfitrah/views/login.php');
        }
        
        // Cek jika role user tidak sesuai dengan halaman yang diakses
        $userRole = strtolower($this->auth->getUserRole());
        
        // Jika role adalah muzakki dan mencoba mengakses halaman yang dibatasi
        if ($userRole === 'muzakki' && !in_array('muzakki', $allowedRoles)) {
            $_SESSION['access_denied_message'] = 'Akses ditolak! Hanya admin yang memiliki akses. Halaman ini hanya dapat diakses oleh admin. Status Anda saat ini adalah Muzakki (Pengunjung), sehingga Anda tidak memiliki hak untuk mengakses halaman ini.';
            redirectTo('/zakatfitrah/index.php');
        }
        
        // Untuk role lain
        if (!in_array($userRole, $allowedRoles)) {
            $_SESSION['access_denied_message'] = 'Akses ditolak! Anda tidak memiliki hak akses ke halaman ini.';
            redirectTo('/zakatfitrah/views/login.php');
        }
        
        return true;
    }
    
    public function requireAdmin() {
        return $this->requireAuth(['admin']);
    }
    
    public function getCurrentUser() {
        return [
            'username' => $this->auth->getUsername(),
            'role' => $this->auth->getUserRole()
        ];
    }
    
    public function getUserId() {
        return $this->auth->getUserId();
    }
    
    public function isAdmin() {
        return $this->auth->getUserRole() === 'admin';
    }
    
    public function hasPermission($permission) {
        return $this->auth->hasPermission($permission);
    }
    
    public function getAllPermissions() {
        return $this->auth->getAllPermissions();
    }
} 