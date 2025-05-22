<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAuth();

// Initialize database and logger
$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger($db, $auth);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                try {
                    $stmt = $db->prepare("INSERT INTO kategori_mustahik (nama_kategori, jumlah_hak) VALUES (?, ?)");
                    $stmt->execute([
                        $_POST['nama_kategori'],
                        $_POST['jumlah_hak']
                    ]);
                    
                    $logger->log('create', 'kategori_mustahik', 'Menambah kategori mustahik: ' . $_POST['nama_kategori']);
                    $success = "Kategori berhasil ditambahkan";
                } catch (Exception $e) {
                    $error = "Gagal menambah kategori: " . $e->getMessage();
                }
                break;

            case 'update':
                try {
                    $stmt = $db->prepare("UPDATE kategori_mustahik SET nama_kategori = ?, jumlah_hak = ? WHERE id_kategori = ?");
                    $stmt->execute([
                        $_POST['nama_kategori'],
                        $_POST['jumlah_hak'],
                        $_POST['id_kategori']
                    ]);
                    
                    $logger->log('update', 'kategori_mustahik', 'Mengubah kategori mustahik: ' . $_POST['nama_kategori']);
                    $success = "Kategori berhasil diperbarui";
                } catch (Exception $e) {
                    $error = "Gagal memperbarui kategori: " . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    // Check if category is being used
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM mustahik_warga WHERE kategori = (SELECT nama_kategori FROM kategori_mustahik WHERE id_kategori = ?)");
                    $stmt->execute([$_POST['id_kategori']]);
                    $warga_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM mustahik_lainnya WHERE kategori = (SELECT nama_kategori FROM kategori_mustahik WHERE id_kategori = ?)");
                    $stmt->execute([$_POST['id_kategori']]);
                    $lainnya_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                    if ($warga_count > 0 || $lainnya_count > 0) {
                        throw new Exception("Kategori sedang digunakan dan tidak dapat dihapus");
                    }

                    $stmt = $db->prepare("DELETE FROM kategori_mustahik WHERE id_kategori = ?");
                    $stmt->execute([$_POST['id_kategori']]);
                    
                    $logger->log('delete', 'kategori_mustahik', 'Menghapus kategori mustahik ID: ' . $_POST['id_kategori']);
                    $success = "Kategori berhasil dihapus";
                } catch (Exception $e) {
                    $error = "Gagal menghapus kategori: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all categories
$stmt = $db->query("SELECT * FROM kategori_mustahik ORDER BY nama_kategori ASC");
$kategori = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total hak percentage
$total_hak = array_sum(array_column($kategori, 'jumlah_hak'));

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

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Kategori Mustahik</h5>
        <button type="button" class="btn btn-primary btn-sm" onclick="showAddModal()">
            <i class="bi bi-plus"></i> Tambah Kategori
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Kategori</th>
                        <th>Jumlah Hak (%)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kategori as $k): ?>
                    <tr>
                        <td><?php echo $k['id_kategori']; ?></td>
                        <td><?php echo htmlspecialchars($k['nama_kategori']); ?></td>
                        <td><?php echo number_format($k['jumlah_hak'], 1); ?>%</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($k)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="showDeleteModal(<?php echo $k['id_kategori']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th><?php echo number_format($total_hak, 1); ?>%</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" name="nama_kategori" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Hak (%)</label>
                        <input type="number" class="form-control" name="jumlah_hak" step="0.1" min="0" max="100" required>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_kategori" id="edit_id_kategori">
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" name="nama_kategori" id="edit_nama_kategori" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Hak (%)</label>
                        <input type="number" class="form-control" name="jumlah_hak" id="edit_jumlah_hak" step="0.1" min="0" max="100" required>
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_kategori" id="delete_id_kategori">
                    <p>Apakah Anda yakin ingin menghapus kategori ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
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
function showAddModal() {
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function showEditModal(kategori) {
    document.getElementById('edit_id_kategori').value = kategori.id_kategori;
    document.getElementById('edit_nama_kategori').value = kategori.nama_kategori;
    document.getElementById('edit_jumlah_hak').value = kategori.jumlah_hak;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function showDeleteModal(id) {
    document.getElementById('delete_id_kategori').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const hakInput = form.querySelector('[name="jumlah_hak"]');
        if (hakInput) {
            const value = parseFloat(hakInput.value);
            if (value < 0 || value > 100) {
                e.preventDefault();
                alert('Jumlah hak harus antara 0 dan 100');
                return;
            }
        }
    });
});
</script>
JS;

// Set template variables
$page_title = 'Kategori Mustahik';
$current_page = 'kategori_mustahik';
$base_path = '../';

// Include template
include '../views/templates/layout.php'; 