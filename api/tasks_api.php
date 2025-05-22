<?php
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/activity_logger.php';

// Initialize auth & database
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$logger = new ActivityLogger($db, $auth);
$user_id = $auth->getUserId();
$is_admin = $auth->isAdmin();

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Parse JSON request if Content-Type is application/json
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($contentType, 'application/json') !== false) {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
    } else {
        $data = $_POST;
    }
    
    $action = $data['action'] ?? '';
    $response = ['success' => false];
    
    if ($action === 'update_status') {
        $task_id = $data['task_id'] ?? 0;
        $status = $data['status'] ?? 'pending';
        
        try {
            // Check if user has permission to update this task
            $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                throw new Exception("Tugas tidak ditemukan");
            }
            
            // Only task owner or admin can update status
            if ($task['user_id'] != $user_id && !$is_admin) {
                throw new Exception("Anda tidak memiliki akses untuk mengubah status tugas ini");
            }
            
            $stmt = $db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $task_id]);
            
            $status_text = $status === 'completed' ? 'selesai' : ($status === 'in_progress' ? 'dalam proses' : 'pending');
            $logger->log('update', 'tasks', "Mengubah status tugas menjadi $status_text: " . $task['title'], $task_id);
            
            $response = [
                'success' => true,
                'message' => 'Status tugas berhasil diperbarui',
                'task_id' => $task_id,
                'status' => $status
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
} 