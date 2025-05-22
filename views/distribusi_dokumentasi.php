<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Define base path for assets and links
$base_path = '../';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAuth();

// Debugging session
echo "<!-- Debug SESSION: " . print_r($_SESSION, true) . " -->";

// Initialize database and logger
$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger($db, $auth);

// Set current page for sidebar
$current_page = 'distribusi_dokumentasi';

// Get user role dan Id
$user_id = $auth->getUserId();
$is_admin = $auth->isAdmin();
$user_role = $auth->getCurrentUser()['role'];

// Debug info untuk role
echo "<!-- Debug: User ID = " . $user_id . ", User Role = " . $user_role . ", Is Admin = " . ($is_admin ? 'true' : 'false') . " -->";

// Inisialisasi variable filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Cek apakah user memiliki hak akses
$can_manage_dokumentasi = $auth->hasPermission('manage_dokumentasi');
$can_delete_dokumentasi = $auth->hasPermission('delete_dokumentasi');
$can_update_status = $auth->hasPermission('update_dokumentasi_status');

// Tolak akses jika tidak memiliki hak
if (!$can_manage_dokumentasi) {
    header('Location: ../index.php?error=unauthorized');
    exit;
}

// Ambil data distribusi dari database dengan filter
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(dd.nama_penerima LIKE ? OR dd.id_mustahik LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_kategori !== '') {
    $where[] = 'dd.kategori = ?';
    $params[] = $filter_kategori;
}
if ($filter_status !== '') {
    $where[] = 'dd.status = ?';
    $params[] = $filter_status;
}
$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}
$sql = "
    SELECT 
        dd.id, 
        dd.id_mustahik, 
        dd.jenis_mustahik, 
        dd.nama_penerima, 
        dd.kategori, 
        dd.tanggal_distribusi, 
        dd.status, 
        dd.dokumentasi, 
        dd.catatan_admin,
        dd.created_at,
        dd.updated_at,
        dd.diupdate_oleh as user_id,
        COALESCE(u.nama_lengkap, 
            CASE 
                WHEN dd.diupdate_oleh = 1 THEN 'Admin'
                WHEN dd.diupdate_oleh = 2 THEN 'Petugas'
                WHEN dd.diupdate_oleh = 3 THEN 'Viewer'
                ELSE CONCAT('User ', dd.diupdate_oleh)
            END
        ) as diupdate_oleh
    FROM 
        distribusi_dokumentasi dd
    LEFT JOIN 
        users u ON dd.diupdate_oleh = u.id
    $where_sql
    ORDER BY 
        dd.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$distribusi_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug query to check total counts
$count_stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM mustahik_warga) as total_warga,
        (SELECT COUNT(*) FROM mustahik_lainnya) as total_lainnya,
        (SELECT COUNT(*) FROM distribusi_dokumentasi) as total_dokumentasi
")->fetch(PDO::FETCH_ASSOC);

$total_mustahik = $count_stats['total_warga'] + $count_stats['total_lainnya'];
$total_terdokumentasi = $count_stats['total_dokumentasi'];
$total_belum_terdokumentasi = $total_mustahik - $total_terdokumentasi;

