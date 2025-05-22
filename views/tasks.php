<?php
require_once '../middleware/auth_middleware.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';
require_once '../includes/functions.php';

// Define base path for assets and links
$base_path = '../';

// Set current page for sidebar
$current_page = 'tasks';

// Initialize auth middleware
$auth = new AuthMiddleware();
$auth->requireAuth(); // All authenticated users can access tasks

// Initialize database and logger
$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger($db, $auth);
$user_id = $auth->getUserId();
$is_admin = $auth->isAdmin();

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = $_POST['due_date'] ?? date('Y-m-d');
        $priority = $_POST['priority'] ?? 'medium';
        $assign_to = $is_admin && isset($_POST['assign_to']) ? $_POST['assign_to'] : $user_id;
        
        try {
            $stmt = $db->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$assign_to, $title, $description, $due_date, $priority]);
            
            $task_id = $db->lastInsertId();
            $logger->log('create', 'tasks', "Membuat tugas baru: $title", $task_id);
            
            // Create notification for assigned user
            if ($assign_to != $user_id) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, type, message, status) 
                                      VALUES (?, 'task', ?, 'unread')");
                $stmt->execute([$assign_to, "Anda mendapat tugas baru: $title"]);
            }
            
            $success = "Tugas berhasil dibuat";
        } catch (Exception $e) {
            $error = "Gagal membuat tugas: " . $e->getMessage();
        }
    } 
    elseif ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = $_POST['due_date'] ?? date('Y-m-d');
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'pending';
        $assign_to = $is_admin && isset($_POST['assign_to']) ? $_POST['assign_to'] : null;
        
        try {
            // Check if user has permission to update this task
            $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                throw new Exception("Tugas tidak ditemukan");
            }
            
            // Only task owner or admin can update
            if ($task['user_id'] != $user_id && !$is_admin) {
                throw new Exception("Anda tidak memiliki akses untuk mengubah tugas ini");
            }
            
            // Prepare query based on whether we're changing the assignee
            if ($assign_to !== null) {
                $stmt = $db->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, 
                                     priority = ?, status = ?, user_id = ?, updated_at = NOW() 
                                     WHERE id = ?");
                $stmt->execute([$title, $description, $due_date, $priority, $status, $assign_to, $id]);
                
                // Notify the new assignee
                if ($assign_to != $task['user_id']) {
                    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, message, status) 
                                          VALUES (?, 'task', ?, 'unread')");
                    $stmt->execute([$assign_to, "Anda ditetapkan ke tugas: $title"]);
                }
            } else {
                $stmt = $db->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, 
                                     priority = ?, status = ?, updated_at = NOW() 
                                     WHERE id = ?");
                $stmt->execute([$title, $description, $due_date, $priority, $status, $id]);
            }
            
            $logger->log('update', 'tasks', "Mengubah tugas: $title", $id);
            $success = "Tugas berhasil diperbarui";
        } catch (Exception $e) {
            $error = "Gagal memperbarui tugas: " . $e->getMessage();
        }
    }
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        try {
            // Check if user has permission to delete this task
            $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                throw new Exception("Tugas tidak ditemukan");
            }
            
            // Only task owner or admin can delete
            if ($task['user_id'] != $user_id && !$is_admin) {
                throw new Exception("Anda tidak memiliki akses untuk menghapus tugas ini");
            }
            
            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            
            $logger->log('delete', 'tasks', "Menghapus tugas: " . $task['title'], $id);
            $success = "Tugas berhasil dihapus";
        } catch (Exception $e) {
            $error = "Gagal menghapus tugas: " . $e->getMessage();
        }
    }
    elseif ($action === 'update_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? 'pending';
        
        try {
            // Check if user has permission to update this task
            $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                throw new Exception("Tugas tidak ditemukan");
            }
            
            // Only task owner or admin can update status
            if ($task['user_id'] != $user_id && !$is_admin) {
                throw new Exception("Anda tidak memiliki akses untuk mengubah status tugas ini");
            }
            
            $stmt = $db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            $status_text = $status === 'completed' ? 'selesai' : ($status === 'in_progress' ? 'dalam proses' : 'pending');
            $logger->log('update', 'tasks', "Mengubah status tugas menjadi $status_text: " . $task['title'], $id);
            $success = "Status tugas berhasil diperbarui";
        } catch (Exception $e) {
            $error = "Gagal memperbarui status tugas: " . $e->getMessage();
        }
    }
}

