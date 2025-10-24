<?php
/**
 * API สำหรับจัดการปีการศึกษาโดยตรง - Fixed Version
 * ไฟล์: /api/api_academic_year_direct.php
 * เวอร์ชัน: 1.2 - แก้ไขปัญหา HTTP 400 และ connection
 */

// ตั้งค่าการแสดงผล error และ output buffering
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ตั้งค่า Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// จัดการ OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Register shutdown function สำหรับจัดการ Fatal Error
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $error['message'],
            'error' => 'Fatal Error',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// ฟังก์ชันช่วยเหลือสำหรับ Response
function jsonSuccess($message, $data = null) {
    ob_clean();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 400, $data = null) {
    ob_clean();
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => 'API Error',
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'teachingscheduledb',
    'charset' => 'utf8mb4'
];

// ฟังก์ชันเชื่อมต่อฐานข้อมูล
function connectDatabase() {
    global $db_config;
    
    try {
        $conn = new mysqli(
            $db_config['host'],
            $db_config['username'],
            $db_config['password'],
            $db_config['database']
        );
        
        if ($conn->connect_error) {
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        
        $conn->set_charset($db_config['charset']);
        return $conn;
        
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }
}

// เริ่มต้น session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบการเข้าสู่ระบบ (แบบยืดหยุ่น)
$user_id = $_SESSION['user_id'] ?? 1; // ใช้ 1 เป็นค่าเริ่มต้นสำหรับ admin

// รับ action จาก POST หรือ GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Debug: Log request information
error_log("=== Academic Year API Request ===");
error_log("Action: " . $action);
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST Data: " . json_encode($_POST));
error_log("User ID: " . $user_id);

// ตรวจสอบว่ามี action หรือไม่
if (empty($action)) {
    jsonError('ไม่พบ action ที่ต้องการ');
}

// ล้าง output buffer
ob_clean();

// ประมวลผล action
try {
    switch ($action) {
        case 'add_academic_year':
            addAcademicYear();
            break;
        case 'get_academic_years':
            getAcademicYears();
            break;
        case 'update_academic_year':
            updateAcademicYear();
            break;
        case 'delete_academic_year':
            deleteAcademicYear();
            break;
        case 'set_current_academic_year':
            setCurrentAcademicYear();
            break;
        default:
            jsonError('Action ไม่ถูกต้อง: ' . $action);
    }
} catch (Exception $e) {
    error_log("Exception in academic year API: " . $e->getMessage());
    jsonError($e->getMessage(), 500);
} catch (Error $e) {
    error_log("Fatal error in academic year API: " . $e->getMessage());
    jsonError('เกิดข้อผิดพลาดในระบบ', 500);
}

/**
 * ดึงรายการปีการศึกษาทั้งหมด
 */
function getAcademicYears() {
    try {
        $conn = connectDatabase();
        
        $sql = "SELECT * FROM academic_years ORDER BY academic_year DESC, semester ASC";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('ไม่สามารถดึงข้อมูลปีการศึกษาได้: ' . $conn->error);
        }
        
        $academic_years = [];
        while ($row = $result->fetch_assoc()) {
            $academic_years[] = $row;
        }
        
        $conn->close();
        
        jsonSuccess('ดึงรายการปีการศึกษาสำเร็จ', $academic_years);
        
    } catch (Exception $e) {
        error_log(" Error getting academic years: " . $e->getMessage());
        jsonError($e->getMessage());
    }
}

/**
 * เพิ่มปีการศึกษาใหม่
 */