// Count problem records
$stmt = $db->query("SELECT COUNT(*) as total FROM distribusi_dokumentasi WHERE status = 'bermasalah'");
$total_bermasalah = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ambil data mustahik warga yang belum terdokumentasi
$stmt = $db->query("
    SELECT mw.*, 
           m.id_muzakki, 
           km.id_kategori,
           mw.id_mustahikwarga as id,
           'warga' as jenis,
           mw.nama,
           mw.kategori,
           mw.status,
           mw.hak
    FROM mustahik_warga mw
    LEFT JOIN muzakki m ON mw.nama = m.nama_muzakki
    LEFT JOIN kategori_mustahik km ON LOWER(mw.kategori) = LOWER(km.nama_kategori)
    WHERE NOT EXISTS (
        SELECT 1 FROM distribusi_dokumentasi dd 
        WHERE dd.id_mustahik = mw.id_mustahikwarga 
        AND dd.jenis_mustahik = 'warga'
    )
    AND mw.status = 'terdistribusi'
    ORDER BY mw.status ASC, mw.id_mustahikwarga DESC
");

// Debug: Print the query result
$mustahik_warga = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data mustahik lainnya yang belum terdokumentasi
$stmt = $db->query("
    SELECT ml.*, 
           km.id_kategori,
           ml.id_mustahiklainnya as id,
           'lainnya' as jenis,
           ml.nama,
           CASE 
               WHEN ml.kategori = '' AND km.nama_kategori = 'muallaf' THEN 'mualaf'
               WHEN ml.kategori = '' AND km.nama_kategori = 'ibnu_sabil' THEN 'ibnu sabil'
               WHEN km.nama_kategori = 'ibnu_sabil' THEN 'ibnu sabil'
               WHEN ml.kategori = '' AND km.nama_kategori = 'gharimin' THEN 'fisabilillah'
               ELSE ml.kategori 
           END as kategori,
           ml.status,
           ml.hak,
           ml.hak_uang
    FROM mustahik_lainnya ml
    LEFT JOIN kategori_mustahik km ON (
        LOWER(ml.kategori) = LOWER(km.nama_kategori) OR
        (ml.kategori = '' AND ml.id_kategori = km.id_kategori) OR
        (LOWER(ml.kategori) = 'ibnu sabil' AND LOWER(km.nama_kategori) = 'ibnu_sabil') OR
        (LOWER(ml.kategori) = 'mualaf' AND LOWER(km.nama_kategori) = 'muallaf')
    )
    WHERE NOT EXISTS (
        SELECT 1 FROM distribusi_dokumentasi dd 
        WHERE dd.id_mustahik = ml.id_mustahiklainnya 
        AND dd.jenis_mustahik = 'lainnya'
    )
    AND ml.status = 'terdistribusi'
    ORDER BY ml.status ASC, ml.id_mustahiklainnya DESC
");

// Debug: Print the query result
$mustahik_lainnya = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission - Upload dokumentasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_dokumentasi':
                $id_mustahik = $_POST['id_mustahik'];
                $jenis_mustahik = $_POST['jenis_mustahik'];
                $tanggal_distribusi = $_POST['tanggal_distribusi'];
                $status = 'belum_diterima'; // Status awal
                
                // Dapatkan user ID dari session
                $user_id = $_SESSION['user']['id'];
                
                // Cek apakah mustahik sudah ada di distribusi_dokumentasi
                $stmt = $db->prepare("SELECT id FROM distribusi_dokumentasi WHERE id_mustahik = ? AND jenis_mustahik = ?");
                $stmt->execute([$id_mustahik, $jenis_mustahik]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Dapatkan data mustahik dari tabel yang sesuai
                if ($jenis_mustahik == 'warga') {
                    $stmt = $db->prepare("SELECT nama, kategori FROM mustahik_warga WHERE id_mustahikwarga = ?");
                } else {
                    $stmt = $db->prepare("SELECT nama, kategori FROM mustahik_lainnya WHERE id_mustahiklainnya = ?");
                }
                $stmt->execute([$id_mustahik]);
                $mustahik = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Handle file upload
                $dokumentasi = '';
                if (isset($_FILES['dokumentasi']) && $_FILES['dokumentasi']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                    $filename = $_FILES['dokumentasi']['name'];
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    
                    if (in_array(strtolower($ext), $allowed)) {
                        // Buat direktori uploads jika belum ada
                        if (!file_exists('../uploads/dokumentasi')) {
                            mkdir('../uploads/dokumentasi', 0777, true);
                        }
                        
                        // Format nama file dengan timestamp untuk menghindari duplikasi
                        $new_filename = uniqid('doc_') . '.' . $ext;
                        $destination = '../uploads/dokumentasi/' . $new_filename;
                        
                        if (move_uploaded_file($_FILES['dokumentasi']['tmp_name'], $destination)) {
                            $dokumentasi = 'uploads/dokumentasi/' . $new_filename;
                        }
                    }
                }
                
                // Insert atau update data distribusi
                if ($existing) {
                    $stmt = $db->prepare("
                        UPDATE distribusi_dokumentasi 
                        SET tanggal_distribusi = ?, dokumentasi = ?, diupdate_oleh = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$tanggal_distribusi, $dokumentasi, $user_id, $existing['id']]);
                    $message = "Dokumentasi berhasil diperbarui";
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO distribusi_dokumentasi 
                        (id_mustahik, jenis_mustahik, nama_penerima, kategori, tanggal_distribusi, status, dokumentasi, diupdate_oleh) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id_mustahik, 
                        $jenis_mustahik, 
                        $mustahik['nama'], 
                        $mustahik['kategori'], 
                        $tanggal_distribusi, 
                        $status, 
                        $dokumentasi, 
                        $user_id
                    ]);
                    $message = "Dokumentasi berhasil ditambahkan";
                }
                
                // Log aktivitas
                $logger->log('update', 'distribusi_dokumentasi', $message . ' untuk ' . $mustahik['nama']);
                
                // Redirect untuk refresh data
                header("Location: distribusi_dokumentasi.php?success=" . urlencode($message));
                exit;
                break;
                
            case 'update_status':
                // Only allow admin to update status
                if ($_SESSION['user']['role'] === 'admin') {
                    $id = $_POST['id'];
                    $status = $_POST['status'];
                    $catatan = $_POST['catatan_admin'] ?? null;
                    
                    // Dapatkan user ID dari session
                    $user_id = $_SESSION['user']['id'];
                    
                    // Get current data for logging
                    $stmt = $db->prepare("SELECT nama_penerima FROM distribusi_dokumentasi WHERE id = ?");
                    $stmt->execute([$id]);
                    $current_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $db->prepare("
                        UPDATE distribusi_dokumentasi 
                        SET status = ?, catatan_admin = ?, diupdate_oleh = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$status, $catatan, $user_id, $id]);
                    
                    // Log aktivitas
                    $logger->log('update', 'distribusi_dokumentasi', 'Mengubah status distribusi menjadi ' . $status . ' untuk ' . $current_data['nama_penerima']);
                    
                    // Redirect untuk refresh data
                    $message = "Status berhasil diperbarui";
                    header("Location: distribusi_dokumentasi.php?success=" . urlencode($message));
                    exit;
                } else {
                    // Redirect dengan pesan error jika bukan admin
                    $error = "Maaf, hanya admin yang dapat mengubah status distribusi";
                    header("Location: distribusi_dokumentasi.php?error=" . urlencode($error));
                    exit;
                }
                break;
            
            case 'reupload_dokumentasi':
                if (isset($_POST['id']) && is_numeric($_POST['id'])) {
                    $id = $_POST['id'];
                    $tanggal_distribusi = $_POST['tanggal_distribusi'];
                    
                    // Periksa status saat ini
                    $check_query = "SELECT status, nama_penerima FROM distribusi_dokumentasi WHERE id = ?";
                    $stmt = $db->prepare($check_query);
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Hanya lanjutkan jika status bermasalah
                    if ($row && $row['status'] === 'bermasalah') {
                        // Upload file baru
                        if (isset($_FILES['dokumentasi']) && $_FILES['dokumentasi']['error'] == 0) {
                            $file = $_FILES['dokumentasi'];
                            $filename = $file['name'];
                            $tmp_name = $file['tmp_name'];
                            $file_size = $file['size'];
                            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            
                            $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
                            $max_size = 5 * 1024 * 1024; // 5MB
                            
                            if (in_array($file_ext, $allowed_exts) && $file_size <= $max_size) {
                                $new_filename = 'dokumentasi_' . time() . '_' . $id . '.' . $file_ext;
                                $upload_dir = '../uploads/dokumentasi/';
                                $upload_path = $upload_dir . $new_filename;
                                
                                // Dapatkan path file lama untuk dihapus
                                $get_old_file = "SELECT dokumentasi FROM distribusi_dokumentasi WHERE id = ?";
                                $stmt = $db->prepare($get_old_file);
                                $stmt->execute([$id]);
                                $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                $old_file = $old_data['dokumentasi'];
                                
                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    // Hapus file lama jika ada
                                    if ($old_file && file_exists('../' . $old_file)) {
                                        unlink('../' . $old_file);
                                    }
                                    
                                    // Update database
                                    $upload_path_db = 'uploads/dokumentasi/' . $new_filename;
                                    $update_query = "UPDATE distribusi_dokumentasi SET 
                                        tanggal_distribusi = ?, 
                                        dokumentasi = ?, 
                                        status = 'belum_diterima', 
                                        diupdate_oleh = ?,
                                        updated_at = NOW() 
                                        WHERE id = ?";
                                    
                                    $stmt = $db->prepare($update_query);
                                    $stmt->execute([$tanggal_distribusi, $upload_path_db, $user_id, $id]);
                                    
                                    // Log aktivitas
                                    $logger->log('update', 'distribusi_dokumentasi', 'Mengupload ulang dokumentasi untuk ' . $row['nama_penerima']);
                                    
                                    $message = "Dokumentasi berhasil diupload ulang dan status diubah menjadi 'Belum Diterima'";
                                    header("Location: distribusi_dokumentasi.php?success=" . urlencode($message));
                                    exit;
                                } else {
                                    $error = "Gagal mengupload file";
                                    header("Location: distribusi_dokumentasi.php?error=" . urlencode($error));
                                    exit;
                                }
                            } else {
                                $error = "Format file tidak valid atau ukuran melebihi 5MB";
                                header("Location: distribusi_dokumentasi.php?error=" . urlencode($error));
                                exit;
                            }
                        } else {
                            $error = "File dokumentasi diperlukan";
                            header("Location: distribusi_dokumentasi.php?error=" . urlencode($error));
                            exit;
                        }
                    } else {
                        $error = "Hanya dokumentasi dengan status 'Bermasalah' yang dapat diupload ulang";
                        header("Location: distribusi_dokumentasi.php?error=" . urlencode($error));
                        exit;
                    }
                }
                break;
            
            case 'delete_dokumentasi':
                if ($_SESSION['user']['role'] === 'admin' && isset($_POST['id']) && is_numeric($_POST['id'])) {
                    $id = $_POST['id'];
                    
                    // Dapatkan informasi dokumentasi
                    $query = "SELECT id_mustahik, jenis_mustahik, dokumentasi, nama_penerima 
                             FROM distribusi_dokumentasi 
                             WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($row) {
                        // Update status mustahik kembali ke belum didokumentasi
                        $id_table = $row['jenis_mustahik'] == 'warga' ? 'id_mustahikwarga' : 'id_mustahiklainnya';
                        $table = $row['jenis_mustahik'] == 'warga' ? 'mustahik_warga' : 'mustahik_lainnya';
                        $update_mustahik = "UPDATE $table SET status = 'belum_didokumentasi' WHERE $id_table = ?";
                        $stmt = $db->prepare($update_mustahik);
                        $stmt->execute([$row['id_mustahik']]);
                        
                        // Hapus file dokumentasi jika ada
                        if ($row['dokumentasi'] && file_exists('../' . $row['dokumentasi'])) {
                            unlink('../' . $row['dokumentasi']);
                        }
                        
                        // Hapus data dari tabel distribusi_dokumentasi
                        $delete_query = "DELETE FROM distribusi_dokumentasi WHERE id = ?";
                        $stmt = $db->prepare($delete_query);
                        $stmt->execute([$id]);
                        
                        // Log aktivitas
                        $logger->log('delete', 'distribusi_dokumentasi', 'Menghapus dokumentasi untuk ' . $row['nama_penerima']);
                        
                        $message = "Dokumentasi berhasil dihapus dan mustahik dikembalikan ke daftar yang belum didokumentasi";
                        header("Location: distribusi_dokumentasi.php?success=" . urlencode($message));
                        exit;
                    } else {
                        $error = "Data dokumentasi tidak ditemukan";
                        header("Location: distribusi_dokumentasi.php?error=" . urlencode($error));
                        exit;
                    }
                } else {
                    $error = "Anda tidak memiliki izin untuk menghapus dokumentasi";
                    header("Location: distribusi_dokumentasi.php?error=" . urlencode($error));
                    exit;
                }
                break;
        }
    }
    
    // Redirect back to the page
    header("Location: distribusi_dokumentasi.php");
    exit;
}

