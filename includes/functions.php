<?php
/**
 * File ini berisi fungsi-fungsi helper yang digunakan di seluruh aplikasi
 */

/**
 * Format nilai uang ke format rupiah
 * 
 * @param float $amount Nilai yang akan diformat
 * @return string Nilai yang sudah diformat
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Mengecek apakah user memiliki role yang diizinkan
 * 
 * @param array $allowed_roles Array dari role yang diizinkan
 * @return boolean True jika user memiliki role yang diizinkan
 */
function hasRole($allowed_roles) {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return false;
    }
    
    $user_role = $_SESSION['user']['role'];
    return in_array($user_role, $allowed_roles);
}

/**
 * Mendapatkan status dalam format yang mudah dibaca
 * 
 * @param string $status Status dari database
 * @return string Status yang sudah diformat
 */
function formatStatus($status) {
    $status_map = [
        'belum_terdistribusi' => 'Belum Terdistribusi',
        'terdistribusi' => 'Sudah Terdistribusi',
        'belum_didokumentasi' => 'Belum Didokumentasi',
        'sudah_didokumentasi' => 'Sudah Didokumentasi',
        'belum_diterima' => 'Belum Diterima',
        'sudah_diterima' => 'Sudah Diterima',
        'bermasalah' => 'Bermasalah',
    ];
    
    return isset($status_map[$status]) ? $status_map[$status] : ucfirst(str_replace('_', ' ', $status));
}

/**
 * Membersihkan input dari karakter berbahaya
 * 
 * @param string $data Data yang akan dibersihkan
 * @return string Data yang sudah dibersihkan
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Mengirim pesan error atau sukses ke session
 * 
 * @param string $type Tipe pesan (error atau success)
 * @param string $message Pesan yang akan ditampilkan
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Generate random string untuk keperluan kode dll
 * 
 * @param int $length Panjang string yang diinginkan
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Convert tanggal MySQL ke format Indonesia
 * 
 * @param string $date Tanggal dalam format MySQL (YYYY-MM-DD)
 * @param bool $with_time Apakah perlu menampilkan waktu
 * @return string Tanggal dalam format Indonesia
 */
function formatTanggal($date, $with_time = false) {
    if (!$date) return '-';
    
    $bulan = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];
    
    // Format: 2023-01-15 14:30:00
    if ($with_time && strlen($date) > 10) {
        $date_parts = explode(' ', $date);
        $tgl = $date_parts[0];
        $waktu = $date_parts[1];
        
        $tgl_parts = explode('-', $tgl);
        $tahun = $tgl_parts[0];
        $bulan_num = $tgl_parts[1];
        $tanggal = $tgl_parts[2];
        
        return $tanggal . ' ' . $bulan[$bulan_num] . ' ' . $tahun . ' ' . substr($waktu, 0, 5);
    } else {
        $tgl_parts = explode('-', $date);
        $tahun = $tgl_parts[0];
        $bulan_num = $tgl_parts[1];
        $tanggal = $tgl_parts[2];
        
        return $tanggal . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
    }
}

/**
 * Handle upload file
 * 
 * @param array $file File dari $_FILES
 * @param string $upload_dir Direktori untuk menyimpan file
 * @param array $allowed_exts Ekstensi yang diperbolehkan
 * @param int $max_size Ukuran maksimal file (dalam bytes)
 * @return array Status upload dan file path
 */
function handleFileUpload($file, $upload_dir, $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
    $result = [
        'success' => false,
        'file_path' => '',
        'error' => ''
    ];
    
    // Cek apakah ada file yang diupload
    if (!isset($file) || $file['error'] !== 0) {
        $result['error'] = 'Tidak ada file yang diupload';
        return $result;
    }
    
    // Cek ukuran file
    if ($file['size'] > $max_size) {
        $result['error'] = 'Ukuran file terlalu besar (maksimal ' . ($max_size / 1024 / 1024) . 'MB)';
        return $result;
    }
    
    // Cek ekstensi file
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed_exts)) {
        $result['error'] = 'Format file tidak diperbolehkan. Format yang diterima: ' . implode(', ', $allowed_exts);
        return $result;
    }
    
    // Buat direktori jika belum ada
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Buat nama file yang unik
    $new_filename = uniqid('file_') . '.' . $ext;
    $destination = rtrim($upload_dir, '/') . '/' . $new_filename;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $result['success'] = true;
        $result['file_path'] = str_replace('../', '', $destination);
    } else {
        $result['error'] = 'Gagal mengupload file';
    }
    
    return $result;
}

/**
 * Cek apakah string merupakan tanggal yang valid
 * 
 * @param string $date Tanggal yang akan dicek
 * @param string $format Format tanggal (default: Y-m-d)
 * @return boolean True jika tanggal valid
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Fungsi untuk mendapatkan HTML sidebar yang konsisten di semua halaman
 * 
 * @param string $current_page Halaman yang sedang aktif
 * @param string $base_path Path relatif ke root aplikasi
 * @return string HTML sidebar yang lengkap
 */
function getStandardSidebar($current_page, $base_path = '../') {
    // Pastikan base_path berakhir dengan '/'
    if (substr($base_path, -1) != '/') {
        $base_path .= '/';
    }
    
    $user_role = $_SESSION['user']['role'] ?? '';
    $is_admin = ($user_role === 'admin');
    
    $html = '<nav class="sidebar">
        <div class="sidebar-header text-center">
            <img src="'.$base_path.'assets/img/logo.svg" alt="Logo Zakat Fitrah" class="img-fluid" style="max-width: 180px; margin-bottom: 10px;">
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'dashboard' || $current_page === 'home' ? 'active' : '').'" href="'.$base_path.'index.php">
                    <i class="bi bi-house-door"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'muzakki' ? 'active' : '').'" href="'.$base_path.'views/muzakki.php">
                    <i class="bi bi-people"></i> Data Muzakki
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'kategori_mustahik' ? 'active' : '').'" href="'.$base_path.'views/kategori_mustahik.php">
                    <i class="bi bi-tags"></i> Kategori Mustahik
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'bayar_zakat' ? 'active' : '').'" href="'.$base_path.'views/bayar_zakat.php">
                    <i class="bi bi-cash-coin"></i> Pengumpulan Zakat
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'distribusi' ? 'active' : '').'" href="'.$base_path.'views/distribusi.php">
                    <i class="bi bi-box-seam"></i> Distribusi Zakat
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'distribusi_dokumentasi' ? 'active' : '').'" href="'.$base_path.'views/distribusi_dokumentasi.php">
                    <i class="bi bi-file-earmark-check"></i> Dokumentasi Distribusi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'laporan' ? 'active' : '').'" href="'.$base_path.'views/laporan.php">
                    <i class="bi bi-file-text"></i> Laporan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'tasks' ? 'active' : '').'" href="'.$base_path.'views/tasks.php">
                    <i class="bi bi-list-check"></i> Manajemen Tugas
                </a>
            </li>';
    
    // Menu admin
    if ($is_admin) {
        $html .= '
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'pengaturan' ? 'active' : '').'" href="'.$base_path.'views/pengaturan.php">
                    <i class="bi bi-gear"></i> Pengaturan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link '.($current_page === 'activity_logs' ? 'active' : '').'" href="'.$base_path.'views/activity_logs.php">
                    <i class="bi bi-clock-history"></i> Activity Logs
                </a>
            </li>';
    }
    
    // Logout
    $html .= '
            <li class="nav-item mt-3">
                <a class="nav-link" href="'.$base_path.'logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </nav>';
    
    return $html;
} 