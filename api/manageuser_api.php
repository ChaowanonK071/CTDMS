<?php
// ป้องกัน HTML error output ที่ทำให้ JSON invalid
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// เริ่ม output buffering เพื่อจับ error ที่อาจหลุดออกมา
ob_start();

// Include การตั้งค่าฐานข้อมูล
try {
    require_once 'config.php';
} catch (Exception $e) {
    // ล้าง buffer และส่ง JSON error
    ob_clean();
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถโหลดไฟล์ config.php ได้: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// รองรับ OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_clean();
    header('HTTP/1.1 200 OK');
    exit;
}

// ฟังก์ชัน Debug Logging ที่ปรับปรุงแล้ว
function logDebug($message, $data = null) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage, 3, __DIR__ . '/api_debug.log');
}

/**
 * ฟังก์ชันส่ง JSON Response ที่ปลอดภัย
 */
function sendJsonResponse($data, $httpCode = 200) {
    ob_clean();
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ฟังก์ชันส่ง Error Response
 */
function sendError($message, $code = 500) {
    logDebug("API Error", [
        'message' => $message,
        'code' => $code,
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI']
    ]);
    
    sendJsonResponse([
        'status' => 'error',
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $code);
}

class UserController {
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = connectMySQLi();
            logDebug("UserController initialized successfully");
        } catch (Exception $e) {
            logDebug("Failed to initialize UserController", ['error' => $e->getMessage()]);
            sendError("ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * ดึงข้อมูลผู้ใช้ทั้งหมดพร้อมการกรองและแบ่งหน้า
     */
    public function getUsers() {
        try {
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $type = isset($_GET['type']) ? trim($_GET['type']) : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
            
            $offset = ($page - 1) * $limit;
            
            logDebug("Get users request", [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'page' => $page,
                'limit' => $limit
            ]);
            
            $sql = "SELECT user_id, username, title, name, lastname, email, user_type, is_active, created_at,
                    CONCAT(COALESCE(title, ''), ' ', COALESCE(name, ''), ' ', COALESCE(lastname, '')) as fullname 
                    FROM users WHERE 1=1";
            $countSql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
            $params = [];
            
            // เงื่อนไขการค้นหา
            if (!empty($search)) {
                $searchTerm = "%$search%";
                $searchCondition = " AND (username LIKE ? OR CONCAT(COALESCE(title, ''), ' ', COALESCE(name, ''), ' ', COALESCE(lastname, '')) LIKE ? OR email LIKE ?)";
                $sql .= $searchCondition;
                $countSql .= $searchCondition;
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($type)) {
                $sql .= " AND user_type = ?";
                $countSql .= " AND user_type = ?";
                $params[] = $type;
            }
            
            if ($status !== '') {
                $sql .= " AND is_active = ?";
                $countSql .= " AND is_active = ?";
                $params[] = (int)$status;
            }
            
            // เพิ่มการเรียงลำดับ
            $sql .= " ORDER BY user_id ASC LIMIT ?, ?";
            
            // นับจำนวนรวม
            $countStmt = $this->prepareAndExecute($countSql, $params);
            $countResult = $countStmt->get_result();
            $totalUsers = $countResult->fetch_assoc()['total'];
            $countStmt->close();
            
            // ดึงข้อมูลผู้ใช้
            $pageParams = array_merge($params, [$offset, $limit]);
            $stmt = $this->prepareAndExecute($sql, $pageParams);
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                // ลบ password ออกจาก response
                unset($row['password']);
                $users[] = $row;
            }
            $stmt->close();
            
            $responseData = [
                'status' => 'success',
                'total' => (int)$totalUsers,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalUsers / $limit),
                'users' => $users
            ];
            
            logDebug("Get users success", ['total' => $totalUsers, 'returned' => count($users)]);
            sendJsonResponse($responseData);
            
        } catch (Exception $e) {
            logDebug("Error in getUsers", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            sendError("เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * ดึงข้อมูลผู้ใช้ตาม ID
     */
    public function getUser($id) {
        try {
            $sql = "SELECT user_id, username, title, name, lastname, email, user_type, is_active, created_at,
                    CONCAT(COALESCE(title, ''), ' ', COALESCE(name, ''), ' ', COALESCE(lastname, '')) as fullname 
                    FROM users WHERE user_id = ?";
            $stmt = $this->prepareAndExecute($sql, [$id]);
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendError("ไม่พบผู้ใช้ที่มี ID: $id", 404);
                return;
            }
            
            $user = $result->fetch_assoc();
            unset($user['password']);
            $stmt->close();
            
            sendJsonResponse([
                'status' => 'success',
                'data' => $user
            ]);
            
        } catch (Exception $e) {
            logDebug("Error in getUser", ['id' => $id, 'error' => $e->getMessage()]);
            sendError("เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * สร้างผู้ใช้ใหม่
     */
    public function createUser() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            sendError("ไม่พบข้อมูลสำหรับการสร้างผู้ใช้ใหม่ หรือข้อมูล JSON ไม่ถูกต้อง", 400);
            return;
        }
        
        try {
            logDebug("Create user request", $data);
            
            // Validation
            $errors = $this->validateUserData($data, true);
            if (!empty($errors)) {
                sendError(implode(', ', $errors), 400);
                return;
            }
            
            // ทำความสะอาดข้อมูล
            $username = trim($data['username']);
            $title = trim($data['title']);
            $name = trim($data['name']);
            $lastname = trim($data['lastname']);
            $email = trim($data['email']);
            $userType = $data['user_type'];
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            
            // ตรวจสอบข้อมูลซ้ำ
            if ($this->checkDuplicate('username', $username)) {
                sendError("ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว", 400);
                return;
            }
            
            if ($this->checkDuplicate('email', $email)) {
                sendError("อีเมลนี้มีอยู่ในระบบแล้ว", 400);
                return;
            }
            
            // เข้ารหัสรหัสผ่าน
            if (empty($data['password'])) {
                $data['password'] = bin2hex(random_bytes(4));
            }
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, password, title, name, lastname, email, user_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$username, $hashedPassword, $title, $name, $lastname, $email, $userType, $isActive];
            
            $stmt = $this->prepareAndExecute($sql, $params);
            $newUserId = $this->conn->insert_id;
            $stmt->close();
            
            logDebug("User created successfully", ['user_id' => $newUserId]);
            
            sendJsonResponse([
                'status' => 'success',
                'message' => 'สร้างผู้ใช้ใหม่เรียบร้อยแล้ว',
                'user_id' => $newUserId
            ]);
            
        } catch (Exception $e) {
            logDebug("Error in createUser", ['error' => $e->getMessage(), 'data' => $data]);
            $this->handleSQLError($e);
        }
    }
    
    /**
     * อัปเดตข้อมูลผู้ใช้
     */
    public function updateUser($id) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            sendError("ไม่พบข้อมูลสำหรับการอัปเดต หรือข้อมูล JSON ไม่ถูกต้อง", 400);
            return;
        }
        
        try {
            logDebug("Update user request", ['id' => $id, 'data' => $data]);
            
            // ตรวจสอบว่าผู้ใช้มีอยู่
            if (!$this->userExists($id)) {
                sendError("ไม่พบผู้ใช้ที่มี ID: $id", 404);
                return;
            }
            
            $updateFields = [];
            $params = [];
            
            $fieldsToUpdate = ['username', 'title', 'name', 'lastname', 'email', 'user_type', 'is_active'];
            
            foreach ($fieldsToUpdate as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                    
                    // ตรวจสอบข้อมูลซ้ำ
                    if (in_array($field, ['username', 'email']) && $this->checkDuplicate($field, $value, $id)) {
                        sendError("$field นี้มีอยู่ในระบบแล้ว", 400);
                        return;
                    }
                    
                    $updateFields[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            // จัดการ password
            if (!empty($data['password'])) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                sendError("ไม่มีข้อมูลที่จะอัปเดต", 400);
                return;
            }
            
            // Execute update
            $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
            $params[] = $id;
            
            $stmt = $this->prepareAndExecute($sql, $params);
            $stmt->close();
            
            logDebug("User updated successfully", ['user_id' => $id]);
            
            sendJsonResponse([
                'status' => 'success',
                'message' => 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว'
            ]);
            
        } catch (Exception $e) {
            logDebug("Error in updateUser", ['id' => $id, 'error' => $e->getMessage()]);
            $this->handleSQLError($e);
        }
    }
    
    /**
     * ลบผู้ใช้
     */
    public function deleteUser($id) {
        try {
            if (!$this->userExists($id)) {
                sendError("ไม่พบผู้ใช้ที่มี ID: $id", 404);
                return;
            }
            
            $sql = "DELETE FROM users WHERE user_id = ?";
            $stmt = $this->prepareAndExecute($sql, [$id]);
            $stmt->close();
            
            logDebug("User deleted successfully", ['user_id' => $id]);
            
            sendJsonResponse([
                'status' => 'success',
                'message' => 'ลบผู้ใช้เรียบร้อยแล้ว'
            ]);
            
        } catch (Exception $e) {
            logDebug("Error in deleteUser", ['id' => $id, 'error' => $e->getMessage()]);
            sendError("เกิดข้อผิดพลาดในการลบผู้ใช้: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * ตรวจสอบสถานะ API
     */
    public function healthCheck() {
        try {
            $result = $this->conn->query("SELECT COUNT(*) as count FROM users");
            $userCount = $result->fetch_assoc()['count'];
            
            sendJsonResponse([
                'status' => 'success',
                'message' => 'API ทำงานปกติ',
                'database' => 'connected',
                'total_users' => (int)$userCount,
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION
            ]);
            
        } catch (Exception $e) {
            sendError("Health check failed: " . $e->getMessage(), 500);
        }
    }
    
    // Helper Methods
    
    private function prepareAndExecute($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error . " | SQL: " . $sql);
        }
        
        if (!empty($params)) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error . " | SQL: " . $sql);
        }
        
        return $stmt;
    }
    
    private function validateUserData($data, $isCreate = false) {
        $errors = [];

        $required = ['username', 'name', 'lastname', 'email', 'user_type'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $errors[] = "กรุณากรอกข้อมูล $field";
            }
        }
        
        // ตรวจสอบอีเมล
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
            }
            if (strlen($email) > 100) {
                $errors[] = "อีเมลยาวเกินไป (สูงสุด 100 ตัวอักษร)";
            }
        }
        
        // ตรวจสอบประเภทผู้ใช้
        if (isset($data['user_type']) && !in_array($data['user_type'], ['admin', 'teacher'])) {
            $errors[] = "ประเภทผู้ใช้ไม่ถูกต้อง";
        }
        
        return $errors;
    }
    
    private function checkDuplicate($field, $value, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE $field = ?";
        $params = [$value];
        
        if ($excludeId !== null) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->prepareAndExecute($sql, $params);
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
        
        return $count > 0;
    }
    
    private function userExists($id) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_id = ?";
        $stmt = $this->prepareAndExecute($sql, [$id]);
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
        
        return $count > 0;
    }
    
    private function handleSQLError($e) {
        $message = $e->getMessage();
        
        if (strpos($message, 'Duplicate entry') !== false) {
            if (strpos($message, 'username') !== false) {
                sendError("ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว", 400);
            } elseif (strpos($message, 'email') !== false) {
                sendError("อีเมลนี้มีอยู่ในระบบแล้ว", 400);
            } else {
                sendError("เกิดข้อผิดพลาด: ข้อมูลซ้ำ", 400);
            }
        } else {
            sendError("เกิดข้อผิดพลาดในการดำเนินการ: " . $message, 500);
        }
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// ======= MAIN ROUTING =======

try {
    $controller = new UserController();
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri = $_SERVER['REQUEST_URI'];
    $urlParts = parse_url($requestUri);
    $path = $urlParts['path'];
    
    // Log request
    logDebug("API Request", [
        'method' => $requestMethod,
        'uri' => $requestUri,
        'path' => $path
    ]);
    
    // ตรวจสอบ endpoint
    if (strpos($path, 'manageuser_api') === false) {
        logDebug("Invalid endpoint", ['path' => $path]);
        sendError('Endpoint ไม่ถูกต้อง', 404);
    }
    
    // ดึง ID และ action
    $id = null;
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    
    if (preg_match('/manageuser_api\/(\d+)/', $path, $matches)) {
        $id = (int)$matches[1];
    } elseif (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
    }
    
    // Route handling
    switch ($requestMethod) {
        case 'GET':
            if ($action === 'health') {
                $controller->healthCheck();
            } elseif ($id) {
                $controller->getUser($id);
            } else {
                $controller->getUsers();
            }
            break;
            
        case 'POST':
            $controller->createUser();
            break;
            
        case 'PUT':
            if ($id) {
                $controller->updateUser($id);
            } else {
                sendError('จำเป็นต้องระบุ ID สำหรับการอัปเดต', 400);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                $controller->deleteUser($id);
            } else {
                sendError('จำเป็นต้องระบุ ID สำหรับการลบ', 400);
            }
            break;
            
        default:
            sendError('HTTP Method ไม่ได้รับการสนับสนุน', 405);
            break;
    }
    
} catch (Exception $e) {
    logDebug("Unhandled exception", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    sendError('เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์', 500);
}

logDebug("Request completed successfully");
?>