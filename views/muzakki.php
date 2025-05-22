<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';
require_once '../helpers/import_helper.php';
require_once '../includes/functions.php';

// Define base path for assets and links
$base_path = '../';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAuth();

// Initialize database and logger
$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger($db, $auth);

// Set current page for sidebar
$current_page = 'muzakki';

// Tambahkan variabel pencarian nama
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_muzakki':
                try {
                    $stmt = $db->prepare("INSERT INTO muzakki (nama_muzakki, nomor_kk, jumlah_tanggungan, alamat, keterangan) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['nama_muzakki'],
                        $_POST['nomor_kk'],
                        $_POST['jumlah_tanggungan'],
                        $_POST['alamat'],
                        $_POST['keterangan']
                    ]);
                    $logger->log('create', 'muzakki', 'Menambah muzakki: ' . $_POST['nama_muzakki']);
                    $success = "Muzakki berhasil ditambahkan";
                } catch (Exception $e) {
                    $error = "Gagal menambah muzakki: " . $e->getMessage();
                }
                break;
                
            case 'edit_muzakki':
                try {
                    // Validasi input
                    if (empty($_POST['id_muzakki']) || empty($_POST['nama_muzakki'])) {
                        throw new Exception("Data muzakki tidak lengkap");
                    }
                    
                    $id_muzakki = (int)$_POST['id_muzakki'];
                    $nama_muzakki = trim($_POST['nama_muzakki']);
                    $nomor_kk = trim($_POST['nomor_kk']);
                    $jumlah_tanggungan = (int)$_POST['jumlah_tanggungan'];
                    $alamat = trim($_POST['alamat']);
                    $keterangan = trim($_POST['keterangan'] ?? '');
                    
                    // Debugging: log data yang akan diupdate
                    error_log("Updating muzakki ID: $id_muzakki, Nama: $nama_muzakki, Tanggungan: $jumlah_tanggungan, Alamat: $alamat, Keterangan: $keterangan");
                    
                    // Cek apakah muzakki dengan ID tersebut ada
                    $check = $db->prepare("SELECT id_muzakki FROM muzakki WHERE id_muzakki = ?");
                    $check->execute([$id_muzakki]);
                    if (!$check->fetch()) {
                        throw new Exception("Muzakki dengan ID $id_muzakki tidak ditemukan");
                    }
                    
                    // Update data muzakki
                    $stmt = $db->prepare("UPDATE muzakki SET nama_muzakki = ?, nomor_kk = ?, jumlah_tanggungan = ?, alamat = ?, keterangan = ? WHERE id_muzakki = ?");
                    $result = $stmt->execute([
                        $nama_muzakki,
                        $nomor_kk,
                        $jumlah_tanggungan,
                        $alamat,
                        $keterangan,
                        $id_muzakki
                    ]);
                    
                    if (!$result) {
                        throw new Exception("Gagal mengupdate muzakki: " . implode(", ", $stmt->errorInfo()));
                    }
                    
                    // Log aktivitas update
                    $logger->log('update', 'muzakki', "Mengubah muzakki ID $id_muzakki: $nama_muzakki");
                    $success = "Muzakki berhasil diperbarui";
                    
                    // Refresh data setelah update
                    $stmt = $db->query("SELECT * FROM muzakki ORDER BY id_muzakki DESC");
                    $muzakki = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $error = "Gagal mengubah muzakki: " . $e->getMessage();
                    error_log("Error updating muzakki: " . $e->getMessage());
                }
                break;
                
            case 'import':
                // Handle file upload
                if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
                    $file = $_FILES['file'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if ($ext === 'csv') {
                        // Process CSV file
                        $handle = fopen($file['tmp_name'], 'r');
                        $header = fgetcsv($handle);
                        $formatType = ImportHelper::detectImportFormat($header);
                        
                        $db->beginTransaction();
                        try {
                            while (($row = fgetcsv($handle)) !== false) {
                                $data = ImportHelper::processRowByFormat($row, $formatType);
                                
                                if (empty($data['nama_muzakki'])) {
                                    continue;
                                }
                                
                                if (ImportHelper::containsCorruptedData($data['nama_muzakki'])) {
                                    continue;
                                }
                                
                                $stmt = $db->prepare("INSERT INTO muzakki (nama_muzakki, jumlah_tanggungan, alamat, keterangan) VALUES (?, ?, ?, ?)");
                                $stmt->execute([
                                    $data['nama_muzakki'],
                                    $data['jumlah_tanggungan'],
                                    $data['alamat'],
                                    $data['keterangan']
                                ]);
                            }
                            
                            $db->commit();
                            $success = "Data berhasil diimpor";
                            $logger->log('import', 'muzakki', 'Berhasil mengimpor data muzakki');
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error = "Gagal mengimpor data: " . $e->getMessage();
                            $logger->log('error', 'muzakki', 'Gagal mengimpor data: ' . $e->getMessage());
                        }
                        
                        fclose($handle);
                    } else {
                        $error = "Format file tidak didukung. Harap gunakan file CSV.";
                    }
                }
                break;
                
            case 'clean':
                try {
                    // Hapus data dengan nama muzakki yang mengandung karakter aneh
                    $stmt = $db->prepare("DELETE FROM muzakki WHERE 
                        (nama_muzakki REGEXP '[\\x00-\\x08\\x0B-\\x0C\\x0E-\\x1F\\x7F-\\xFF]{4,}')
                        OR (nama_muzakki LIKE '%?%?%?%?%')
                        OR (nama_muzakki LIKE '%\\\\%\\\\%\\\\%\\\\%')
                        OR (nama_muzakki REGEXP '[\\{\\}\\[\\]]{4,}')
                        OR (nama_muzakki REGEXP '[\\*\\?\\^\\+\\=\\~\\|\\<\\>]{5,}')
                        OR (nama_muzakki LIKE '%?d1^%' OR nama_muzakki LIKE '%??M?%??D??%')
                        OR (nama_muzakki LIKE '%??G?O%' OR nama_muzakki LIKE '%?\\*??%')
                        OR (nama_muzakki REGEXP '.*[0-9]{2,}.*[^a-zA-Z0-9\\s.,\\-]{4,}.*[0-9]{2,}')
                        AND nama_muzakki NOT REGEXP '^[a-zA-Z0-9\\s]{1,20}$'");
                    $stmt->execute();
                    
                    $success = "Data berhasil dibersihkan";
                    $logger->log('clean', 'muzakki', 'Berhasil membersihkan data muzakki');
                } catch (Exception $e) {
                    $error = "Gagal membersihkan data: " . $e->getMessage();
                    $logger->log('error', 'muzakki', 'Gagal membersihkan data: ' . $e->getMessage());
                }
                break;
                
            case 'delete_muzakki':
                try {
                    // Validasi input
                    if (empty($_POST['id_muzakki'])) {
                        throw new Exception("ID muzakki tidak ditemukan");
                    }
                    
                    $id_muzakki = (int)$_POST['id_muzakki'];
                    
                    // Cek apakah muzakki dengan ID tersebut ada
                    $check = $db->prepare("SELECT nama_muzakki FROM muzakki WHERE id_muzakki = ?");
                    $check->execute([$id_muzakki]);
                    $muzakki_data = $check->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$muzakki_data) {
                        throw new Exception("Muzakki dengan ID $id_muzakki tidak ditemukan");
                    }
                    
                    $nama_muzakki = $muzakki_data['nama_muzakki'];
                    
                    // Hapus muzakki
                    $stmt = $db->prepare("DELETE FROM muzakki WHERE id_muzakki = ?");
                    $result = $stmt->execute([$id_muzakki]);
                    
                    if (!$result) {
                        throw new Exception("Gagal menghapus muzakki: " . implode(", ", $stmt->errorInfo()));
                    }
                    
                    // Log aktivitas delete
                    $logger->log('delete', 'muzakki', "Menghapus muzakki ID $id_muzakki: $nama_muzakki");
                    $success = "Muzakki berhasil dihapus";
                } catch (Exception $e) {
                    $error = "Gagal menghapus muzakki: " . $e->getMessage();
                    error_log("Error deleting muzakki: " . $e->getMessage());
                }
                break;
        }
    }
}

