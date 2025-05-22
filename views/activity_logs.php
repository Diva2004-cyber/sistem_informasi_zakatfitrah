<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';
require_once '../includes/functions.php';

// Define base path for assets and links
$base_path = '../';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAdmin(); // Only admin can access activity logs

// Initialize database and logger
$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger();

// Set current page for sidebar
$current_page = 'activity_logs';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;

// Filters
$filters = [];
if (isset($_GET['username']) && !empty($_GET['username'])) {
    $filters['username'] = $_GET['username'];
}
if (isset($_GET['action']) && !empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}
if (isset($_GET['module']) && !empty($_GET['module'])) {
    $filters['module'] = $_GET['module'];
}
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

// Get logs with pagination
$result = $logger->getLogs($page, $perPage, $filters);
$logs = $result['logs'];
$pagination = $result['pagination'];

// Get unique modules for filter dropdown
$stmt = $db->query("SELECT DISTINCT module FROM activity_logs ORDER BY module");
$modules = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique actions for filter dropdown
$stmt = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Prepare content for template
ob_start();
?>

<!-- Content -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Filter Log</h5>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="bi bi-funnel"></i> Filter
        </button>
    </div>
    <div class="collapse" id="filterCollapse">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" 
                           value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Action</label>
                    <select class="form-select" name="action">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?php echo $action; ?>" 
                                <?php echo (isset($_GET['action']) && $_GET['action'] == $action) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($action); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Module</label>
                    <select class="form-select" name="module">
                        <option value="">Semua Modul</option>
                        <?php foreach ($modules as $module): ?>
                        <option value="<?php echo $module; ?>" 
                                <?php echo (isset($_GET['module']) && $_GET['module'] == $module) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($module); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                </div>
                <div class="col-md-4 align-self-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <a href="activity_logs.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada data log aktivitas.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d M Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo ucfirst($log['action']); ?></td>
                            <td><?php echo ucfirst($log['module']); ?></td>
                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                            <td><?php echo $log['ip_address']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['last_page'] > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($pagination['current_page'] > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" aria-label="First">
                        <span aria-hidden="true">&laquo;&laquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?><?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])); ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php
                $start = max(1, $pagination['current_page'] - 2);
                $end = min($pagination['last_page'], $pagination['current_page'] + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?><?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])); ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $pagination['last_page']; ?><?php echo http_build_query(array_merge($_GET, ['page' => $pagination['last_page']])); ?>" aria-label="Last">
                        <span aria-hidden="true">&raquo;&raquo;</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

// Additional JavaScript
$additional_js = <<<JS
<script>
// Initialize collapse state from localStorage
document.addEventListener('DOMContentLoaded', function() {
    const filterCollapse = document.getElementById('filterCollapse');
    const showFilter = localStorage.getItem('showActivityLogFilter') === 'true';
    
    if (showFilter) {
        filterCollapse.classList.add('show');
    }
    
    filterCollapse.addEventListener('shown.bs.collapse', function () {
        localStorage.setItem('showActivityLogFilter', 'true');
    });
    
    filterCollapse.addEventListener('hidden.bs.collapse', function () {
        localStorage.setItem('showActivityLogFilter', 'false');
    });
});
</script>
JS;

// Set template variables
$page_title = 'Activity Logs';
$current_page = 'activity_logs';
$base_path = '../';

// Include template
include '../views/templates/layout.php'; 