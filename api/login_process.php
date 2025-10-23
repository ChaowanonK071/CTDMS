<?php
/**
 * login_process.php - จัดการการล็อกอินจากหน้า frontend
 * รองรับทั้ง eLogin และ Admin login
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ฟังก์ชันกำหนดประเภทผู้ใช้
function determineUserType($userData) {
    $userType = 'teacher'; // default
    $isTemporaryAccess = false;
    
    // ถ้าเป็น admin login ให้ใช้ user_type จากฐานข้อมูล
    if (isset($userData['login_method']) && $userData['login_method'] === 'admin') {
        return [
            'user_type' => $userData['user_type'], // จะเป็น 'admin' จากฐานข้อมูล
            'is_temporary_access' => false,
            'original_user_type' => $userData['user_type']
        ];
    }
    
    // *** สำหรับ eLogin ให้ตรวจสอบ user_type จากฐานข้อมูลก่อน ***
    if (isset($userData['user_type'])) {
        // ใช้ user_type จากฐานข้อมูลที่ส่งมาจาก eLogin API
        $userType = $userData['user_type'];
        error_log("Using user_type from database via eLogin: " . $userType);
        
        // ตรวจสอบว่าเป็น student ที่ได้รับสิทธิ์ teacher ชั่วคราวหรือไม่
        if (isset($userData['type']) && $userData['type'] === 'student' && $userType === 'teacher') {
            $isTemporaryAccess = true;
        }
        
        return [
            'user_type' => $userType,
            'is_temporary_access' => $isTemporaryAccess,
            'original_user_type' => $userData['type'] ?? 'unknown'
        ];
    }
    
    // ถ้าไม่มี user_type จากฐานข้อมูล ให้ใช้วิธีเดิม
    if (isset($userData['type'])) {
        if ($userData['type'] === 'staff') {
            // ตรวจสอบว่าเป็น admin หรือไม่จากหน่วยงาน
            $depname = strtolower($userData['depname'] ?? '');
            $secname = strtolower($userData['secname'] ?? '');
            
            if (strpos($depname, 'admin') !== false || 
                strpos($secname, 'admin') !== false ||
                strpos($depname, 'ผู้บริหาร') !== false ||
                strpos($secname, 'ผู้บริหาร') !== false ||
                strpos($depname, 'บริหาร') !== false) {
                $userType = 'admin';
            } else {
                $userType = 'teacher';
            }
        } elseif ($userData['type'] === 'student') {
            // ให้สิทธิ์ teacher ชั่วคราวแก่ student
            $userType = 'teacher';
            $isTemporaryAccess = true;
        }
    }
    
    return [
        'user_type' => $userType,
        'is_temporary_access' => $isTemporaryAccess,
        'original_user_type' => $userData['type'] ?? 'unknown'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ข้อมูล JSON ไม่ถูกต้อง'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ตรวจสอบว่ามีข้อมูลจาก login หรือไม่
    if (!isset($input['user_data'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลผู้ใช้'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $userData = $input['user_data'];
    
    // ตรวจสอบข้อมูลที่จำเป็น
    if (!isset($userData['username'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบ username ในข้อมูลผู้ใช้'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ใช้ user_id ถ้ามี หรือใช้ 0 เป็นค่าเริ่มต้น
    $userId = isset($userData['user_id']) ? (int)$userData['user_id'] : 0;
    
    // กำหนดประเภทผู้ใช้
    $userTypeInfo = determineUserType($userData);
    $userType = $userTypeInfo['user_type'];
    $isTemporaryAccess = $userTypeInfo['is_temporary_access'];
    $originalUserType = $userTypeInfo['original_user_type'];
    
    // บันทึกข้อมูลใน session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $userData['username'];
    $_SESSION['user_type'] = $userType;
    $_SESSION['original_user_type'] = $originalUserType;
    $_SESSION['is_temporary_access'] = $isTemporaryAccess;
    $_SESSION['login_method'] = $userData['login_method'] ?? 'elogin';
    
    // ข้อมูลส่วนบุคคล
    $_SESSION['name'] = $userData['name'] ?? '';
    $_SESSION['title'] = $userData['title'] ?? '';
    $_SESSION['lastname'] = $userData['lastname'] ?? '';
    $_SESSION['email'] = $userData['email'] ?? '';
    $_SESSION['facname'] = $userData['facname'] ?? '';
    $_SESSION['depname'] = $userData['depname'] ?? '';
    $_SESSION['secname'] = $userData['secname'] ?? '';
    $_SESSION['faccode'] = $userData['faccode'] ?? '';
    $_SESSION['depcode'] = $userData['depcode'] ?? '';
    $_SESSION['seccode'] = $userData['seccode'] ?? '';
    $_SESSION['cid'] = $userData['cid'] ?? '';
    $_SESSION['elogin_token'] = $userData['token'] ?? '';
    $_SESSION['database_saved'] = $userData['database_saved'] ?? false;
    
    // ข้อมูลเวลา
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    
    // ข้อมูลความปลอดภัย
    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['session_id'] = session_id();
    
    // สำหรับความเข้ากันได้เก่า
    if ($isTemporaryAccess) {
        $_SESSION['temp_teacher'] = true;
    }
    
    // Log การเข้าสู่ระบบ
    $logMessage = "User logged in: " . $userData['username'] . " (ID: " . $userId . ", Type: " . $userType;
    if ($isTemporaryAccess) {
        $logMessage .= ", Originally: " . $originalUserType . ", Temporary access";
    }
    $logMessage .= ", Method: " . ($_SESSION['login_method'] ?? 'unknown') . ")";
    error_log($logMessage);
    
    // เตรียม response message
    $responseMessage = 'เข้าสู่ระบบสำเร็จ';
    if ($isTemporaryAccess) {
        $responseMessage .= ' (สิทธิ์ชั่วคราว)';
    }
    if (isset($userData['login_method']) && $userData['login_method'] === 'admin') {
        $responseMessage .= ' - ระดับผู้ดูแลระบบ';
    }
    
    // ส่งข้อมูลกลับ
    echo json_encode([
        'status' => 'success',
        'message' => $responseMessage,
        'user_data' => [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'user_type' => $_SESSION['user_type'],
            'original_user_type' => $_SESSION['original_user_type'],
            'is_temporary_access' => $_SESSION['is_temporary_access'],
            'login_method' => $_SESSION['login_method'],
            'name' => $_SESSION['name'],
            'title' => $_SESSION['title'],
            'lastname' => $_SESSION['lastname'],
            'email' => $_SESSION['email'],
            'facname' => $_SESSION['facname'],
            'depname' => $_SESSION['depname'],
            'secname' => $_SESSION['secname'],
            'database_saved' => $_SESSION['database_saved'],
            'temp_teacher' => $_SESSION['temp_teacher'] ?? false
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
?>