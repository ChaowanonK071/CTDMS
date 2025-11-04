<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // บันทึก log การออกจากระบบ
    if (isset($_SESSION['username']) && isset($_SESSION['user_id'])) {
        $logMessage = "User logout: " . $_SESSION['username'] . 
                     " (ID: " . $_SESSION['user_id'] . 
                     ", Type: " . ($_SESSION['user_type'] ?? 'unknown') .
                     ", Method: " . ($_SESSION['login_method'] ?? 'unknown') . 
                     ")";
        error_log($logMessage);
    }
    
    // ทำลาย session
    session_unset();
    session_destroy();
    
    // ลบ session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // ส่ง response สำเร็จ
    echo json_encode([
        'status' => 'success',
        'message' => 'ออกจากระบบเรียบร้อยแล้ว'
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
?>