<?php
require_once '../config/auth.php';
require_once '../config/koneksi.php';

$auth = new Auth();
// Ensure only admin can access this page
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: login.php?error=Akses ditolak! Hanya admin yang dapat mengakses halaman ini.');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get current timezone settings
$stmt = $db->query("SELECT timezone FROM konfigurasi WHERE id = 1");
$current_timezone = $stmt->fetch(PDO::FETCH_ASSOC)['timezone'] ?? 'Asia/Jakarta';

// Get server time and current timezone
$server_time = new DateTime();
$php_timezone = date_default_timezone_get();

// Get MySQL server timezone
$stmt = $db->query("SELECT @@system_time_zone as system_tz, @@time_zone as session_tz");
$mysql_timezone = $stmt->fetch(PDO::FETCH_ASSOC);

// Get Windows system timezone
exec('powershell -command "[System.TimeZoneInfo]::Local.Id"', $windows_timezone);
$windows_tz = !empty($windows_timezone[0]) ? $windows_timezone[0] : 'Unknown';

// Handle form submission to update timezone
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_timezone') {
        $new_timezone = $_POST['timezone'];
        
        try {
            // Update timezone in database
            $stmt = $db->prepare("UPDATE konfigurasi SET timezone = ? WHERE id = 1");
            $stmt->execute([$new_timezone]);
            
            // Set current PHP session timezone
            date_default_timezone_set($new_timezone);
            
            // Set MySQL timezone offset
            $dateTime = new DateTime("now", new DateTimeZone($new_timezone));
            $offset = $dateTime->format('P');
            $db->exec("SET time_zone = '$offset'");
            
            $message = "Zona waktu berhasil diperbarui ke $new_timezone";
            
            // Update variables for display
            $current_timezone = $new_timezone;
            $php_timezone = date_default_timezone_get();
            
            // Get updated MySQL timezone
            $stmt = $db->query("SELECT @@time_zone as session_tz");
            $mysql_timezone['session_tz'] = $stmt->fetch(PDO::FETCH_ASSOC)['session_tz'];
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'sync_with_system') {
        // Get system timezone via PHP
        exec('powershell -command "[System.TimeZoneInfo]::Local.Id"', $tzOutput);
        
        if (!empty($tzOutput[0])) {
            // Windows to PHP timezone mapping
            $windowsToPhpTimezones = [
                'Malay Peninsula Standard Time' => 'Asia/Kuala_Lumpur',
                'Singapore Standard Time' => 'Asia/Singapore',
                'China Standard Time' => 'Asia/Shanghai',
                'Tokyo Standard Time' => 'Asia/Tokyo',
                'Korea Standard Time' => 'Asia/Seoul',
                'SE Asia Standard Time' => 'Asia/Bangkok',
                'W. Europe Standard Time' => 'Europe/Berlin',
                'Romance Standard Time' => 'Europe/Paris',
                'GMT Standard Time' => 'Europe/London',
                'Central Europe Standard Time' => 'Europe/Budapest',
                'E. Europe Standard Time' => 'Europe/Minsk',
                'Russian Standard Time' => 'Europe/Moscow',
                'Eastern Standard Time' => 'America/New_York',
                'Central Standard Time' => 'America/Chicago',
                'Mountain Standard Time' => 'America/Denver',
                'Pacific Standard Time' => 'America/Los_Angeles',
                'AUS Eastern Standard Time' => 'Australia/Sydney',
                'Egypt Standard Time' => 'Africa/Cairo',
                'South Africa Standard Time' => 'Africa/Johannesburg',
                'India Standard Time' => 'Asia/Kolkata'
            ];
            
            if (isset($windowsToPhpTimezones[$tzOutput[0]])) {
                $system_tz = $windowsToPhpTimezones[$tzOutput[0]];
            } else {
                // Default to Asia/Jakarta if mapping not found
                $system_tz = 'Asia/Jakarta';
            }
            
            // Update timezone in database
            $stmt = $db->prepare("UPDATE konfigurasi SET timezone = ? WHERE id = 1");
            $stmt->execute([$system_tz]);
            
            // Set current PHP session timezone
            date_default_timezone_set($system_tz);
            
            // Set MySQL timezone offset
            $dateTime = new DateTime("now", new DateTimeZone($system_tz));
            $offset = $dateTime->format('P');
            $db->exec("SET time_zone = '$offset'");
            
            $message = "Zona waktu berhasil disinkronkan dengan sistem ($system_tz)";
            
            // Update variables for display
            $current_timezone = $system_tz;
            $php_timezone = date_default_timezone_get();
            
            // Get updated MySQL timezone
            $stmt = $db->query("SELECT @@time_zone as session_tz");
            $mysql_timezone['session_tz'] = $stmt->fetch(PDO::FETCH_ASSOC)['session_tz'];
        } else {
            $message = "Tidak dapat mendeteksi zona waktu sistem.";
        }
    }
}