// Get all users for assignment (admin only)
$users = [];
if ($is_admin) {
    $stmt = $db->query("SELECT id, username, nama_lengkap FROM users WHERE status = 'aktif' ORDER BY nama_lengkap");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get tasks with filters
$filter_status = $_GET['status'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT t.*, u.username, u.nama_lengkap FROM tasks t
         LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";

$params = [];

// Apply filters
if (!$is_admin) {
    // Regular users can only see their tasks
    $query .= " AND t.user_id = ?";
    $params[] = $user_id;
}

if ($filter_status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

if ($filter_priority !== 'all') {
    $query .= " AND t.priority = ?";
    $params[] = $filter_priority;
}

if (!empty($search)) {
    $query .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY t.due_date ASC, t.priority DESC, t.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug info - tambahkan ini untuk debugging
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<pre>";
    echo "Query: " . $query . "\n";
    echo "Params: " . print_r($params, true) . "\n";
    echo "Tasks: " . print_r($tasks, true);
    echo "</pre>";
    exit;
}

// Prepare content for template
ob_start();
?>

<!-- Content -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manajemen Tugas</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-lg"></i> Tambah Tugas
        </button>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Belum Dikerjakan</option>
                        <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>Dalam Proses</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prioritas</label>
                    <select class="form-select" name="priority">
                        <option value="all" <?php echo $filter_priority === 'all' ? 'selected' : ''; ?>>Semua Prioritas</option>
                        <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>Tinggi</option>
                        <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Sedang</option>
                        <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Rendah</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cari</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari judul atau deskripsi...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tasks List -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Daftar Tugas</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tasks)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-clipboard-check fs-1"></i>
                    <p class="mt-2">Tidak ada tugas ditemukan</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($tasks as $task): ?>
                        <div class="list-group-item task-item <?php echo $task['status'] === 'completed' ? 'completed' : ''; ?>" data-priority="<?php echo $task['priority']; ?>">
                            <div class="d-flex align-items-center">
                                <!-- Status checkbox -->
                                <div class="me-3">
                                    <div class="form-check">
                                        <input class="form-check-input task-status-checkbox" type="checkbox" 
                                            data-id="<?php echo $task['id']; ?>" 
                                            <?php echo $task['status'] === 'completed' ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                
                                <!-- Task content -->
                                <div class="flex-grow-1">
                                    <h5 class="task-title mb-1"><?php echo htmlspecialchars($task['title']); ?></h5>
                                    <div class="task-description text-muted mb-2">
                                        <?php echo htmlspecialchars($task['description']); ?>
                                    </div>
                                    <div class="task-meta d-flex flex-wrap gap-3">
                                        <div>
                                            <i class="bi bi-calendar"></i> 
                                            <?php echo date('d M Y', strtotime($task['due_date'])); ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-flag-fill priority-<?php echo $task['priority']; ?>"></i> 
                                            <?php 
                                                echo $task['priority'] === 'high' ? 'Tinggi' : 
                                                    ($task['priority'] === 'medium' ? 'Sedang' : 'Rendah'); 
                                            ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-person"></i> 
                                            <?php echo htmlspecialchars($task['nama_lengkap'] ?: $task['username']); ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-clock"></i> 
                                            <?php echo date('d M Y', strtotime($task['created_at'])); ?>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php 
                                                echo $task['status'] === 'completed' ? 'success' : 
                                                    ($task['status'] === 'in_progress' ? 'primary' : 'warning'); 
                                            ?>">
                                                <?php 
                                                    echo $task['status'] === 'completed' ? 'Selesai' : 
                                                        ($task['status'] === 'in_progress' ? 'Dalam Proses' : 'Belum Dikerjakan'); 
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Task actions -->
                                <div class="ms-auto">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item edit-task" href="#" data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                                   data-id="<?php echo $task['id']; ?>"
                                                   data-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                   data-description="<?php echo htmlspecialchars($task['description']); ?>"
                                                   data-due-date="<?php echo $task['due_date']; ?>"
                                                   data-priority="<?php echo $task['priority']; ?>"
                                                   data-status="<?php echo $task['status']; ?>"
                                                   data-user-id="<?php echo $task['user_id']; ?>">
                                                    <i class="bi bi-pencil me-2"></i> Edit
                                                </a>
                                            </li>
                                            <?php if ($task['status'] !== 'completed'): ?>
                                            <li>
                                                <form method="POST" class="complete-task-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="bi bi-check-circle me-2"></i> Tandai Selesai
                                                    </button>
                                                </form>
                                            </li>
                                            <?php else: ?>
                                            <li>
                                                <form method="POST" class="reopen-task-form">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                                    <input type="hidden" name="status" value="pending">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="bi bi-arrow-counterclockwise me-2"></i> Buka Kembali
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger delete-task" href="#" data-bs-toggle="modal" data-bs-target="#deleteTaskModal"
                                                   data-id="<?php echo $task['id']; ?>"
                                                   data-title="<?php echo htmlspecialchars($task['title']); ?>">
                                                    <i class="bi bi-trash me-2"></i> Hapus
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Tugas Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
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
                        <input type="date" class="form-control" name="due_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prioritas</label>
                        <select class="form-select" name="priority">
                            <option value="low">Rendah</option>
                            <option value="medium" selected>Sedang</option>
                            <option value="high">Tinggi</option>
                        </select>
                    </div>
                    <?php if ($is_admin && !empty($users)): ?>
                    <div class="mb-3">
                        <label class="form-label">Tetapkan Kepada</label>
                        <select class="form-select" name="assign_to">
                            <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $user_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nama_lengkap'] ?: $u['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Tugas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_task_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Judul Tugas</label>
                        <input type="text" class="form-control" name="title" id="edit_task_title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" id="edit_task_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Jatuh Tempo</label>
                        <input type="date" class="form-control" name="due_date" id="edit_task_due_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prioritas</label>
                        <select class="form-select" name="priority" id="edit_task_priority">
                            <option value="low">Rendah</option>
                            <option value="medium">Sedang</option>
                            <option value="high">Tinggi</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_task_status">
                            <option value="pending">Belum Dikerjakan</option>
                            <option value="in_progress">Dalam Proses</option>
                            <option value="completed">Selesai</option>
                        </select>
                    </div>
                    <?php if ($is_admin && !empty($users)): ?>
                    <div class="mb-3">
                        <label class="form-label">Tetapkan Kepada</label>
                        <select class="form-select" name="assign_to" id="edit_task_assign_to">
                            <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['nama_lengkap'] ?: $u['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Task Modal -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Tugas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus tugas "<span id="delete_task_title"></span>"?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_task_id">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto close alert after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    }
    
    // Auto dismiss modals and refresh on form submit success
    <?php if (isset($success)): ?>
    const activeModal = document.querySelector('.modal.show');
    if (activeModal) {
        const modal = bootstrap.Modal.getInstance(activeModal);
        if (modal) {
            modal.hide();
        }
    }
    <?php endif; ?>
    
    // Handle edit task modal
    document.querySelectorAll('.edit-task').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const description = this.getAttribute('data-description');
            const dueDate = this.getAttribute('data-due-date');
            const priority = this.getAttribute('data-priority');
            const status = this.getAttribute('data-status');
            const userId = this.getAttribute('data-user-id');
            
            document.getElementById('edit_task_id').value = id;
            document.getElementById('edit_task_title').value = title;
            document.getElementById('edit_task_description').value = description;
            document.getElementById('edit_task_due_date').value = dueDate;
            document.getElementById('edit_task_priority').value = priority;
            document.getElementById('edit_task_status').value = status;
            
            const assignToSelect = document.getElementById('edit_task_assign_to');
            if (assignToSelect) {
                assignToSelect.value = userId;
            }
        });
    });
    
    // Handle delete task modal
    document.querySelectorAll('.delete-task').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            
            document.getElementById('delete_task_id').value = id;
            document.getElementById('delete_task_title').textContent = title;
        });
    });
    
    // Handle task status checkboxes
    document.querySelectorAll('.task-status-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const taskId = this.getAttribute('data-id');
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_status';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = taskId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = this.checked ? 'completed' : 'pending';
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            form.appendChild(statusInput);
            
            document.body.appendChild(form);
            form.submit();
        });
    });
});
</script>

<?php
$content = ob_get_clean();

// Set template variables
$page_title = 'Manajemen Tugas';
$current_page = 'tasks';
$base_path = '../';
$additional_js = "<script src='{$base_path}assets/js/tasks.js'></script>";
$additional_css = "<link rel='stylesheet' href='{$base_path}assets/css/tasks.css'>";

// Include template
include '../views/templates/layout.php';
?> 