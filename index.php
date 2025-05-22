<?php
// Include initialization file for timezone and other app settings
require_once 'config/init.php';

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set base path for assets and links
$base_path = './';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: views/login.php');
    exit;
}

$userRole = $auth->getUserRole();

// Set current page for sidebar
$current_page = 'dashboard';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get total muzakki
$stmt = $db->query("SELECT COUNT(*) as total_muzakki FROM muzakki");
$total_muzakki = $stmt->fetch(PDO::FETCH_ASSOC)['total_muzakki'];

// Get total beras and uang
$stmt = $db->query("SELECT SUM(bayar_beras) as total_beras, SUM(bayar_uang) as total_uang FROM bayarzakat");
$total_bayar = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate target achievement (assuming target is 100 muzakki)
$target_muzakki = 100;
$achievement = ($total_muzakki / $target_muzakki) * 100;
$achievement = min($achievement, 100); // Cap at 100%

// Get recent payment activities
$stmt = $db->query("SELECT nama_KK, jenis_bayar, bayar_beras, bayar_uang, created_at 
                    FROM bayarzakat 
                    ORDER BY created_at DESC 
                    LIMIT 5");
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent distribution activities
$stmt = $db->query("SELECT m.nama, m.kategori, m.hak, m.created_at
                    FROM (
                        SELECT nama, kategori, hak, created_at
                        FROM mustahik_warga
                        UNION ALL
                        SELECT nama, kategori, hak, created_at
                        FROM mustahik_lainnya
                    ) as m
                    ORDER BY m.created_at DESC
                    LIMIT 5");
$recent_distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total zakat collected
$stmt = $db->query("SELECT SUM(bayar_beras) as total_beras FROM bayarzakat");
$total_zakat = $stmt->fetch(PDO::FETCH_ASSOC)['total_beras'] ?? 0;

// Get total distributed zakat
$stmt = $db->query("SELECT SUM(hak) as total_distributed FROM mustahik_warga");
$total_distributed_warga = $stmt->fetch(PDO::FETCH_ASSOC)['total_distributed'] ?? 0;

$stmt = $db->query("SELECT SUM(hak) as total_distributed FROM mustahik_lainnya");
$total_distributed_lainnya = $stmt->fetch(PDO::FETCH_ASSOC)['total_distributed'] ?? 0;

$total_distributed = $total_distributed_warga + $total_distributed_lainnya;
$sisa_zakat = $total_zakat - $total_distributed;

// Get total muzakki for progress calculation
$stmt = $db->query("SELECT COUNT(*) as total FROM muzakki");
$total_muzakki = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total muzakki who have paid
$stmt = $db->query("SELECT COUNT(DISTINCT nama_KK) as total_paid FROM bayarzakat");
$total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'];

// Get dokumentasi data
$stmt = $db->query("SELECT 
                    COUNT(*) as total_mustahik,
                    SUM(CASE WHEN status = 'sudah_diterima' THEN 1 ELSE 0 END) as sudah_didokumentasi,
                    SUM(CASE WHEN status = 'belum_diterima' THEN 1 ELSE 0 END) as belum_didokumentasi,
                    SUM(CASE WHEN status = 'bermasalah' THEN 1 ELSE 0 END) as bermasalah
                    FROM distribusi_dokumentasi");
$dokumentasi_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get kategori mustahik
$stmt = $db->query("SELECT nama_kategori as kategori, COUNT(*) as jumlah FROM kategori_mustahik GROUP BY nama_kategori");
$kategori_mustahik = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate real progress percentage
$progress_percentage = $total_muzakki > 0 ? ($total_paid / $total_muzakki) * 100 : 0;

// Function to format time difference
function time_elapsed_string($datetime) {
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Jakarta'));
    
    // If datetime is in timestamp format, convert it to DateTime object
    if (is_numeric($datetime)) {
        $ago = new DateTime();
        $ago->setTimestamp($datetime);
        $ago->setTimezone(new DateTimeZone('Asia/Jakarta'));
    }
    
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) {
        return $diff->y . ' tahun yang lalu';
    } elseif ($diff->m > 0) {
        return $diff->m . ' bulan yang lalu';
    } elseif ($diff->d > 0) {
        if ($diff->d == 1) {
            return 'Kemarin';
        } else {
            return $diff->d . ' hari yang lalu';
        }
    } elseif ($diff->h > 0) {
        return $diff->h . ' jam yang lalu';
    } elseif ($diff->i > 0) {
        return $diff->i . ' menit yang lalu';
    } else {
        return 'Baru saja';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Manajemen Zakat Fitrah</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        .dashboard-stats {
            background: linear-gradient(135deg, var(--zakat-primary) 0%, var(--zakat-primary-dark) 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .dashboard-stats:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
            margin-bottom: 15px;
        }
        
        .progress-wrapper {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .activity-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.3s ease;
        }
        
        .activity-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .chart-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .distribution-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .quick-action-btn {
            padding: 15px;
            border-radius: 10px;
            border: none;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .quick-action-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php echo getStandardSidebar($current_page, $base_path); ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Dashboard</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item active">Overview</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="window.location.href='views/tasks.php'">
                        <i class="bi bi-list-check"></i> Manajemen Tugas
                    </button>
                </div>
            </div>

            <!-- Access Denied Alert (if exists) -->
            <?php if (isset($_SESSION['access_denied_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                <strong>Perhatian!</strong> <?php echo htmlspecialchars($_SESSION['access_denied_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="<?php unset($_SESSION['access_denied_message']); ?>"></button>
            </div>
            <?php unset($_SESSION['access_denied_message']); ?>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <button class="quick-action-btn" onclick="window.location.href='views/bayar_zakat.php'">
                    <div class="quick-action-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">Bayar Zakat</h6>
                        <small class="text-muted">Catat pembayaran baru</small>
                    </div>
                </button>
                
                <button class="quick-action-btn" onclick="window.location.href='views/distribusi.php'">
                    <div class="quick-action-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">Distribusi</h6>
                        <small class="text-muted">Atur distribusi zakat</small>
                    </div>
                </button>
                
                <button class="quick-action-btn" onclick="window.location.href='views/muzakki.php'">
                    <div class="quick-action-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">Data Muzakki</h6>
                        <small class="text-muted">Kelola data muzakki</small>
                    </div>
                </button>
                
                <button class="quick-action-btn" onclick="window.location.href='views/laporan.php'">
                    <div class="quick-action-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-file-text"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">Laporan</h6>
                        <small class="text-muted">Lihat laporan lengkap</small>
                    </div>
                </button>
                
                <button class="quick-action-btn" onclick="window.location.href='views/tasks.php'">
                    <div class="quick-action-icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-list-check"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">Manajemen Tugas</h6>
                        <small class="text-muted">Kelola tugas dan kegiatan</small>
                    </div>
                </button>
                
                <button class="quick-action-btn" onclick="window.location.href='views/distribusi_dokumentasi.php'">
                    <div class="quick-action-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-file-earmark-image"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">Dokumentasi</h6>
                        <small class="text-muted">Kelola dokumentasi distribusi</small>
                    </div>
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="dashboard-stats">
                        <i class="bi bi-people stats-icon"></i>
                        <h3 class="mb-1"><?php echo number_format($total_muzakki); ?></h3>
                        <p class="mb-0">Total Muzakki</p>
                        <div class="progress-wrapper">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Progress</small>
                                <small><?php echo round($progress_percentage); ?>%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $progress_percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="dashboard-stats" style="background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);">
                        <i class="bi bi-box-seam stats-icon"></i>
                        <h3 class="mb-1"><?php echo number_format($total_bayar['total_beras'] ?? 0, 1); ?> kg</h3>
                        <p class="mb-0">Total Beras Terkumpul</p>
                        <div class="progress-wrapper">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Target: <?php echo number_format($target_muzakki); ?> kg</small>
                                <small><?php echo round($achievement); ?>%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $achievement; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="dashboard-stats" style="background: linear-gradient(135deg, #1976D2 0%, #0D47A1 100%);">
                        <i class="bi bi-cash stats-icon"></i>
                        <h3 class="mb-1">Rp <?php echo number_format($total_bayar['total_uang'] ?? 0, 0, ',', '.'); ?></h3>
                        <p class="mb-0">Total Uang Terkumpul</p>
                        <div class="progress-wrapper">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Distribusi</small>
                                <small><?php echo number_format($total_distributed_warga + $total_distributed_lainnya, 1); ?> kg</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo ($total_distributed / $total_zakat) * 100; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="dashboard-stats" style="background: linear-gradient(135deg, #F57C00 0%, #E65100 100%);">
                        <i class="bi bi-graph-up stats-icon"></i>
                        <h3 class="mb-1"><?php echo number_format($sisa_zakat, 1); ?> kg</h3>
                        <p class="mb-0">Sisa Zakat</p>
                        <div class="progress-wrapper">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Status</small>
                                <small><?php echo round(($sisa_zakat / $total_zakat) * 100); ?>%</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo ($sisa_zakat / $total_zakat) * 100; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Activities -->
                <div class="col-md-6 mb-4">
                    <div class="card activity-card h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Aktivitas Terbaru</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($recent_payments as $payment): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($payment['nama_KK']); ?></h6>
                                        <p class="mb-0 text-muted">
                                            <?php if ($payment['jenis_bayar'] == 'beras'): ?>
                                                Membayar zakat <?php echo number_format($payment['bayar_beras'], 2); ?> kg beras
                                            <?php else: ?>
                                                Membayar zakat Rp <?php echo number_format($payment['bayar_uang'], 0, ',', '.'); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <small class="text-muted"><?php echo time_elapsed_string($payment['created_at']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Distribution Schedule -->
                <div class="col-md-6 mb-4">
                    <div class="card activity-card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Jadwal Distribusi</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.location.href='views/distribusi.php'">
                                Lihat Semua
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            $stmt = $db->query("SELECT ds.*, m.nama as mustahik_name 
                                              FROM distributions_schedule ds 
                                              JOIN mustahik_warga m ON ds.mustahik_id = m.id_mustahikwarga 
                                              WHERE ds.status = 'scheduled' 
                                              ORDER BY ds.scheduled_date ASC LIMIT 5");
                            $distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($distributions as $dist):
                            ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($dist['mustahik_name']); ?></h6>
                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($dist['notes']); ?></p>
                                    </div>
                                    <span class="distribution-status <?php echo $dist['status'] == 'pending' ? 'status-pending' : 'status-completed'; ?>">
                                        <?php echo ucfirst($dist['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kategori Mustahik Card -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card chart-card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Kategori Mustahik</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.location.href='views/kategori_mustahik.php'">
                                <i class="bi bi-arrow-right"></i> Kelola Kategori
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <?php if (empty($kategori_mustahik)): ?>
                                <div class="col-12 text-center py-5">
                                    <i class="bi bi-tags display-4 text-muted"></i>
                                    <p class="mt-3 text-muted">Belum ada kategori mustahik yang ditambahkan</p>
                                    <a href="views/kategori_mustahik.php" class="btn btn-sm btn-primary">Tambah Kategori</a>
                                </div>
                                <?php else: ?>
                                    <?php foreach ($kategori_mustahik as $km): 
                                        $color_class = '';
                                        switch (strtolower($km['kategori'])) {
                                            case 'fakir':
                                                $color_class = 'danger';
                                                $icon = 'bi-heart';
                                                break;
                                            case 'miskin':
                                                $color_class = 'warning';
                                                $icon = 'bi-house';
                                                break;
                                            case 'amil':
                                            case 'amilin':
                                                $color_class = 'primary';
                                                $icon = 'bi-briefcase';
                                                break;
                                            case 'fisabilillah':
                                                $color_class = 'success';
                                                $icon = 'bi-book';
                                                break;
                                            default:
                                                $color_class = 'info';
                                                $icon = 'bi-people';
                                        }
                                    ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="d-flex align-items-center p-3 border rounded bg-white">
                                            <div class="text-<?php echo $color_class; ?> fs-3 me-3">
                                                <i class="bi <?php echo $icon; ?>"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars(ucfirst($km['kategori'])); ?></h6>
                                                <div class="text-muted small"><?php echo number_format($km['jumlah']); ?> orang</div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card chart-card h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Dokumentasi Distribusi</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <h6 class="mb-3">Berdasarkan Status</h6>
                                    <div class="chart-container" style="position: relative; height:200px;">
                                        <canvas id="documentationChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="bi bi-info-circle fs-3"></i>
                                            </div>
                                            <div>
                                                <h6 class="alert-heading">Dokumentasi Penting!</h6>
                                                <p class="mb-0">Dokumentasi distribusi zakat merupakan bagian penting dari akuntabilitas dan transparansi pengelolaan zakat.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dokumentasi Distribusi Status -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card chart-card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Status Dokumentasi Distribusi</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.location.href='views/distribusi_dokumentasi.php'">
                                <i class="bi bi-arrow-right"></i> Lihat Semua
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="p-3 rounded-circle me-3" style="background-color: rgba(25, 135, 84, 0.1);">
                                            <i class="bi bi-check-circle-fill text-success fs-3"></i>
                                        </div>
                                        <div>
                                            <div class="h4 mb-0"><?php echo number_format($dokumentasi_stats['sudah_didokumentasi'] ?? 0); ?></div>
                                            <div class="text-muted">Sudah Diterima</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="p-3 rounded-circle me-3" style="background-color: rgba(255, 193, 7, 0.1);">
                                            <i class="bi bi-hourglass-split text-warning fs-3"></i>
                                        </div>
                                        <div>
                                            <div class="h4 mb-0"><?php echo number_format($dokumentasi_stats['belum_didokumentasi'] ?? 0); ?></div>
                                            <div class="text-muted">Belum Diterima</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="p-3 rounded-circle me-3" style="background-color: rgba(220, 53, 69, 0.1);">
                                            <i class="bi bi-exclamation-triangle-fill text-danger fs-3"></i>
                                        </div>
                                        <div>
                                            <div class="h4 mb-0"><?php echo number_format($dokumentasi_stats['bermasalah'] ?? 0); ?></div>
                                            <div class="text-muted">Bermasalah</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="pt-4">
                                        <?php
                                        $total_docs = ($dokumentasi_stats['total_mustahik'] ?? 0);
                                        
                                        // Handle case when no data exists to avoid division by zero
                                        if ($total_docs > 0) {
                                            $sudah_persen = ($dokumentasi_stats['sudah_didokumentasi'] / $total_docs) * 100;
                                            $belum_persen = ($dokumentasi_stats['belum_didokumentasi'] / $total_docs) * 100;
                                            $masalah_persen = ($dokumentasi_stats['bermasalah'] / $total_docs) * 100;
                                        } else {
                                            $sudah_persen = $belum_persen = $masalah_persen = 0;
                                        }
                                        ?>
                                        <h5 class="mb-3">Persentase Dokumentasi</h5>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Sudah Diterima</small>
                                                <small><?php echo round($sudah_persen); ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $sudah_persen; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Belum Diterima</small>
                                                <small><?php echo round($belum_persen); ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $belum_persen; ?>%"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Bermasalah</small>
                                                <small><?php echo round($masalah_persen); ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $masalah_persen; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribution Details -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card chart-card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Distribusi per Kategori</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Jumlah Orang</th>
                                            <th>Total Hak (kg)</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $db->query("SELECT kategori, COUNT(*) as jumlah, SUM(hak) as total_hak FROM mustahik_warga GROUP BY kategori");
                                        $distribusi_warga = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Get data from mustahik_lainnya too
                                        $stmt = $db->query("SELECT kategori, COUNT(*) as jumlah, SUM(hak) as total_hak FROM mustahik_lainnya GROUP BY kategori");
                                        $distribusi_lainnya = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Combine both arrays
                                        $all_distribusi = array_merge($distribusi_warga, $distribusi_lainnya);
                                        
                                        foreach ($all_distribusi as $dw):
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="category-icon me-2">
                                                        <?php if (strtolower($dw['kategori']) === 'fakir'): ?>
                                                            <i class="bi bi-people text-danger"></i>
                                                        <?php elseif (strtolower($dw['kategori']) === 'miskin'): ?>
                                                            <i class="bi bi-people text-warning"></i>
                                                        <?php elseif (strtolower($dw['kategori']) === 'amilin'): ?>
                                                            <i class="bi bi-people text-primary"></i>
                                                        <?php elseif (strtolower($dw['kategori']) === 'fisabilillah'): ?>
                                                            <i class="bi bi-people text-success"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-people text-info"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php echo ucfirst($dw['kategori']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo number_format($dw['jumlah']); ?></td>
                                            <td><?php echo number_format($dw['total_hak'] ?? 0, 2); ?></td>
                                            <td>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo ($dw['total_hak'] / $total_zakat) * 100; ?>%"></div>
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
            </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Tugas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="api/tasks.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Judul Tugas</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal Jatuh Tempo</label>
                            <input type="date" class="form-control" name="due_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prioritas</label>
                            <select class="form-select" name="priority">
                                <option value="low">Rendah</option>
                                <option value="medium">Sedang</option>
                                <option value="high">Tinggi</option>
                            </select>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/layout-fix.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/custom.js"></script>
    <script>
        // Dokumentasi Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('documentationChart').getContext('2d');
            
            const sudahDidokumentasi = <?php echo (int)($dokumentasi_stats['sudah_didokumentasi'] ?? 0); ?>;
            const belumDidokumentasi = <?php echo (int)($dokumentasi_stats['belum_didokumentasi'] ?? 0); ?>;
            const bermasalah = <?php echo (int)($dokumentasi_stats['bermasalah'] ?? 0); ?>;
            
            // Create chart only if we have data
            if (sudahDidokumentasi > 0 || belumDidokumentasi > 0 || bermasalah > 0) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Sudah Diterima', 'Belum Diterima', 'Bermasalah'],
                        datasets: [{
                            data: [sudahDidokumentasi, belumDidokumentasi, bermasalah],
                            backgroundColor: [
                                'rgba(25, 135, 84, 0.7)',
                                'rgba(255, 193, 7, 0.7)',
                                'rgba(220, 53, 69, 0.7)'
                            ],
                            borderColor: [
                                'rgba(25, 135, 84, 1)',
                                'rgba(255, 193, 7, 1)',
                                'rgba(220, 53, 69, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            } else {
                // Display a message if no data is available
                const container = document.querySelector('.chart-container');
                container.innerHTML = '<div class="text-center py-5"><p class="text-muted">Tidak ada data dokumentasi distribusi</p></div>';
            }
        });
    </script>
</body>
</html> 