// Available timezones (common ones)
$timezones = [
    'Asia/Jakarta' => 'Asia/Jakarta (WIB)',
    'Asia/Makassar' => 'Asia/Makassar (WITA)',
    'Asia/Jayapura' => 'Asia/Jayapura (WIT)',
    'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur',
    'Asia/Singapore' => 'Asia/Singapore',
    'Asia/Bangkok' => 'Asia/Bangkok',
    'Asia/Shanghai' => 'Asia/Shanghai',
    'Asia/Tokyo' => 'Asia/Tokyo',
    'Europe/London' => 'Europe/London (GMT)',
    'Europe/Paris' => 'Europe/Paris',
    'Europe/Berlin' => 'Europe/Berlin',
    'America/New_York' => 'America/New_York',
    'America/Chicago' => 'America/Chicago',
    'America/Los_Angeles' => 'America/Los_Angeles',
    'Australia/Sydney' => 'Australia/Sydney'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Zona Waktu - Sistem Manajemen Zakat Fitrah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <style>
        .sidebar {
            background-color: #2E7D32;
            min-height: 100vh;
            color: white;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: white;
            padding: 10px 20px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        .timezone-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky">
                    <div class="text-center p-3 mb-3">
                        <h5>Sistem Manajemen Zakat Fitrah</h5>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="muzakki.php">
                                <i class="bi bi-people"></i> Data Muzakki
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bayar_zakat.php">
                                <i class="bi bi-cash-stack"></i> Bayar Zakat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="distribusi.php">
                                <i class="bi bi-box-seam"></i> Distribusi Zakat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="laporan.php">
                                <i class="bi bi-file-earmark-text"></i> Laporan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="pengaturan.php">
                                <i class="bi bi-gear"></i> Pengaturan
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pengaturan Zona Waktu</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="pengaturan.php">Pengaturan</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Zona Waktu</li>
                        </ol>
                    </nav>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Current Time Information -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">Informasi Waktu Saat Ini</h5>
                            </div>
                            <div class="card-body">
                                <div class="timezone-info">
                                    <h5 class="mb-3">Detail Zona Waktu</h5>
                                    <p><strong>Zona Waktu Aplikasi:</strong> <?php echo $current_timezone; ?></p>
                                    <p><strong>Zona Waktu PHP:</strong> <?php echo $php_timezone; ?></p>
                                    <p><strong>Zona Waktu MySQL:</strong> <?php echo $mysql_timezone['system_tz']; ?> (System), <?php echo $mysql_timezone['session_tz']; ?> (Session)</p>
                                    <p><strong>Zona Waktu Windows:</strong> <?php echo $windows_tz; ?></p>
                                </div>
                                
                                <div class="timezone-info">
                                    <h5 class="mb-3">Waktu Server</h5>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h3 id="current-time"><?php echo $server_time->format('H:i:s'); ?></h3>
                                        <h4 id="current-date"><?php echo $server_time->format('d M Y'); ?></h4>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="sync_with_system">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="bi bi-arrow-repeat"></i> Sinkronkan dengan Waktu Sistem
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change Timezone -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">Ubah Zona Waktu</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="update_timezone">
                                    
                                    <div class="mb-3">
                                        <label for="timezone" class="form-label">Pilih Zona Waktu</label>
                                        <select class="form-select" id="timezone" name="timezone">
                                            <?php foreach($timezones as $tz_value => $tz_label): ?>
                                                <option value="<?php echo $tz_value; ?>" <?php echo ($current_timezone === $tz_value) ? 'selected' : ''; ?>>
                                                    <?php echo $tz_label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="mb-1"><strong>Penjelasan:</strong></p>
                                        <p class="small text-muted">
                                            Pengaturan zona waktu ini memengaruhi tampilan waktu di seluruh aplikasi, 
                                            termasuk waktu pembayaran zakat, distribusi, dan laporan. Pastikan zona 
                                            waktu yang dipilih sesuai dengan lokasi Anda untuk memastikan semua 
                                            data memiliki stempel waktu yang akurat.
                                        </p>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-check-circle"></i> Simpan Pengaturan Zona Waktu
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
            document.getElementById('current-date').textContent = now.toLocaleDateString('id-ID', { 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric' 
            });
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html> 