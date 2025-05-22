<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

class ActivityLogger {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new Auth();
    }
    
    /**
     * Log an activity
     * @param string $action The action performed (e.g., 'create', 'update', 'delete')
     * @param string $module The module where the action was performed (e.g., 'muzakki', 'bayarzakat', 'distribusi')
     * @param string $description Additional details about the action
     * @param int|null $record_id The ID of the affected record (if applicable)
     * @return bool Whether the activity was successfully logged
     */
    public function log($action, $module, $description, $record_id = null) {
        try {
            $user_id = $this->auth->getUserId();
            $username = $this->auth->getUsername();
            $role = $this->auth->getUserRole();
            $ip_address = $this->getIpAddress();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt = $this->db->prepare("INSERT INTO activity_logs (user_id, username, role, action, module, description, record_id, ip_address, user_agent) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            return $stmt->execute([
                $user_id, 
                $username, 
                $role, 
                $action, 
                $module, 
                $description, 
                $record_id, 
                $ip_address, 
                $user_agent
            ]);
        } catch (PDOException $e) {
            // Log error to file instead
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the client IP address
     * @return string The IP address
     */
    private function getIpAddress() {
        // Check for shared internet/ISP IP
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        
        // Check for IPs passing through proxies
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can include multiple IPs, take the first one
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Get activity logs with pagination
     * @param int $page The page number
     * @param int $perPage Items per page
     * @param array $filters Optional filters for the logs
     * @return array An array containing the logs and pagination info
     */
    public function getLogs($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        // Build the WHERE clause based on filters
        $whereClause = '';
        $params = [];
        
        if (!empty($filters)) {
            $whereConditions = [];
            
            if (isset($filters['username']) && !empty($filters['username'])) {
                $whereConditions[] = "username LIKE ?";
                $params[] = '%' . $filters['username'] . '%';
            }
            
            if (isset($filters['action']) && !empty($filters['action'])) {
                $whereConditions[] = "action = ?";
                $params[] = $filters['action'];
            }
            
            if (isset($filters['module']) && !empty($filters['module'])) {
                $whereConditions[] = "module = ?";
                $params[] = $filters['module'];
            }
            
            if (isset($filters['start_date']) && !empty($filters['start_date'])) {
                $whereConditions[] = "DATE(created_at) >= ?";
                $params[] = $filters['start_date'];
            }
            
            if (isset($filters['end_date']) && !empty($filters['end_date'])) {
                $whereConditions[] = "DATE(created_at) <= ?";
                $params[] = $filters['end_date'];
            }
            
            if (!empty($whereConditions)) {
                $whereClause = " WHERE " . implode(" AND ", $whereConditions);
            }
        }
        
        // Get total count for pagination
        $countStmt = $this->db->prepare("SELECT COUNT(*) as total FROM activity_logs" . $whereClause);
        $countStmt->execute($params);
        $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get the logs with pagination
        $query = "SELECT * FROM activity_logs" . $whereClause . " ORDER BY created_at DESC LIMIT " . (int)$offset . ", " . (int)$perPage;
        $stmt = $this->db->prepare($query);
        
        // Execute with WHERE clause parameters only (no pagination parameters)
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'logs' => $logs,
            'pagination' => [
                'total' => (int)$totalItems,
                'per_page' => (int)$perPage,
                'current_page' => (int)$page,
                'last_page' => ceil($totalItems / $perPage)
            ]
        ];
    }
} 
