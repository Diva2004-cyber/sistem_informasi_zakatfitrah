<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Define base path for assets and links
$base_path = '../';

// Set current page for sidebar
$current_page = 'pengaturan';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAdmin(); // Only admin can access settings

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_lembaga':
                $stmt = $db->prepare("INSERT INTO lembaga_zakat (nama_lembaga, alamat, telepon, email, ketua_nama, ketua_jabatan) 
                                    VALUES (?, ?, ?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE 
                                    nama_lembaga = VALUES(nama_lembaga),
                                    alamat = VALUES(alamat),
                                    telepon = VALUES(telepon),
                                    email = VALUES(email),
                                    ketua_nama = VALUES(ketua_nama),
                                    ketua_jabatan = VALUES(ketua_jabatan)");
                
                $stmt->execute([
                    $_POST['nama_lembaga'],
                    $_POST['alamat'],
                    $_POST['telepon'],
                    $_POST['email'],
                    $_POST['ketua_nama'],
                    $_POST['ketua_jabatan']
                ]);
                
                $success = "Pengaturan berhasil disimpan!";
                break;

            case 'generate_key':
                $kode_kunci = bin2hex(random_bytes(8)); // 16 karakter acak
                $admin_id = $_SESSION['user_id'];
                $stmt = $db->prepare("INSERT INTO petugas_keys (kode_kunci, created_by) VALUES (?, ?)");
                if ($stmt->execute([$kode_kunci, $admin_id])) {
                    $success = "Kunci berhasil dibuat: <b>$kode_kunci</b>";
                } else {
                    $error = 'Gagal membuat kunci.';
                }
                break;

            case 'generate_reset_key':
                $kode_kunci = bin2hex(random_bytes(8)); // 16 karakter acak
                $admin_id = $_SESSION['user_id'];
                $stmt = $db->prepare("INSERT INTO kunci_reset_petugas (kode_kunci, created_by) VALUES (?, ?)");
                if ($stmt->execute([$kode_kunci, $admin_id])) {
                    $success_reset = "Kunci reset berhasil dibuat: <b>$kode_kunci</b>";
                } else {
                    $error_reset = 'Gagal membuat kunci reset.';
                }
                break;
        }
    }
}

// Get current settings
$stmt = $db->query("SELECT * FROM lembaga_zakat ORDER BY id DESC LIMIT 1");
$lembaga = $stmt->fetch(PDO::FETCH_ASSOC);

// Get key lists
$stmt = $db->query("SELECT pk.*, u.username as admin, u2.username as used_by_user 
                    FROM petugas_keys pk 
                    LEFT JOIN users u ON pk.created_by = u.id 
                    LEFT JOIN users u2 ON pk.used_by = u2.id 
                    ORDER BY pk.created_at DESC");
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT kr.*, u.username as admin, u2.username as used_by_user 
                    FROM kunci_reset_petugas kr 
                    LEFT JOIN users u ON kr.created_by = u.id 
                    LEFT JOIN users u2 ON kr.used_by = u2.id 
                    ORDER BY kr.created_at DESC");
$reset_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare content for template
ob_start();
?>

<!-- Content -->
<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Informasi Lembaga</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_lembaga">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Lembaga/Masjid</label>
                    <input type="text" class="form-control" name="nama_lembaga" 
                           value="<?php echo htmlspecialchars($lembaga['nama_lembaga'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telepon</label>
                    <input type="text" class="form-control" name="telepon" 
                           value="<?php echo htmlspecialchars($lembaga['telepon'] ?? ''); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Alamat</label>
                <textarea class="form-control" name="alamat" rows="3" required><?php echo htmlspecialchars($lembaga['alamat'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" 
                       value="<?php echo htmlspecialchars($lembaga['email'] ?? ''); ?>">
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Ketua/Penanggung Jawab</label>
                    <input type="text" class="form-control" name="ketua_nama" 
                           value="<?php echo htmlspecialchars($lembaga['ketua_nama'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jabatan</label>
                    <input type="text" class="form-control" name="ketua_jabatan" 
                           value="<?php echo htmlspecialchars($lembaga['ketua_jabatan'] ?? ''); ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        </form>
    </div>
</div>

<!-- Timezone Settings Card -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Pengaturan Zona Waktu</h5>
    </div>
    <div class="card-body">
        <p>Pengaturan zona waktu mempengaruhi tampilan waktu di seluruh sistem, termasuk laporan, log aktivitas, dan timestamps.</p>
        <a href="timezone_settings.php" class="btn btn-success">
            <i class="bi bi-clock"></i> Kelola Pengaturan Zona Waktu
        </a>
    </div>
</div>

<!-- Key Management Section -->
<div class="container mt-4">
    <h3>Manajemen Kunci Pendaftaran Petugas</h3>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="post" class="mb-3">
        <input type="hidden" name="action" value="generate_key">
        <button type="submit" class="btn btn-primary">Generate Kunci Petugas</button>
    </form>
    
    <div class="card">
        <div class="card-header">Daftar Kunci Petugas</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Kode Kunci</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th>Waktu Dibuat</th>
                            <th>Dipakai Oleh</th>
                            <th>Waktu Dipakai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keys as $key): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($key['kode_kunci']); ?></td>
                            <td><?php echo $key['status']; ?></td>
                            <td><?php echo htmlspecialchars($key['admin'] ?? '-'); ?></td>
                            <td><?php echo $key['created_at']; ?></td>
                            <td><?php echo htmlspecialchars($key['used_by_user'] ?? '-'); ?></td>
                            <td><?php echo $key['used_at'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reset Key Management Section -->
<div class="container mt-4">
    <h3>Manajemen Kunci Reset Password Petugas</h3>
    <?php if (isset($success_reset)): ?>
        <div class="alert alert-success"><?php echo $success_reset; ?></div>
    <?php endif; ?>
    <?php if (isset($error_reset)): ?>
        <div class="alert alert-danger"><?php echo $error_reset; ?></div>
    <?php endif; ?>
    
    <form method="post" class="mb-3">
        <input type="hidden" name="action" value="generate_reset_key">
        <button type="submit" class="btn btn-warning">Generate Kunci Reset Password Petugas</button>
    </form>
    
    <div class="card">
        <div class="card-header">Daftar Kunci Reset Password Petugas</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Kode Kunci</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th>Waktu Dibuat</th>
                            <th>Dipakai Oleh</th>
                            <th>Waktu Dipakai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reset_keys as $key): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($key['kode_kunci']); ?></td>
                            <td><?php echo $key['status']; ?></td>
                            <td><?php echo htmlspecialchars($key['admin'] ?? '-'); ?></td>
                            <td><?php echo $key['created_at']; ?></td>
                            <td><?php echo htmlspecialchars($key['used_by_user'] ?? '-'); ?></td>
                            <td><?php echo $key['used_at'] ?? '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Set template variables
$page_title = 'Pengaturan';
$current_page = 'pengaturan';
$base_path = '../';

// Include template
include '../views/templates/layout.php'; 