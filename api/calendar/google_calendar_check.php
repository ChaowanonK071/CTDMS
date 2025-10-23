<?php
/**
 * Fixed Google Calendar Check API - Complete Version
 * ไฟล์: /api/calendar/google_calendar_check.php
 * แก้ไขการแสดงสถานะ Google Calendar ให้ถูกต้อง
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // ไม่แสดง error ใน response
ini_set('log_errors', 1);

// ป้องกัน output buffer และ error ที่อาจรบกวน JSON
ob_start();

// Set headers ก่อน output อื่นๆ
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ทำความสะอาด output buffer
if (ob_get_length()) {
    ob_clean();
}

// จัดการ OPTIONS request สำหรับ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Database configuration fallback
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'teachingscheduledb');
}

/**
 * ส่ง JSON response และหยุดการทำงาน
 */
function sendJsonResponse($data, $httpCode = 200) {
    // ทำความสะอาด output buffer
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * จัดการ error อย่างปลอดภัย
 */
function handleSafeError($message, $code = 500, $details = null) {
    $error_data = [
        'status' => 'error',
        'message' => $message,
        'has_google_auth' => false,
        'action_required' => 'check_error',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($details) {
        error_log("Google Calendar Check Error: " . $message . " | Details: " . json_encode($details));
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $error_data['debug_details'] = $details;
        }
    }
    
    sendJsonResponse($error_data, $code);
}

/**
 * ตรวจสอบการเข้าสู่ระบบอย่างปลอดภัย
 */
function checkLoginSafely() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'กรุณาเข้าสู่ระบบ',
            'has_google_auth' => false,
            'action_required' => 'login',
            'requires_login' => true
        ], 401);
    }
    
    return (int)$_SESSION['user_id'];
}

/**
 * เชื่อมต่อฐานข้อมูลอย่างปลอดภัย
 */
