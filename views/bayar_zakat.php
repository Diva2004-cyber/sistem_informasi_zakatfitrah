<?php
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';
require_once '../includes/functions.php';
require_once '../middleware/auth_middleware.php';

// Define base path for assets and links
$base_path = '../';

// Set current page for sidebar
$current_page = 'bayar_zakat';
$page_title = 'Pengumpulan Zakat';

$auth = new Auth();
$error_message = '';

// Gunakan AuthMiddleware untuk memeriksa akses
$authMiddleware = new AuthMiddleware();
$authMiddleware->requireAuth(['admin', 'petugas']);

// Kode di bawah ini hanya akan dijalankan jika pengguna memiliki akses

$database = new Database();
$db = $database->getConnection();

// Check if database connection is successful
if ($db === null) {
    $error_message = 'Tidak dapat terhubung ke database. Silakan periksa konfigurasi database Anda.';
}

$activity_logger = new ActivityLogger();

// Tambahkan variabel pencarian nama
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db !== null) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nama_kk = isset($_POST['nama_kk']) ? trim($_POST['nama_kk']) : '';
                
                // Validasi input
                if (empty($nama_kk)) {
                    $error_message = "Nama KK tidak boleh kosong";
                    break;
                }
                
                $jenis_bayar = isset($_POST['jenis_bayar']) ? $_POST['jenis_bayar'] : 'beras';
                $jumlah_tanggungan = isset($_POST['jumlah_tanggungan']) ? (int)$_POST['jumlah_tanggungan'] : 1;
                $jumlah_tanggunganyangdibayar = isset($_POST['jumlah_tanggunganyangdibayar']) ? (int)$_POST['jumlah_tanggunganyangdibayar'] : $jumlah_tanggungan;
                $bayar_beras = ($jenis_bayar == 'beras') ? (isset($_POST['jumlah_bayar']) ? (float)$_POST['jumlah_bayar'] : 0) : 0;
                $bayar_uang = ($jenis_bayar == 'uang') ? (isset($_POST['jumlah_bayar']) ? (float)$_POST['jumlah_bayar'] : 0) : 0;
                
                try {
                    $stmt = $db->prepare("INSERT INTO bayarzakat (nama_KK, jumlah_tanggungan, jenis_bayar, jumlah_tanggunganyangdibayar, bayar_beras, bayar_uang) VALUES (?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$nama_kk, $jumlah_tanggungan, $jenis_bayar, $jumlah_tanggunganyangdibayar, $bayar_beras, $bayar_uang]);
                    
                    if ($result) {
                        // Log the activity
                        $payment_id = $db->lastInsertId();
                        $activity_logger->log(
                            'create',
                            'bayarzakat',
                            "Membuat pembayaran zakat baru oleh $nama_kk (" . ($jenis_bayar == 'beras' ? "$bayar_beras kg beras" : "Rp " . number_format($bayar_uang, 0, ',', '.')) . ")",
                            $payment_id
                        );
                        
                        // Create notification
                        $user_id = $auth->getUserId();
                        if ($user_id) {
                            $stmt = $db->prepare("INSERT INTO notifications (user_id, type, message, status) VALUES (?, ?, ?, 'unread')");
                            $stmt->execute([
                                $user_id,
                                'payment',
                                "Pembayaran zakat baru oleh $nama_kk (" . ($jenis_bayar == 'beras' ? "$bayar_beras kg beras" : "Rp " . number_format($bayar_uang, 0, ',', '.')) . ")"
                            ]);
                        }
                    }
                } catch (PDOException $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'update':
                $id_zakat = isset($_POST['id_zakat']) ? (int)$_POST['id_zakat'] : 0;
                $nama_kk = isset($_POST['nama_kk']) ? trim($_POST['nama_kk']) : '';
                $jenis_bayar = isset($_POST['jenis_bayar']) ? $_POST['jenis_bayar'] : '';
                $jumlah_bayar = isset($_POST['jumlah_bayar']) ? (float)$_POST['jumlah_bayar'] : 0;
                
                // Validasi input
                if (empty($id_zakat) || empty($nama_kk) || empty($jenis_bayar)) {
                    $error_message = "Semua field harus diisi";
                    break;
                }
                
                try {
                    // Get existing payment details for logging
                    $stmt = $db->prepare("SELECT * FROM bayarzakat WHERE id_zakat = ?");
                    $stmt->execute([$id_zakat]);
                    $old_payment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Set the appropriate payment field based on type
                    $bayar_beras = ($jenis_bayar == 'beras') ? $jumlah_bayar : 0;
                    $bayar_uang = ($jenis_bayar == 'uang') ? $jumlah_bayar : 0;
                    
                    // Update the payment
                    $stmt = $db->prepare("UPDATE bayarzakat SET nama_KK = ?, jenis_bayar = ?, bayar_beras = ?, bayar_uang = ? WHERE id_zakat = ?");
                    $result = $stmt->execute([$nama_kk, $jenis_bayar, $bayar_beras, $bayar_uang, $id_zakat]);
                    
                    if ($result) {
                        // Log the activity
                        $activity_logger->log(
                            'update',
                            'bayarzakat',
                            "Mengupdate pembayaran zakat oleh $nama_kk dari " . 
                            ($old_payment['jenis_bayar'] == 'beras' ? "{$old_payment['bayar_beras']} kg beras" : "Rp " . number_format($old_payment['bayar_uang'], 0, ',', '.')) .
                            " menjadi " . 
                            ($jenis_bayar == 'beras' ? "$bayar_beras kg beras" : "Rp " . number_format($bayar_uang, 0, ',', '.')),
                            $id_zakat
                        );
                    }
                } catch (PDOException $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                $id_zakat = isset($_POST['id_zakat']) ? (int)$_POST['id_zakat'] : 0;
                
                if (empty($id_zakat)) {
                    $error_message = "ID zakat tidak valid";
                    break;
                }
                
                try {
                    // Get payment details before deletion for logging
                    $stmt = $db->prepare("SELECT nama_KK, jenis_bayar, bayar_beras, bayar_uang FROM bayarzakat WHERE id_zakat = ?");
                    $stmt->execute([$id_zakat]);
                    $payment_details = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($payment_details) {
                        $stmt = $db->prepare("DELETE FROM bayarzakat WHERE id_zakat = ?");
                        $result = $stmt->execute([$id_zakat]);
                        
                        if ($result) {
                            // Log the activity
                            $nama_kk = $payment_details['nama_KK'];
                            $jenis_bayar = $payment_details['jenis_bayar'];
                            $bayar_value = ($jenis_bayar == 'beras') ? $payment_details['bayar_beras'] . " kg beras" : "Rp " . number_format($payment_details['bayar_uang'], 0, ',', '.');
                            
                            $activity_logger->log(
                                'delete',
                                'bayarzakat',
                                "Menghapus pembayaran zakat oleh $nama_kk ($bayar_value)",
                                $id_zakat
                            );
                        }
                    } else {
                        $error_message = "Data pembayaran tidak ditemukan";
                    }
                } catch (PDOException $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
        }
        
        // Redirect only if there's no error
        if (empty($error_message)) {
            header("Location: bayar_zakat.php");
            exit;
        }
    }
}

// Default empty arrays in case DB connection fails
$lembaga = [];
$muzakki_list = [];
$payments = [];

// Only try to fetch data if database connection is successful
if ($db !== null) {
    // Get current settings
    $stmt = $db->query("SELECT * FROM lembaga_zakat ORDER BY id DESC LIMIT 1");
    if ($stmt) {
        $lembaga = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all muzakki for dropdown with payment status
    $stmt = $db->query("SELECT m.*, 
                              CASE 
                                WHEN b.id_zakat IS NOT NULL THEN 'Sudah Bayar'
                                ELSE 'Belum Bayar'
                              END as status_bayar
                       FROM muzakki m
                       LEFT JOIN bayarzakat b ON m.nama_muzakki = b.nama_KK
                       ORDER BY status_bayar DESC, m.nama_muzakki");
    if ($stmt) {
        $muzakki_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all payment records with muzakki data, tambahkan filter pencarian nama
    $query_payments = "SELECT 
                           b.id_zakat,
                           b.nama_KK,
                           b.jumlah_tanggungan,
                           b.jumlah_tanggunganyangdibayar,
                           b.jenis_bayar,
                           b.bayar_beras,
                           b.bayar_uang,
                           b.created_at,
                           COALESCE(m.nama_muzakki, b.nama_KK) as nama_muzakki,
                           m.alamat,
                           m.nomor_kk
                       FROM bayarzakat b
                       LEFT JOIN muzakki m ON b.nama_KK = m.nama_muzakki";
    $params = [];
    if ($search_nama !== '') {
        $query_payments .= " WHERE b.nama_KK LIKE ?";
        $params[] = "%$search_nama%";
    }
    $query_payments .= " ORDER BY b.id_zakat DESC";
    $stmt = $db->prepare($query_payments);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Mulai output buffering untuk menangkap konten utama
ob_start();
?>

<!-- Content -->
<div class="container-fluid">
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
            <i class="bi bi-plus"></i> Tambah Pembayaran
        </button>
    </div>

    <!-- Form Pencarian Nama KK -->
    <form method="get" class="row g-3 mb-3">
        <div class="col-md-4">
            <input type="text" class="form-control" name="search_nama" placeholder="Cari Nama KK" value="<?php echo htmlspecialchars($search_nama); ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Cari</button>
        </div>
    </form>

    <!-- Payment List -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama KK</th>
                            <th>Nomor KK</th>
                            <th>Jumlah Tanggungan</th>
                            <th>Jumlah Dibayar</th>
                            <th>Jenis Bayar</th>
                            <th>Jumlah</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo $payment['id_zakat']; ?></td>
                            <td><?php echo htmlspecialchars($payment['nama_KK']); ?></td>
                            <td><?php echo htmlspecialchars($payment['nomor_kk'] ?? ''); ?></td>
                            <td><?php echo $payment['jumlah_tanggungan']; ?></td>
                            <td><?php echo $payment['jumlah_tanggunganyangdibayar']; ?></td>
                            <td><?php echo ucfirst($payment['jenis_bayar']); ?></td>
                            <td>
                                <?php if ($payment['jenis_bayar'] == 'beras'): ?>
                                    <?php echo $payment['bayar_beras']; ?> kg
                                <?php else: ?>
                                    Rp <?php echo number_format($payment['bayar_uang'], 0, ',', '.'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-info" onclick="window.location.href='cetak_kwitansi.php?id=<?php echo $payment['id_zakat']; ?>'" title="Cetak">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning edit-btn" 
                                            data-id="<?php echo $payment['id_zakat']; ?>"
                                            data-nama="<?php echo htmlspecialchars($payment['nama_KK']); ?>"
                                            data-jenis="<?php echo $payment['jenis_bayar']; ?>"
                                            data-jumlah="<?php echo ($payment['jenis_bayar'] == 'beras') ? $payment['bayar_beras'] : $payment['bayar_uang']; ?>"
                                            title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-btn"
                                            data-id="<?php echo $payment['id_zakat']; ?>"
                                            data-nama="<?php echo htmlspecialchars($payment['nama_KK']); ?>"
                                            title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" title="Tandai Dibagikan">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Pembayaran Zakat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPaymentForm" method="post">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="nama_kk" class="form-label">Nama Kepala Keluarga</label>
                        <select class="form-select" id="nama_kk" name="nama_kk" required>
                            <option value="">-- Pilih Nama --</option>
                            <?php foreach ($muzakki_list as $muzakki): ?>
                                <option value="<?php echo htmlspecialchars($muzakki['nama_muzakki']); ?>" data-tanggungan="<?php echo $muzakki['jumlah_tanggungan']; ?>" data-nomor-kk="<?php echo htmlspecialchars($muzakki['nomor_kk']); ?>" <?php echo $muzakki['status_bayar'] === 'Sudah Bayar' ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($muzakki['nama_muzakki']); ?> - KK: <?php echo htmlspecialchars($muzakki['nomor_kk']); ?>
                                    <?php if ($muzakki['status_bayar'] === 'Sudah Bayar'): ?>
                                        (Sudah Bayar)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jumlah_tanggungan" class="form-label">Jumlah Tanggungan</label>
                        <input type="number" class="form-control" id="jumlah_tanggungan" name="jumlah_tanggungan" min="1" value="1" required readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jumlah_tanggunganyangdibayar" class="form-label">Jumlah Tanggungan Yang Dibayar</label>
                        <input type="number" class="form-control" id="jumlah_tanggunganyangdibayar" name="jumlah_tanggunganyangdibayar" min="1" value="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jenis Pembayaran</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_bayar" id="jenis_beras" value="beras" checked>
                            <label class="form-check-label" for="jenis_beras">Beras</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_bayar" id="jenis_uang" value="uang">
                            <label class="form-check-label" for="jenis_uang">Uang</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jumlah_bayar" class="form-label">Jumlah <span id="label_satuan">Beras (kg)</span></label>
                        <input type="number" step="0.01" class="form-control" id="jumlah_bayar" name="jumlah_bayar" required>
                        <small class="form-text text-muted" id="bayar_guide">Pembayaran beras untuk 1 orang = 2.5 kg</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Pembayaran Zakat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editPaymentForm" method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_zakat" id="edit_id_zakat">
                    
                    <div class="mb-3">
                        <label for="edit_nama_kk" class="form-label">Nama Kepala Keluarga</label>
                        <input type="text" class="form-control" id="edit_nama_kk" name="nama_kk" required readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jenis Pembayaran</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_bayar" id="edit_jenis_beras" value="beras">
                            <label class="form-check-label" for="edit_jenis_beras">Beras</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_bayar" id="edit_jenis_uang" value="uang">
                            <label class="form-check-label" for="edit_jenis_uang">Uang</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_jumlah_bayar" class="form-label">Jumlah <span id="edit_label_satuan">Beras (kg)</span></label>
                        <input type="number" step="0.01" class="form-control" id="edit_jumlah_bayar" name="jumlah_bayar" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Payment Modal -->
<div class="modal fade" id="deletePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Pembayaran Zakat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah anda yakin ingin menghapus pembayaran dari <strong id="delete_nama"></strong>?</p>
                <form id="deletePaymentForm" method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_zakat" id="delete_id_zakat">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle nama_kk change to set jumlah_tanggungan
    document.getElementById('nama_kk').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const tanggungan = selectedOption.getAttribute('data-tanggungan');
        
        if (tanggungan) {
            document.getElementById('jumlah_tanggungan').value = tanggungan;
            document.getElementById('jumlah_tanggunganyangdibayar').value = tanggungan;
            
            // Update bayar guide
            updateBayarGuide();
        }
    });
    
    // Handle jenis_bayar change to update label and guide
    const radios = document.querySelectorAll('input[name="jenis_bayar"]');
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateLabelSatuan();
            updateBayarGuide();
        });
    });
    
    // Handle edit button clicks
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const jenis = this.getAttribute('data-jenis');
            const jumlah = this.getAttribute('data-jumlah');
            
            document.getElementById('edit_id_zakat').value = id;
            document.getElementById('edit_nama_kk').value = nama;
            
            if (jenis === 'beras') {
                document.getElementById('edit_jenis_beras').checked = true;
                document.getElementById('edit_label_satuan').textContent = 'Beras (kg)';
            } else {
                document.getElementById('edit_jenis_uang').checked = true;
                document.getElementById('edit_label_satuan').textContent = 'Uang (Rp)';
            }
            
            document.getElementById('edit_jumlah_bayar').value = jumlah;
            
            // Initialize Bootstrap modal
            new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
        });
    });
    
    // Handle delete button clicks
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            
            document.getElementById('delete_id_zakat').value = id;
            document.getElementById('delete_nama').textContent = nama;
            
            // Initialize Bootstrap modal
            new bootstrap.Modal(document.getElementById('deletePaymentModal')).show();
        });
    });
    
    // Handle edit form jenis_bayar change
    const editRadios = document.querySelectorAll('#editPaymentForm input[name="jenis_bayar"]');
    editRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'beras') {
                document.getElementById('edit_label_satuan').textContent = 'Beras (kg)';
            } else {
                document.getElementById('edit_label_satuan').textContent = 'Uang (Rp)';
            }
        });
    });
    
    // Function to update label satuan
    function updateLabelSatuan() {
        const jenis = document.querySelector('input[name="jenis_bayar"]:checked').value;
        if (jenis === 'beras') {
            document.getElementById('label_satuan').textContent = 'Beras (kg)';
        } else {
            document.getElementById('label_satuan').textContent = 'Uang (Rp)';
        }
    }
    
    // Function to update bayar guide
    function updateBayarGuide() {
        const jenis = document.querySelector('input[name="jenis_bayar"]:checked').value;
        const tanggungan = document.getElementById('jumlah_tanggungan').value;
        
        if (jenis === 'beras') {
            const totalBeras = (tanggungan * 2.5).toFixed(2);
            document.getElementById('bayar_guide').textContent = `Pembayaran beras untuk ${tanggungan} orang = ${totalBeras} kg`;
        } else {
            const hargaBeras = <?php echo isset($lembaga['harga_beras']) ? $lembaga['harga_beras'] : 15000; ?>;
            const totalUang = (tanggungan * 2.5 * hargaBeras).toFixed(0);
            document.getElementById('bayar_guide').textContent = `Pembayaran uang untuk ${tanggungan} orang = Rp ${new Intl.NumberFormat('id-ID').format(totalUang)}`;
        }
    }
    
    // Initialize on load
    updateLabelSatuan();
    updateBayarGuide();

    // Tambahkan fitur auto isi jumlah bayar
    const namaKKSelect = document.getElementById('nama_kk');
    const jumlahTanggunganInput = document.getElementById('jumlah_tanggungan');
    const jumlahTanggunganDibayarInput = document.getElementById('jumlah_tanggunganyangdibayar');
    const jenisBerasRadio = document.getElementById('jenis_beras');
    const jenisUangRadio = document.getElementById('jenis_uang');
    const jumlahBayarInput = document.getElementById('jumlah_bayar');
    const hargaBeras = <?php echo isset($lembaga['harga_beras']) ? (float)$lembaga['harga_beras'] : 15000; ?>;

    function updateJumlahBayar() {
        const tanggungan = parseFloat(jumlahTanggunganDibayarInput.value) || 1;
        if (jenisBerasRadio.checked) {
            jumlahBayarInput.value = (tanggungan * 2.5).toFixed(2);
        } else {
            jumlahBayarInput.value = Math.round(tanggungan * 2.5 * hargaBeras);
        }
    }

    // Update otomatis saat nama KK dipilih
    if (namaKKSelect) {
        namaKKSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tanggungan = selectedOption.getAttribute('data-tanggungan');
            if (tanggungan) {
                jumlahTanggunganInput.value = tanggungan;
                jumlahTanggunganDibayarInput.value = tanggungan;
                updateJumlahBayar();
            }
        });
    }

    // Update otomatis saat jumlah tanggungan diubah
    if (jumlahTanggunganDibayarInput) {
        jumlahTanggunganDibayarInput.addEventListener('input', updateJumlahBayar);
    }

    // Update otomatis saat jenis pembayaran diubah
    if (jenisBerasRadio) jenisBerasRadio.addEventListener('change', updateJumlahBayar);
    if (jenisUangRadio) jenisUangRadio.addEventListener('change', updateJumlahBayar);

    // Inisialisasi awal
    updateJumlahBayar();
});
</script>

<?php
// Ambil konten yang telah di-buffer dan simpan ke dalam variabel $content
$content = ob_get_clean();

// Sertakan layout.php untuk menampilkan template dengan $content yang telah diisi
include_once 'templates/layout.php';
?>