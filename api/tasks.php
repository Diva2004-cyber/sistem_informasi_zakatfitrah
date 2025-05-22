<?php
require_once '../config/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $user_id = $auth->getUserId();
    
    if (!$user_id) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'User ID tidak valid']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO tasks (title, description, due_date, priority, status, created_at, user_id) 
                            VALUES (?, ?, ?, ?, 'pending', NOW(), ?)");
        $stmt->execute([$title, $description, $due_date, $priority, $user_id]);
        
        // Create notification
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, message, status, created_at) 
                            VALUES (?, 'task', ?, 'unread', NOW())");
        $stmt->execute([$user_id, "Tugas baru: $title"]);
        
        header('Location: ../index.php?success=1');
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
} 