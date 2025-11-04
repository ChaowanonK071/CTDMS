<?php
// เริ่ม session ถ้ายังไม่เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ตรวจสอบการเข้าสู่ระบบ
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) &&
           isset($_SESSION['user_type']) && !empty($_SESSION['user_type']);
}

function requireLogin($redirectPage = 'login.php') {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // ถ้าเป็น AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน',
                'redirect' => $redirectPage
            ]);
            exit;
        }
        
        // ถ้าเป็น request ปกติ
        header("Location: $redirectPage");
        exit;
    }
}
/**
 * ดึงข้อมูลผู้ใช้จาก session
 */
function getUserData() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'user_type' => $_SESSION['user_type'] ?? 'teacher',
        'title' => $_SESSION['title'] ?? 'อาจารย์',
        'name' => $_SESSION['name'] ?? 'ผู้ใช้',
        'lastname' => $_SESSION['lastname'] ?? ''
    ];
}

/**
 * ตรวจสอบสิทธิ์ admin
 */
function requireAdmin() {
    requireLogin();
    
    if ($_SESSION['user_type'] !== 'admin') {
        // สำหรับ AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        header("Location: ../login.php");
        exit;
    }
}

/**
 * ตรวจสอบว่าเป็น teacher หรือ admin
 */
function requireTeacherOrAdmin() {
    requireLogin();
    
    if (!in_array($_SESSION['user_type'], ['teacher', 'admin'])) {
        // สำหรับ AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        header("Location: ../access_denied.php");
        exit;
    }
}

/**
 * ล้าง session และ logout
 */
function logout() {
    session_destroy();
    header("Location: ../login.php");
    exit;
}
?>