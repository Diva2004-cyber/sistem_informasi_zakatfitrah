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

function sendWhatsAppNotification($phone, $message) {
    // Replace with your WhatsApp API credentials
    $api_key = 'YOUR_WHATSAPP_API_KEY';
    $api_url = 'https://api.whatsapp.com/v1/messages';
    
    $data = [
        'phone' => $phone,
        'message' => $message
    ];
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $message = $_POST['message'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    try {
        // Save notification to database
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, message, status, created_at) 
                            VALUES (?, ?, ?, 'unread', NOW())");
        $stmt->execute([$auth->getUserId(), $type, $message]);
        
        // Send WhatsApp notification if phone number is provided
        if ($phone) {
            $result = sendWhatsAppNotification($phone, $message);
            if (isset($result['error'])) {
                throw new Exception($result['error']);
            }
        }
        
        header('Location: ../index.php?success=1');
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
} 