function addAcademicYear() {
    global $user_id;
    
    // รับข้อมูลจาก POST
    $academic_year = intval($_POST['academic_year'] ?? 0);
    $semester = intval($_POST['semester'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_current = intval($_POST['is_current'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 1);

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($academic_year) || empty($semester) || empty($start_date) || empty($end_date)) {
        jsonError('กรุณากรอกข้อมูลให้ครบถ้วน (ปีการศึกษา, เทอม, วันที่เริ่มต้น, วันที่สิ้นสุด)');
    }
    
    // ตรวจสอบรูปแบบข้อมูล
    if ($academic_year < 2560 || $academic_year > 2580) {
        jsonError('ปีการศึกษาต้องอยู่ระหว่าง 2560-2580');
    }
    
    if (!in_array($semester, [1, 2, 3])) {
        jsonError('เทอมต้องเป็น 1, 2, หรือ 3');
    }
    
    // ตรวจสอบรูปแบบวันที่
    if (!strtotime($start_date) || !strtotime($end_date)) {
        jsonError('รูปแบบวันที่ไม่ถูกต้อง');
    }
    
    if (strtotime($start_date) >= strtotime($end_date)) {
        jsonError('วันที่เริ่มต้นต้องน้อยกว่าวันที่สิ้นสุด');
    }

    try {
        $conn = connectDatabase();
        
        // ตรวจสอบว่าปีการศึกษาและเทอมซ้ำหรือไม่
        $check_sql = "SELECT academic_year_id FROM academic_years WHERE academic_year = ? AND semester = ?";
        $stmt = $conn->prepare($check_sql);
        
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียมคำสั่ง SQL ได้: ' . $conn->error);
        }
        
        $stmt->bind_param("ii", $academic_year, $semester);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            $conn->close();
            jsonError('ปีการศึกษา ' . $academic_year . ' เทอม ' . $semester . ' มีอยู่แล้ว');
        }
        
        // ถ้าตั้งเป็นปีปัจจุบัน ให้อัปเดตปีอื่นเป็น false ก่อน
        if ($is_current) {
            $update_sql = "UPDATE academic_years SET is_current = 0";
            if (!$conn->query($update_sql)) {
                throw new Exception('ไม่สามารถอัปเดตปีการศึกษาปัจจุบันได้: ' . $conn->error);
            }
        }
        
        // เพิ่มปีการศึกษาใหม่
        $insert_sql = "INSERT INTO academic_years (academic_year, semester, start_date, end_date, is_active, is_current) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        
        if (!$stmt) {
            throw new Exception('ไม่สามารถเตรียมคำสั่ง SQL ได้: ' . $conn->error);
        }
        
        $stmt->bind_param("iissii", $academic_year, $semester, $start_date, $end_date, $is_active, $is_current);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถเพิ่มปีการศึกษาได้: ' . $stmt->error);
        }
        
        $new_id = $conn->insert_id;
        $conn->close();
        
        jsonSuccess('เพิ่มปีการศึกษาสำเร็จ', [
            'academic_year_id' => $new_id,
            'academic_year' => $academic_year,
            'semester' => $semester
        ]);
        
    } catch (Exception $e) {
        error_log(" Error adding academic year: " . $e->getMessage());
        jsonError($e->getMessage());
    }
}

/**
 * แก้ไขปีการศึกษา
 */
function updateAcademicYear() {
    global $user_id;
    
    $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
    $academic_year = intval($_POST['academic_year'] ?? 0);
    $semester = intval($_POST['semester'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_active = intval($_POST['is_active'] ?? 1);
    $is_current = intval($_POST['is_current'] ?? 0);

    if (empty($academic_year_id)) {
        jsonError('ไม่พบรหัสปีการศึกษา');
    }

    try {
        $conn = connectDatabase();
        
        // ตรวจสอบว่าปีการศึกษาอยู่หรือไม่
        $check_sql = "SELECT academic_year_id FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if (!$existing) {
            $conn->close();
            jsonError('ไม่พบปีการศึกษาที่ต้องการแก้ไข');
        }
        
        // ถ้าตั้งเป็นปีปัจจุบัน ให้อัปเดตปีอื่นเป็น false ก่อน
        if ($is_current) {
            $update_sql = "UPDATE academic_years SET is_current = 0";
            if (!$conn->query($update_sql)) {
                throw new Exception('ไม่สามารถอัปเดตปีการศึกษาปัจจุบันได้: ' . $conn->error);
            }
        }
        
        // อัปเดตปีการศึกษา
        $update_sql = "UPDATE academic_years SET academic_year = ?, semester = ?, start_date = ?, end_date = ?, is_active = ?, is_current = ? WHERE academic_year_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iissiii", $academic_year, $semester, $start_date, $end_date, $is_active, $is_current, $academic_year_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถอัปเดตข้อมูลได้: ' . $stmt->error);
        }
        
        $conn->close();
        
        jsonSuccess('แก้ไขปีการศึกษาสำเร็จ', [
            'academic_year_id' => $academic_year_id,
            'academic_year' => $academic_year,
            'semester' => $semester
        ]);
        
    } catch (Exception $e) {
        error_log(" Error updating academic year: " . $e->getMessage());
        jsonError($e->getMessage());
    }
}

