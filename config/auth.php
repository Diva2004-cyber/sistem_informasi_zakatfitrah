<?php
// Start session before including any files to prevent "headers already sent" errors
session_start();
require_once __DIR__ . '/database.php';

class Auth {
    private $db;
    
    // Definisi hak akses untuk setiap role
    private $role_permissions = [
        'admin' => [
            'view_dashboard' => true,
            'manage_muzakki' => true,
            'manage_mustahik' => true,
            'manage_zakat' => true,
            'manage_distribusi' => true,
            'manage_laporan' => true,
            'manage_tasks' => true,
            'manage_users' => true,
            'manage_activity_logs' => true,
            'manage_settings' => true,
            'reset_distribusi' => true,
            'delete_data' => true,
            'manage_dokumentasi' => true,
            'delete_dokumentasi' => true,
            'update_dokumentasi_status' => true,
            'manage_kategori_mustahik' => true
        ],
        'petugas' => [
            'view_dashboard' => true,
            'manage_muzakki' => true,
            'manage_mustahik' => true,
            'manage_zakat' => true,
            'manage_distribusi' => true,
            'manage_laporan' => true,
            'manage_tasks' => true,
            'manage_users' => false,
            'manage_activity_logs' => false,
            'manage_settings' => false,
            'reset_distribusi' => false,
            'delete_data' => false,
            'manage_dokumentasi' => true,
            'delete_dokumentasi' => false,
            'update_dokumentasi_status' => true,
            'manage_kategori_mustahik' => false
        ],
        'staff' => [
            'view_dashboard' => true,
            'manage_muzakki' => false,
            'manage_mustahik' => false,
            'manage_zakat' => false,
            'manage_distribusi' => false,
            'manage_laporan' => false,
            'manage_tasks' => false,
            'manage_users' => false,
            'manage_activity_logs' => false,
            'manage_settings' => false,
            'reset_distribusi' => false,
            'delete_data' => false,
            'manage_dokumentasi' => true, // Staff hanya bisa mengelola dokumentasi
            'delete_dokumentasi' => false,
            'update_dokumentasi_status' => false,
            'manage_kategori_mustahik' => false
        ],
        'viewer' => [
            'view_dashboard' => true,
            'manage_muzakki' => false,
            'manage_mustahik' => false,
            'manage_zakat' => false,
            'manage_distribusi' => false,
            'manage_laporan' => true,
            'manage_tasks' => false,
            'manage_users' => false,
            'manage_activity_logs' => false,
            'manage_settings' => false,
            'reset_distribusi' => false,
            'delete_data' => false,
            'manage_dokumentasi' => false,
            'delete_dokumentasi' => false,
            'update_dokumentasi_status' => false,
            'manage_kategori_mustahik' => false
        ]
    ];
    
    private $users = [
        'admin' => [
            'id' => 1,
            'username' => 'Pemwebzakat',
            'password' => 'projectzakat2025',
            'role' => 'admin'
        ],
        'petugas' => [
            'id' => 2,
            'username' => 'petugas',
            'password' => 'TugasbesarPemweb',
            'role' => 'petugas'
        ],
        'staff' => [
            'id' => 4,
            'username' => 'staff',
            'password' => 'staff123',
            'role' => 'staff'
        ],
        'viewer' => [
            'id' => 3,
            'username' => 'viewer',
            'password' => '',
            'role' => 'viewer'
        ]
    ];

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function login($username, $password) {
        // Cek dulu users hardcoded
        foreach ($this->users as $user) {
            if ($user['username'] === $username && $user['password'] === $password) {
                $_SESSION['user'] = $user;
                $_SESSION['user_id'] = $user['id'];
                
                // Tambahkan permissions ke session
                $role = $user['role'];
                if (isset($this->role_permissions[$role])) {
                    $_SESSION['permissions'] = $this->role_permissions[$role];
                }
                
                return true;
            }
        }

        // Jika tidak ditemukan di hardcoded users, cek di database
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];
            $_SESSION['user_id'] = $user['id'];
            
            // Tambahkan permissions ke session
            $role = $user['role'];
            if (isset($this->role_permissions[$role])) {
                $_SESSION['permissions'] = $this->role_permissions[$role];
            } else {
                // Default permissions untuk role yang tidak terdefinisi
                $_SESSION['permissions'] = $this->role_permissions['viewer'];
            }
            
            return true;
        }

        return false;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user']);
    }

    public function getUserRole() {
        return $_SESSION['user']['role'] ?? null;
    }

    public function getUsername() {
        return $_SESSION['user']['username'] ?? 'Guest';
    }

    public function getUserId() {
        // Jika session user_id tidak ada, coba ambil dari SESSION user untuk kompatibilitas
        if (!isset($_SESSION['user_id']) && isset($_SESSION['user']['id'])) {
            $_SESSION['user_id'] = $_SESSION['user']['id'];
        }
        return $_SESSION['user_id'] ?? null;
    }

    public function logout() {
        session_destroy();
    }

    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['permissions'][$permission] ?? false;
    }

    public function checkAccess($requiredRole) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $userRole = $this->getUserRole();
        
        // Admin memiliki akses ke semua
        if ($userRole === 'admin') {
            return true;
        }
        
        // Petugas memiliki akses terbatas
        if ($userRole === 'petugas' && in_array($requiredRole, ['petugas', 'staff', 'viewer'])) {
            return true;
        }
        
        // Staff memiliki akses terbatas
        if ($userRole === 'staff' && in_array($requiredRole, ['staff', 'viewer'])) {
            return true;
        }
        
        // Viewer/muzakki hanya memiliki akses view
        if (($userRole === 'viewer' || $userRole === 'muzakki') && $requiredRole === 'viewer') {
            return true;
        }
        
        return false;
    }
    
    public function getAllPermissions() {
        if (!$this->isLoggedIn()) {
            return [];
        }
        
        return $_SESSION['permissions'] ?? [];
    }
}
?> 