<?php
// Include common layout header
$page_title = 'Pengumpulan Zakat';
$current_page = 'bayar_zakat';
$base_path = '../../';

// Definisikan tambahan CSS jika diperlukan
$additional_css = '';

// Definisikan tambahan JS
ob_start();
?>
<script>
// Script khusus untuk halaman Pengumpulan Zakat
document.addEventListener('DOMContentLoaded', function() {
    // Kode JavaScript untuk halaman ini
    console.log('Halaman Pengumpulan Zakat dimuat');
    
    // Sisipkan script khusus di sini
});
</script>
<?php
include __DIR__ . '/bayar_zakat_scripts.php';
$additional_js = ob_get_clean();

// Definisikan konten utama
ob_start(); // Mulai output buffering
?>
<!-- Content -->
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

<!-- Payment List -->
<?php include __DIR__ . '/bayar_zakat_payment_list.php'; ?>

<!-- Modals -->
<?php include __DIR__ . '/bayar_zakat_modals.php'; ?>

<?php
// Simpan konten utama ke dalam variabel $content
$content = ob_get_clean();

// Sertakan file layout (ini akan menggunakan $content yang sudah diatur)
require_once __DIR__ . '/../../views/templates/layout.php';
?> 