/**
 * ลบปีการศึกษา
 */
function deleteAcademicYear() {
    $academic_year_id = intval($_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0);

    if (empty($academic_year_id)) {
        jsonError('ไม่พบรหัสปีการศึกษา');
    }

    try {
        $conn = connectDatabase();
        
        // ตรวจสอบว่าปีการศึกษาอยู่หรือไม่
        $check_sql = "SELECT * FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if (!$existing) {
            $conn->close();
            jsonError('ไม่พบปีการศึกษาที่ต้องการลบ');
        }
        
        // ตรวจสอบว่าเป็นปีปัจจุบันหรือไม่
        if ($existing['is_current'] == 1) {
            $conn->close();
            jsonError('ไม่สามารถลบปีการศึกษาปัจจุบันได้');
        }
        
        // ตรวจสอบว่ามีตารางสอนในปีนี้หรือไม่
        $schedule_check = "SELECT COUNT(*) as schedule_count FROM teaching_schedules WHERE academic_year_id = ?";
        $stmt = $conn->prepare($schedule_check);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $schedule_result = $stmt->get_result()->fetch_assoc();
        
        if ($schedule_result['schedule_count'] > 0) {
            $conn->close();
            jsonError('ไม่สามารถลบปีการศึกษาได้ เนื่องจากมีตารางสอนในปีนี้');
        }
        
        // ลบปีการศึกษา
        $delete_sql = "DELETE FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $academic_year_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถลบปีการศึกษาได้: ' . $stmt->error);
        }
        
        $conn->close();
        
        jsonSuccess('ลบปีการศึกษาสำเร็จ', [
            'deleted_academic_year_id' => $academic_year_id
        ]);
        
    } catch (Exception $e) {
        error_log(" Error deleting academic year: " . $e->getMessage());
        jsonError($e->getMessage());
    }
}

/**
 * ตั้งค่าปีการศึกษาปัจจุบัน
 */
function setCurrentAcademicYear() {
    $academic_year_id = intval($_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0);

    if (empty($academic_year_id)) {
        jsonError('ไม่พบรหัสปีการศึกษา');
    }

    try {
        $conn = connectDatabase();
        
        // ตรวจสอบว่าปีการศึกษาอยู่หรือไม่
        $check_sql = "SELECT * FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if (!$existing) {
            $conn->close();
            jsonError('ไม่พบปีการศึกษาที่ต้องการตั้งค่า');
        }
        
        // อัปเดตปีการศึกษาปัจจุบัน
        $conn->begin_transaction();
        
        // รีเซ็ตปีการศึกษาปัจจุบันทั้งหมด
        $reset_sql = "UPDATE academic_years SET is_current = 0";
        if (!$conn->query($reset_sql)) {
            $conn->rollback();
            throw new Exception('ไม่สามารถรีเซ็ตปีการศึกษาปัจจุบันได้: ' . $conn->error);
        }
        
        // ตั้งค่าปีการศึกษาใหม่
        $update_sql = "UPDATE academic_years SET is_current = 1 WHERE academic_year_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $academic_year_id);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            throw new Exception('ไม่สามารถตั้งค่าปีการศึกษาปัจจุบันได้: ' . $stmt->error);
        }
        
        $conn->commit();
        $conn->close();
        
        jsonSuccess('ตั้งค่าปีการศึกษาปัจจุบันสำเร็จ', [
            'current_academic_year_id' => $academic_year_id,
            'academic_year' => $existing['academic_year'],
            'semester' => $existing['semester']
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
            $conn->close();
        }
        error_log(" Error setting current academic year: " . $e->getMessage());
        jsonError($e->getMessage());
    }
}
?>