// Set page title
$page_title = "Dokumentasi Distribusi Zakat";

// Start output buffering
ob_start();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Dokumentasi Distribusi Zakat</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Dokumentasi Distribusi Zakat</li>
    </ol>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- Info Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>Total Mustahik</div>
                        <div><strong><?= $total_mustahik ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>Sudah Terdokumentasi</div>
                        <div><strong><?= $total_terdokumentasi ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>Belum Terdokumentasi</div>
                        <div><strong><?= $total_belum_terdokumentasi ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>Status Bermasalah</div>
                        <div><strong><?= $total_bermasalah ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Upload Dokumentasi -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-upload me-1"></i>
            Upload Dokumentasi Distribusi Zakat
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_dokumentasi">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="jenis_mustahik" class="form-label">Jenis Mustahik</label>
                            <select class="form-select" id="jenis_mustahik" name="jenis_mustahik" required>
                                <option value="" selected disabled>Pilih Jenis Mustahik</option>
                                <option value="warga">Mustahik Warga</option>
                                <option value="lainnya">Mustahik Lainnya</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="id_mustahik" class="form-label">Nama Mustahik</label>
                            <select class="form-select" id="id_mustahik" name="id_mustahik" required>
                                <option value="" selected disabled>Pilih Mustahik</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="tanggal_distribusi" class="form-label">Tanggal Distribusi</label>
                            <input type="date" class="form-control" id="tanggal_distribusi" name="tanggal_distribusi" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="dokumentasi" class="form-label">Dokumentasi (Foto/PDF)</label>
                            <input type="file" class="form-control" id="dokumentasi" name="dokumentasi" accept=".jpg,.jpeg,.png,.pdf" required>
                            <small class="text-muted">Format yang diterima: JPG, JPEG, PNG, PDF (Maks. 5MB)</small>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan Dokumentasi
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Filter dan Pencarian -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filter dan Pencarian
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Cari nama atau ID" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="kategori">
                        <option value="">Semua Kategori</option>
                        <option value="fakir" <?= ($filter_kategori == 'fakir') ? 'selected' : '' ?>>Fakir</option>
                        <option value="miskin" <?= ($filter_kategori == 'miskin') ? 'selected' : '' ?>>Miskin</option>
                        <option value="mampu" <?= ($filter_kategori == 'mampu') ? 'selected' : '' ?>>Mampu</option>
                        <option value="amilin" <?= ($filter_kategori == 'amilin') ? 'selected' : '' ?>>Amilin</option>
                        <option value="fisabilillah" <?= ($filter_kategori == 'fisabilillah') ? 'selected' : '' ?>>Fisabilillah</option>
                        <option value="mualaf" <?= ($filter_kategori == 'mualaf') ? 'selected' : '' ?>>Mualaf</option>
                        <option value="ibnu sabil" <?= ($filter_kategori == 'ibnu sabil') ? 'selected' : '' ?>>Ibnu Sabil</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="belum_diterima" <?= ($filter_status == 'belum_diterima') ? 'selected' : '' ?>>Belum Diterima</option>
                        <option value="sudah_diterima" <?= ($filter_status == 'sudah_diterima') ? 'selected' : '' ?>>Sudah Diterima</option>
                        <option value="bermasalah" <?= ($filter_status == 'bermasalah') ? 'selected' : '' ?>>Bermasalah</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tabel Dokumentasi Distribusi -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Data Dokumentasi Distribusi Zakat
        </div>
        <div class="card-body">
            <table class="table table-striped table-bordered" id="datatablesDistribusi" width="100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Penerima</th>
                        <th>Kategori</th>
                        <th>Jenis</th>
                        <th>Tanggal Distribusi</th>
                        <th>Status</th>
                        <th>Dokumentasi</th>
                        <th>Catatan</th>
                        <th>Diupdate Oleh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($distribusi_data)): ?>
                        <?php foreach ($distribusi_data as $data): ?>
                        <tr>
                            <td><?= htmlspecialchars($data['id']) ?></td>
                            <td><?= htmlspecialchars($data['nama_penerima']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($data['kategori'])) ?></td>
                            <td><?= $data['jenis_mustahik'] == 'warga' ? 'Mustahik Warga' : 'Mustahik Lainnya' ?></td>
                            <td><?= $data['tanggal_distribusi'] ? date('d-m-Y', strtotime($data['tanggal_distribusi'])) : '-' ?></td>
                            <td>
                                <?php 
                                $badge_class = '';
                                $status_text = '';
                                switch ($data['status']) {
                                    case 'belum_diterima':
                                        $badge_class = 'bg-warning';
                                        $status_text = 'Belum Diterima';
                                        break;
                                    case 'sudah_diterima':
                                        $badge_class = 'bg-success';
                                        $status_text = 'Sudah Diterima';
                                        break;
                                    case 'bermasalah':
                                        $badge_class = 'bg-danger';
                                        $status_text = 'Bermasalah';
                                        break;
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $status_text ?></span>
                            </td>
                            <td>
                                <?php if ($data['dokumentasi']): ?>
                                <a href="../<?= $data['dokumentasi'] ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye me-1"></i> Lihat
                                </a>
                                <?php else: ?>
                                <span class="badge bg-secondary">Belum Ada</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($data['catatan_admin'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($data['diupdate_oleh']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($can_update_status): ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateStatusModal-<?= $data['id'] ?>">
                                        <i class="fas fa-edit me-1"></i> Update Status
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($can_delete_dokumentasi): ?>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal-<?= $data['id'] ?>">
                                        <i class="fas fa-trash me-1"></i> Hapus
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Update Status Modals -->
<?php if (!empty($distribusi_data)): ?>
    <?php foreach ($distribusi_data as $data): ?>
    <div class="modal fade" id="updateStatusModal-<?= $data['id'] ?>" tabindex="-1" aria-labelledby="updateStatusModalLabel-<?= $data['id'] ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel-<?= $data['id'] ?>">Update Status Distribusi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?= $data['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="status-<?= $data['id'] ?>" class="form-label">Status</label>
                            <select class="form-select" id="status-<?= $data['id'] ?>" name="status" required>
                                <option value="belum_diterima" <?= $data['status'] == 'belum_diterima' ? 'selected' : '' ?>>Belum Diterima</option>
                                <option value="sudah_diterima" <?= $data['status'] == 'sudah_diterima' ? 'selected' : '' ?>>Sudah Diterima</option>
                                <option value="bermasalah" <?= $data['status'] == 'bermasalah' ? 'selected' : '' ?>>Bermasalah</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="catatan_admin-<?= $data['id'] ?>" class="form-label">Catatan Admin</label>
                            <textarea class="form-control" id="catatan_admin-<?= $data['id'] ?>" name="catatan_admin" rows="3"><?= htmlspecialchars($data['catatan_admin'] ?: '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Upload Ulang (untuk petugas) -->
    <div class="modal fade" id="reuploadModal-<?= $data['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Ulang Dokumentasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reupload_dokumentasi">
                        <input type="hidden" name="id" value="<?= $data['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Penerima</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($data['nama_penerima']) ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tanggal_distribusi" class="form-label">Tanggal Distribusi</label>
                            <input type="date" class="form-control" id="tanggal_distribusi" name="tanggal_distribusi" value="<?= $data['tanggal_distribusi'] ? date('Y-m-d', strtotime($data['tanggal_distribusi'])) : date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dokumentasi" class="form-label">Dokumentasi Baru (Foto/PDF)</label>
                            <input type="file" class="form-control" id="dokumentasi" name="dokumentasi" accept=".jpg,.jpeg,.png,.pdf" required>
                            <small class="text-muted">Format yang diterima: JPG, JPEG, PNG, PDF (Maks. 5MB)</small>
                        </div>
                        
                        <?php if ($data['catatan_admin']): ?>
                        <div class="mb-3">
                            <label class="form-label">Catatan Admin</label>
                            <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($data['catatan_admin']) ?></textarea>
                            <small class="text-muted">Perbaiki masalah sesuai catatan admin di atas</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-upload me-1"></i> Upload Ulang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Hapus (untuk admin) -->
    <div class="modal fade" id="deleteModal-<?= $data['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_dokumentasi">
                        <input type="hidden" name="id" value="<?= $data['id'] ?>">
                        
                        <p>Apakah Anda yakin ingin menghapus dokumentasi untuk mustahik:</p>
                        <p><strong><?= htmlspecialchars($data['nama_penerima']) ?></strong>?</p>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i> Perhatian: Tindakan ini akan menghapus dokumentasi dan mengembalikan mustahik ke daftar yang belum didokumentasi.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Hapus Dokumentasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Use vanilla JavaScript to ensure this works without jQuery
document.addEventListener('DOMContentLoaded', function() {
    // Wait for jQuery to be available
    if (typeof jQuery !== 'undefined') {
        // Initialize DataTables
        jQuery('#datatablesDistribusi').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json'
            },
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            pageLength: 10
        });
    }
    
    // Data mustahik dari PHP
    const mustahikWarga = <?= json_encode($mustahik_warga) ?>;
    const mustahikLainnya = <?= json_encode($mustahik_lainnya) ?>;
    
    console.log('Mustahik Warga:', mustahikWarga);
    console.log('Mustahik Lainnya:', mustahikLainnya);
    
    // Handle dynamic select for mustahik
    const jenisSelect = document.getElementById('jenis_mustahik');
    const mustahikSelect = document.getElementById('id_mustahik');
    
    if (jenisSelect && mustahikSelect) {
        jenisSelect.addEventListener('change', function() {
            // Enable mustahik select
            mustahikSelect.disabled = false;
            
            // Clear options
            mustahikSelect.innerHTML = '<option value="" selected disabled>Pilih Mustahik</option>';
            
            const selectedData = this.value === 'warga' ? mustahikWarga : mustahikLainnya;
            
            // Add placeholder if no data
            if (selectedData.length === 0) {
                const option = document.createElement('option');
                option.value = "";
                option.disabled = true;
                option.selected = true;
                option.textContent = "Tidak ada data mustahik yang telah didistribusi namun belum didokumentasi";
                mustahikSelect.appendChild(option);
            } else {
                // Fill options
                selectedData.forEach(mustahik => {
                    const option = document.createElement('option');
                    option.value = mustahik.id;
                    
                    let hakInfo = '';
                    if (mustahik.hak) {
                        hakInfo = ` (${mustahik.hak} kg)`;
                    }
                    
                    let hakUangInfo = '';
                    if (mustahik.hak_uang) {
                        hakUangInfo = ` (Rp ${new Intl.NumberFormat('id-ID').format(mustahik.hak_uang)})`;
                    }
                    
                    let statusInfo = '';
                    if (mustahik.status) {
                        statusInfo = ` [${mustahik.status === 'terdistribusi' ? 'Terdistribusi' : mustahik.status}]`;
                    }
                    
                    option.textContent = `${mustahik.nama} - ${mustahik.kategori}${hakInfo}${hakUangInfo}${statusInfo}`;
                    mustahikSelect.appendChild(option);
                });
            }
        });
        
        // Trigger the change event to populate on load if a value is selected
        if (jenisSelect.value) {
            jenisSelect.dispatchEvent(new Event('change'));
        }
    }
});
</script>

<?php
// Get the content from buffer
$content = ob_get_clean();

// Include layout
include_once "templates/layout.php";
?> 