function getDbConnectionSafely() {
    try {
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception('Connection failed: ' . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
        
    } catch (Exception $e) {
        handleSafeError('ไม่สามารถเชื่อมต่อฐานข้อมูลได้', 500, $e->getMessage());
    }
}

/**
 * ตรวจสอบว่ามีตาราง google_auth หรือไม่
 */
function checkGoogleAuthTableExists($conn) {
    try {
        $result = $conn->query("SHOW TABLES LIKE 'google_auth'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        error_log("Error checking google_auth table: " . $e->getMessage());
        return false;
    }
}

/**
 * สร้างตาราง google_auth ถ้าไม่มี
 */
function createGoogleAuthTableIfNeeded($conn) {
    if (checkGoogleAuthTableExists($conn)) {
        return true;
    }
    
    try {
        $createTable = "CREATE TABLE IF NOT EXISTS google_auth (
            google_auth_id INT(11) PRIMARY KEY AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            google_access_token TEXT,
            google_refresh_token TEXT,
            google_id_token TEXT,
            token_expiry DATETIME DEFAULT NULL,
            google_email VARCHAR(255) DEFAULT NULL,
            google_name VARCHAR(255) DEFAULT NULL,
            calendar_id VARCHAR(255) DEFAULT 'primary',
            is_active TINYINT(1) DEFAULT 1,
            last_checked DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (is_active),
            UNIQUE KEY unique_user_google (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $result = $conn->query($createTable);
        
        if (!$result) {
            error_log("Error creating google_auth table: " . $conn->error);
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Exception creating google_auth table: " . $e->getMessage());
        return false;
    }
}

/**
 * ตรวจสอบสถานะ Google Calendar (แก้ไขแล้ว)
 */
function checkGoogleCalendarStatusFixed($user_id) {
    try {
        $conn = getDbConnectionSafely();
        
        // ตรวจสอบและสร้างตารางถ้าจำเป็น
        if (!createGoogleAuthTableIfNeeded($conn)) {
            $conn->close();
            return [
                'has_google_auth' => false,
                'status' => 'table_error',
                'message' => 'ไม่สามารถสร้างหรือเข้าถึงตาราง google_auth ได้',
                'action_required' => 'check_database'
            ];
        }
        
        $query = "
            SELECT 
                google_auth_id, 
                google_email, 
                google_name, 
                token_expiry,
                google_refresh_token IS NOT NULL as has_refresh_token,
                google_access_token IS NOT NULL as has_access_token,
                created_at,
                updated_at,
                last_checked,
                TIMESTAMPDIFF(MINUTE, NOW(), token_expiry) as minutes_to_expiry,
                CASE 
                    WHEN token_expiry IS NULL THEN 'no_expiry'
                    WHEN token_expiry > NOW() THEN 'valid'
                    WHEN token_expiry > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'expired_refreshable'
                    ELSE 'long_expired_refreshable'
                END as token_status
            FROM google_auth 
            WHERE user_id = ? AND is_active = 1
            ORDER BY updated_at DESC
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            $conn->close();
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->close();
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // เพิ่มการตรวจสอบสถานะการซิงค์
        $sync_stats = getGoogleCalendarSyncStats($conn, $user_id);
        $conn->close();
        
        if (!$result) {
            // ไม่มีข้อมูล Google Auth
            return [
                'has_google_auth' => false,
                'status' => 'no_auth',
                'message' => 'ยังไม่ได้เชื่อมต่อ Google Calendar',
                'oauth_url' => '/api/calendar/google_calendar_oauth.php?action=start',
                'action_required' => 'connect',
                'user_id' => $user_id,
                'sync_ready' => false,
                'sync_stats' => [
                    'synced_count' => 0,
                    'pending_count' => 0,
                    'failed_count' => 0,
                    'total_count' => 0
                ]
            ];
        }
        
        // มีข้อมูล Google Auth แล้ว
        $token_status = $result['token_status'];
        $minutes_to_expiry = $result['minutes_to_expiry'];
        $has_refresh_token = (bool)$result['has_refresh_token'];
        $has_access_token = (bool)$result['has_access_token'];
        
        $response = [
            'has_google_auth' => true,
            'google_auth_id' => (int)$result['google_auth_id'],
            'google_email' => $result['google_email'],
            'google_name' => $result['google_name'],
            'connected_at' => $result['created_at'],
            'last_updated' => $result['updated_at'],
            'last_checked' => $result['last_checked'],
            'token_status' => $token_status,
            'token_expiry' => $result['token_expiry'],
            'minutes_to_expiry' => $minutes_to_expiry,
            'has_refresh_token' => $has_refresh_token,
            'has_access_token' => $has_access_token,
            'user_id' => $user_id,
            'sync_stats' => $sync_stats
        ];
        
        // กำหนดสถานะและ action ตามสถานะ token
        switch ($token_status) {
            case 'valid':
            case 'no_expiry':
                $response['status'] = 'connected';
                $response['message'] = 'Google Calendar เชื่อมต่อแล้ว และพร้อมส่งข้อมูล';
                $response['action_required'] = 'none';
                $response['sync_ready'] = true;
                
                if ($minutes_to_expiry !== null && $minutes_to_expiry <= 120) {
                    $response['should_refresh'] = true;
                    $response['expires_soon'] = true;
                    $response['message'] = 'Google Calendar เชื่อมต่อแล้ว - Token จะหมดอายุเร็วๆ นี้';
                }
                break;
                
            case 'expired_refreshable':
            case 'long_expired_refreshable':
                if ($has_refresh_token) {
                    $response['status'] = 'token_expired_can_refresh';
                    $response['message'] = 'Google Calendar Token หมดอายุ - สามารถ Refresh ได้';
                    $response['action_required'] = 'refresh';
                    $response['can_refresh'] = true;
                    $response['needs_refresh'] = true;
                    $response['sync_ready'] = false;
                } else {
                    $response['status'] = 'token_expired_need_reconnect';
                    $response['message'] = 'Google Calendar Token หมดอายุ - ต้องเชื่อมต่อใหม่';
                    $response['action_required'] = 'connect';
                    $response['oauth_url'] = '/api/calendar/google_calendar_oauth.php?action=start';
                    $response['can_refresh'] = false;
                    $response['sync_ready'] = false;
                }
                break;
                
            default:
                $response['status'] = 'unknown';
                $response['message'] = 'สถานะ Google Calendar ไม่ทราบ';
                $response['action_required'] = 'check';
                $response['sync_ready'] = false;
                break;
        }
        
        return $response;
        
    } catch (Exception $e) {
        error_log("Error checking Google Calendar status: " . $e->getMessage());
        return [
            'has_google_auth' => false,
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการตรวจสอบ Google Authentication',
            'error_detail' => $e->getMessage(),
            'user_id' => $user_id,
            'action_required' => 'check_error',
            'sync_ready' => false,
            'sync_stats' => [
                'synced_count' => 0,
                'pending_count' => 0,
                'failed_count' => 0,
                'total_count' => 0
            ]
        ];
    }
}

/**
 * ดึงสถิติการซิงค์ Google Calendar
 */
function getGoogleCalendarSyncStats($conn, $user_id) {
    try {
        // ตรวจสอบว่ามีตาราง class_sessions หรือไม่
        $checkTable = $conn->query("SHOW TABLES LIKE 'class_sessions'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return [
                'synced_count' => 0,
                'pending_count' => 0,
                'failed_count' => 0,
                'total_count' => 0,
                'table_available' => false,
                'message' => 'ตาราง class_sessions ยังไม่พร้อม'
            ];
        }
        
        // ตรวจสอบว่ามีคอลัมน์ google_sync_status หรือไม่
        $checkColumn = $conn->query("SHOW COLUMNS FROM class_sessions LIKE 'google_sync_status'");
        $hasGoogleSyncStatus = $checkColumn && $checkColumn->num_rows > 0;
        
        if (!$hasGoogleSyncStatus) {
            // ถ้าไม่มีคอลัมน์ google_sync_status ให้นับทั้งหมดเป็นรอส่ง
            $fallback_query = "
                SELECT 
                    COUNT(*) as total_sessions
                FROM class_sessions 
                WHERE user_id = ?
                AND session_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            ";
            
            $stmt = $conn->prepare($fallback_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $fallback_stats = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            return [
                'synced_count' => 0,
                'pending_count' => (int)$fallback_stats['total_sessions'],
                'failed_count' => 0,
                'total_count' => (int)$fallback_stats['total_sessions'],
                'table_available' => true,
                'message' => 'คอลัมน์ google_sync_status ยังไม่มี - นับทั้งหมดเป็นรอส่ง',
                'sync_method' => 'no_google_sync_status_column'
            ];
        }
        
        // นับข้อมูลจริงจากตาราง (ลบเงื่อนไข date filter ออกก่อนเพื่อดูข้อมูลทั้งหมด)
        $stats_query = "
            SELECT 
                COUNT(*) as total_all_sessions,
                SUM(CASE 
                    WHEN google_sync_status = 'synced'
                    THEN 1 ELSE 0 
                END) as synced_count,
                SUM(CASE 
                    WHEN google_sync_status = 'pending'
                    THEN 1 ELSE 0 
                END) as pending_count,
                SUM(CASE 
                    WHEN google_sync_status = 'failed' OR google_sync_status = 'error'
                    THEN 1 ELSE 0 
                END) as failed_count,
                SUM(CASE 
                    WHEN google_sync_status IS NULL
                    THEN 1 ELSE 0 
                END) as null_count,
                MAX(google_sync_at) as last_sync_time,
                COUNT(DISTINCT session_date) as unique_dates,
                COUNT(CASE WHEN google_sync_status = 'synced' AND google_event_id IS NOT NULL THEN 1 END) as synced_with_event_id,
                MIN(session_date) as earliest_date,
                MAX(session_date) as latest_date
            FROM class_sessions 
            WHERE user_id = ?
        ";
        
        $stmt = $conn->prepare($stats_query);
        if (!$stmt) {
            throw new Exception('Prepare sync stats failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Execute sync stats failed: ' . $stmt->error);
        }
        
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // ข้อมูลจริงจากฐานข้อมูล
        $total_count = (int)$stats['total_all_sessions'];
        $synced_count = (int)$stats['synced_count'];
        $pending_count = (int)$stats['pending_count'];
        $failed_count = (int)$stats['failed_count'];
        $null_count = (int)$stats['null_count'];
        
        // รวม pending + null เป็นข้อมูลรอส่ง
        $total_pending = $pending_count + $null_count;
        
        // คำนวณสถิติเพิ่มเติม
        $success_rate = $total_count > 0 ? round(($synced_count / $total_count) * 100, 1) : 0;
        $sync_accuracy = $synced_count > 0 ? round(($stats['synced_with_event_id'] / $synced_count) * 100, 1) : 0;
        
        return [
            'synced_count' => $synced_count,
            'pending_count' => $total_pending, // pending + null
            'failed_count' => $failed_count,
            'total_count' => $total_count,
            'success_rate' => $success_rate,
            'sync_accuracy' => $sync_accuracy,
            'last_sync_time' => $stats['last_sync_time'],
            'unique_dates' => (int)$stats['unique_dates'],
            'date_range' => [
                'earliest' => $stats['earliest_date'],
                'latest' => $stats['latest_date']
            ],
            'details' => [
                'class_sessions' => [
                    'total_all_sessions' => $total_count,
                    'synced' => $synced_count,
                    'pending_explicit' => $pending_count,
                    'null_status' => $null_count,
                    'failed' => $failed_count,
                    'pending_total' => $total_pending,
                    'synced_with_event_id' => (int)$stats['synced_with_event_id'],
                    'unique_dates' => (int)$stats['unique_dates']
                ]
            ],
            'table_available' => true,
            'sync_method' => 'accurate_count',
            'data_source' => 'class_sessions only',
            'has_google_sync_status_column' => true,
            'calculation_method' => 'pending_total = pending + null',
            'verification' => [
                'total_calculated' => ($synced_count + $failed_count + $total_pending),
                'total_actual' => $total_count,
                'matches' => ($synced_count + $failed_count + $total_pending) == $total_count
            ],
            'raw_data_check' => [
                'all_sessions' => $total_count,
                'status_pending' => $pending_count,
                'status_null' => $null_count,
                'status_synced' => $synced_count,
                'status_failed' => $failed_count,
                'should_be' => $total_count,
                'should_be_all_pending' => $pending_count == 0 && $null_count == 0
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error getting Google Calendar sync stats for user $user_id: " . $e->getMessage());
        return [
            'synced_count' => 0,
            'pending_count' => 0,
            'failed_count' => 0,
            'total_count' => 0,
            'error' => $e->getMessage(),
            'table_available' => false,
            'sync_method' => 'error'
        ];
    }
}

/**
 * Main request handler
 */
try {
    // เริ่ม session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // ตรวจสอบการเข้าสู่ระบบ
    $user_id = checkLoginSafely();
    $username = $_SESSION['username'] ?? 'Unknown';
    
    // ตรวจสอบ method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Method not allowed: ' . $_SERVER['REQUEST_METHOD'],
            'allowed_methods' => ['GET']
        ], 405);
    }
    
    // ตรวจสอบสถานะ Google Calendar ด้วยฟังก์ชันที่แก้ไขแล้ว
    $status = checkGoogleCalendarStatusFixed($user_id);
    
    // เพิ่มข้อมูลเพิ่มเติม
    $status['username'] = $username;
    $status['timestamp'] = date('Y-m-d H:i:s');
    $status['server_time'] = time();
    $status['api_version'] = '3.1';
    $status['database_accessible'] = true;
    $status['request_method'] = $_SERVER['REQUEST_METHOD'];
    
    // ส่ง response
    sendJsonResponse($status);
    
} catch (Exception $e) {
    error_log("Google Calendar Check API Error: " . $e->getMessage());
    handleSafeError('เกิดข้อผิดพลาดที่ไม่คาดคิด', 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    error_log("Google Calendar Check API Fatal Error: " . $e->getMessage());
    handleSafeError('เกิดข้อผิดพลาดร้าย', 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

?>