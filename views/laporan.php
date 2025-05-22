<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';
require_once '../includes/functions.php';
require_once '../helpers/report_helper.php';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAuth();

// Initialize database and report helper
$database = new Database();
$db = $database->getConnection();
$reportHelper = new ReportHelper($db);

// Get report data
$lembaga = $reportHelper->getLembagaInfo();
$collection = $reportHelper->getZakatSummary(date('Y-m-d'), date('Y-m-d'));
$distribution = $reportHelper->getDistributionByCategory(date('Y-m-d'), date('Y-m-d'));

// Prepare content for template
$content = '';
ob_start();

// Define base path for assets and links
$base_path = '../';

// Set current page for sidebar
$current_page = 'laporan';
?>

<!-- Laporan Pengumpulan Zakat -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Laporan Pengumpulan Zakat</h5>
        <div>
            <button class="btn btn-success btn-sm" onclick="exportToPDF('collection')">
                <i class="bi bi-file-pdf"></i> Export PDF
            </button>
            <button class="btn btn-primary btn-sm" onclick="exportToWord('collection')">
                <i class="bi bi-file-word"></i> Export Word
            </button>
        </div>
    </div>
    <div class="card-body" id="collection-report">
        <div class="text-center mb-4">
            <h4><?php echo htmlspecialchars($lembaga['nama_lembaga'] ?? 'Lembaga Zakat'); ?></h4>
            <p class="mb-0"><?php echo htmlspecialchars($lembaga['alamat'] ?? ''); ?></p>
            <p>Telp: <?php echo htmlspecialchars($lembaga['telepon'] ?? ''); ?></p>
            <h5>LAPORAN PENGUMPULAN ZAKAT FITRAH</h5>
        </div>
        <table class="table table-bordered">
            <tr>
                <td>Total Muzakki</td>
                <td><?php echo number_format($collection['total_muzakki']); ?> orang</td>
            </tr>
            <tr>
                <td>Total Jiwa</td>
                <td><?php echo number_format($collection['total_jiwa']); ?> jiwa</td>
            </tr>
            <tr>
                <td>Total Beras</td>
                <td><?php echo number_format($collection['total_beras'], 2); ?> kg</td>
            </tr>
            <tr>
                <td>Total Uang</td>
                <td>Rp <?php echo number_format($collection['total_uang'], 0, ',', '.'); ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- Laporan Distribusi Zakat -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Laporan Distribusi Zakat Fitrah</h5>
        <div>
            <button class="btn btn-success btn-sm" onclick="exportToPDF('distribution')">
                <i class="bi bi-file-pdf"></i> Export PDF
            </button>
            <button class="btn btn-primary btn-sm" onclick="exportToWord('distribution')">
                <i class="bi bi-file-word"></i> Export Word
            </button>
        </div>
    </div>
    <div class="card-body" id="distribution-report">
        <div class="text-center mb-4">
            <h4><?php echo htmlspecialchars($lembaga['nama_lembaga'] ?? 'Lembaga Zakat'); ?></h4>
            <p class="mb-0"><?php echo htmlspecialchars($lembaga['alamat'] ?? ''); ?></p>
            <p>Telp: <?php echo htmlspecialchars($lembaga['telepon'] ?? ''); ?></p>
            <h5>LAPORAN DISTRIBUSI ZAKAT FITRAH</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Kategori Mustahik</th>
                        <th>Jumlah KK</th>
                        <th>Total Beras (kg)</th>
                        <th>Total Uang (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distribution as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['kategori']); ?></td>
                        <td><?php echo number_format($row['jumlah']); ?></td>
                        <td><?php echo number_format($row['total_hak'], 2); ?></td>
                        <td>Rp <?php echo number_format($row['total_uang'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Additional JavaScript for export functionality
$additional_js = <<<JS
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function exportToPDF(section) {
        const element = document.getElementById(section + '-report');
        const opt = {
            margin: 1,
            filename: 'laporan_' + section + '.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }

    function exportToWord(section) {
        const element = document.getElementById(section + '-report');
        const html = element.innerHTML;
        const blob = new Blob(['\ufeff', html], {
            type: 'application/msword'
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'laporan_' + section + '.doc';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
JS;

// Set template variables
$page_title = 'Laporan';

// Include template
include '../views/templates/layout.php'; 