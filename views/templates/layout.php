<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan functions.php diinclude
require_once __DIR__ . '/../../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistem Manajemen Zakat Fitrah'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo $base_path; ?>assets/css/custom.css" rel="stylesheet">
    <?php if (isset($additional_css)) echo $additional_css; ?>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    <?php if ($current_page === 'tasks'): ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/tasks.css">
    <?php endif; ?>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
                    <h1 class="h3 mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                </div>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Guest'); ?></span>
                    <span class="badge bg-primary ms-2"><?php echo ucfirst($_SESSION['user']['role'] ?? 'Guest'); ?></span>
                </div>
            </div>

            <!-- Content -->
            <?php echo $content; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script src="<?php echo $base_path; ?>assets/js/layout-fix.js"></script>
    <!-- Main Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_path; ?>assets/js/custom.js"></script>
    <?php if ($current_page === 'tasks'): ?>
    <script src="<?php echo $base_path; ?>assets/js/tasks.js"></script>
    <?php endif; ?>
    <!-- Additional JS specific to this page -->
    <?php if (isset($additional_js)): ?>
    <?php echo $additional_js; ?>
    <?php endif; ?>
</body>
</html> 