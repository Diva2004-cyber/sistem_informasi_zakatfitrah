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

$messages = [];

try {
    // Check if timezone column exists
    $stmt = $db->query("SHOW COLUMNS FROM konfigurasi LIKE 'timezone'");
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, add it
        $db->exec("ALTER TABLE konfigurasi ADD COLUMN timezone VARCHAR(100) DEFAULT 'Asia/Jakarta' AFTER harga_beras");
        $messages[] = "Added timezone column to konfigurasi table";
    } else {
        $messages[] = "Timezone column already exists";
    }
    
    // Update the timezone in the configuration table
    $stmt = $db->prepare("UPDATE konfigurasi SET timezone = ? WHERE id = 1");
    $stmt->execute(['Asia/Jakarta']);
    $messages[] = "Updated timezone to 'Asia/Jakarta'";
    
    // Set MySQL session timezone
    $db->exec("SET time_zone = '+07:00'");
    $messages[] = "Set MySQL session timezone to '+07:00'";
    
    $messages[] = "Database update completed successfully!";
    
} catch (PDOException $e) {
    $messages[] = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update - Sistem Manajemen Zakat Fitrah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Database Update Results</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5>SQL Execution Results:</h5>
                            <ul>
                                <?php foreach ($messages as $message): ?>
                                    <li><?php echo htmlspecialchars($message); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <a href="pengaturan.php" class="btn btn-primary">Return to Settings</a>
                            <a href="timezone_settings.php" class="btn btn-success ms-2">Go to Timezone Settings</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 