// Get muzakki list dengan filter pencarian nama
if ($search_nama !== '') {
    $stmt = $db->prepare("SELECT * FROM muzakki WHERE nama_muzakki LIKE ? ORDER BY id_muzakki DESC");
    $stmt->execute(["%$search_nama%"]);
    $muzakki = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->query("SELECT * FROM muzakki ORDER BY id_muzakki DESC");
    $muzakki = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Prepare content for template
ob_start();
?>

<!-- Content -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Data Muzakki</h5>
        <div>
            <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#tambahMuzakkiModal">
                <i class="bi bi-plus"></i> Tambah Muzakki
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload"></i> Import Data
            </button>
            <button type="button" class="btn btn-warning btn-sm" onclick="cleanData()">
                <i class="bi bi-trash"></i> Bersihkan Data
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Form Pencarian Nama Muzakki -->
        <form method="get" class="row g-3 mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search_nama" placeholder="Cari Nama Muzakki" value="<?php echo htmlspecialchars($search_nama); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Cari</button>
            </div>
        </form>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Muzakki</th>
                        <th>Nomor KK</th>
                        <th>Jumlah Tanggungan</th>
                        <th>Alamat</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($muzakki as $m): ?>
                    <tr>
                        <td><?php echo $m['id_muzakki']; ?></td>
                        <td><?php echo htmlspecialchars($m['nama_muzakki']); ?></td>
                        <td><?php echo isset($m['nomor_kk']) ? htmlspecialchars($m['nomor_kk']) : '-'; ?></td>
                        <td><?php echo $m['jumlah_tanggungan']; ?></td>
                        <td><?php echo htmlspecialchars($m['alamat']); ?></td>
                        <td><?php echo htmlspecialchars($m['keterangan']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editMuzakki(<?php echo $m['id_muzakki']; ?>)" data-id="<?php echo $m['id_muzakki']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteMuzakki(<?php echo $m['id_muzakki']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Data Muzakki</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import">
                    <div class="mb-3">
                        <label class="form-label">File CSV</label>
                        <input type="file" class="form-control" name="file" accept=".csv" required>
                    </div>
                    <div class="alert alert-info">
                        Format yang didukung:
                        <ul class="mb-0">
                            <li>Standard: No, Nama Muzakki, Jumlah Tanggungan, Alamat, Keterangan</li>
                            <li>Laporan: No, Nama, Jumlah Orang, Beras, Uang</li>
                            <li>Alternative: No, Nama Muzakki, RT/Lokasi, Jiwa/Jumlah Orang</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tambah Muzakki Modal -->
<div class="modal fade" id="tambahMuzakkiModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Muzakki</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_muzakki">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Muzakki</label>
                        <input type="text" class="form-control" name="nama_muzakki" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor KK</label>
                        <input type="text" class="form-control" name="nomor_kk" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Tanggungan</label>
                        <input type="number" class="form-control" name="jumlah_tanggungan" required min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Muzakki Modal -->
<div class="modal fade" id="editMuzakkiModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Muzakki</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="edit_muzakki_form" onsubmit="return validateEditForm()">
                <input type="hidden" name="action" value="edit_muzakki">
                <input type="hidden" name="id_muzakki" id="edit_id_muzakki">
                <!-- Debug display untuk ID muzakki -->
                <div class="alert alert-info mb-0 small" style="font-size: 11px;">
                    ID Muzakki: <span id="debug_id_muzakki">tidak diset</span>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Muzakki</label>
                        <input type="text" class="form-control" name="nama_muzakki" id="edit_nama_muzakki" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor KK</label>
                        <input type="text" class="form-control" name="nomor_kk" id="edit_nomor_kk" value="<?php echo isset($edit_nomor_kk) ? htmlspecialchars($edit_nomor_kk) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Tanggungan</label>
                        <input type="number" class="form-control" name="jumlah_tanggungan" id="edit_jumlah_tanggungan" required min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" id="edit_alamat" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="edit_keterangan"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Additional JavaScript
$additional_js = <<<JS
<script>
function cleanData() {
    if (confirm('Apakah Anda yakin ingin membersihkan data yang rusak?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="clean">';
        document.body.appendChild(form);
        form.submit();
    }
}

function editMuzakki(id) {
    // Debugging - tampilkan ID yang diterima
    console.log('Mengambil data muzakki dengan ID (awal):', id);
    
    // Ubah ID ke integer dan pastikan valid
    id = parseInt(id, 10);
    
    // Debug setelah konversi
    console.log('ID setelah konversi ke integer:', id);
    
    // Validasi ID
    if (isNaN(id) || id <= 0) {
        alert('ID Muzakki tidak valid: ' + id);
        return;
    }
    
    // Atur nilai ID di form dan di elemen debug
    const hiddenIdField = document.getElementById('edit_id_muzakki');
    hiddenIdField.value = id;
    
    // Update elemen debug display
    const debugElement = document.getElementById('debug_id_muzakki');
    if (debugElement) {
        debugElement.textContent = id;
    }
    
    // Double check value setelah diset
    console.log('ID tersimpan di hidden field:', hiddenIdField.value);
    
    // Gunakan URL yang benar
    const url = '../views/get_muzakki.php?id=' + id;
    
    console.log('Mengambil data dari URL:', url);
    
    // Tambahkan timestamp untuk menghindari cache
    fetch(url + '&_=' + new Date().getTime(), {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        console.log('Status response:', response.status);
        if (!response.ok) {
            throw new Error('Respons server tidak OK: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        // Debug: Tampilkan data yang diterima dari server
        console.log('Data dari server:', data);
        
        if (!data || data.error) {
            alert('Data tidak ditemukan: ' + (data ? data.error : 'Tidak ada respons dari server'));
            return;
        }
        
        // Mengisi form edit dengan data yang diperoleh dengan pengecekan keberadaan data
        document.getElementById('edit_id_muzakki').value = id;
        document.getElementById('edit_nama_muzakki').value = data.nama_muzakki || '';
        document.getElementById('edit_nomor_kk').value = data.nomor_kk || '';
        document.getElementById('edit_jumlah_tanggungan').value = data.jumlah_tanggungan || 1;
        document.getElementById('edit_alamat').value = data.alamat || '';
        document.getElementById('edit_keterangan').value = data.keterangan || '';
        
        // Menampilkan modal edit
        const modal = new bootstrap.Modal(document.getElementById('editMuzakkiModal'));
        modal.show();
    })
    .catch(error => {
        console.error('Error saat fetch data:', error);
        alert('Terjadi kesalahan saat mengambil data: ' + error);
    });
}

function validateEditForm() {
    console.log('Validasi form edit...');
    const id = document.getElementById('edit_id_muzakki').value;
    const nama = document.getElementById('edit_nama_muzakki').value;
    const tanggungan = document.getElementById('edit_jumlah_tanggungan').value;
    const alamat = document.getElementById('edit_alamat').value;
    
    console.log('Data yang akan dikirim:', {
        id,
        nama,
        tanggungan,
        alamat
    });
    
    if (!id || !nama || !tanggungan || !alamat) {
        alert('Silakan lengkapi semua field yang dibutuhkan');
        return false;
    }
    
    return true;
}

function deleteMuzakki(id) {
    // Konversi ID ke integer
    id = parseInt(id, 10);
    
    // Validasi ID
    if (isNaN(id) || id <= 0) {
        alert('ID Muzakki tidak valid!');
        return;
    }
    
    // Konfirmasi penghapusan
    if (confirm('Apakah Anda yakin ingin menghapus muzakki dengan ID ' + id + '?')) {
        console.log('Menghapus muzakki dengan ID:', id);
        
        // Buat form untuk submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        // Tambahkan input untuk action dan id_muzakki
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_muzakki';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id_muzakki';
        idInput.value = id;
        form.appendChild(idInput);
        
        // Tambahkan form ke body dan submit
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
JS;

// Set template variables
$page_title = 'Data Muzakki';
$current_page = 'muzakki';
$base_path = '../';

// Include template
include '../views/templates/layout.php'; 