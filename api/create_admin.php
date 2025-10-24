<?php
/**
 * รองรับการสร้างบัญชีใหม่และการอัพเดทรหัสผ่าน
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include การตั้งค่าฐานข้อมูล
require_once 'config.php';

// จัดการ OPTIONS request สำหรับ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
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
    
    // ตรวจสอบข้อมูลที่จำเป็น
    $required = ['username', 'password', 'name'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            echo json_encode([
                'status' => 'error',
                'message' => "กรุณากรอก {$field}"
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
    
    try {
        // ลองเชื่อมต่อฐานข้อมูลปกติก่อน
        try {
            $conn = connectDB();
        } catch (Exception $e) {
            // สร้างฐานข้อมูลและตารางใหม่
            $host = DB_HOST;
            $username = DB_USERNAME;
            $password = DB_PASSWORD;
            $dbname = DB_NAME;
            
            $conn = new PDO("mysql:host=$host", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->exec("USE `$dbname`");
            
            // สร้างตาราง users
            $createTableSQL = "
            CREATE TABLE IF NOT EXISTS users (
                user_id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                title VARCHAR(20),
                name VARCHAR(100) NOT NULL,
                lastname VARCHAR(100),
                email VARCHAR(100),
                cid VARCHAR(13),
                elogin_token TEXT,
                faccode VARCHAR(10),
                facname VARCHAR(100),
                depcode VARCHAR(10),
                depname VARCHAR(100),
                seccode VARCHAR(10),
                secname VARCHAR(100),
                user_type ENUM('admin', 'teacher', 'student') DEFAULT 'teacher',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                INDEX idx_username (username),
                INDEX idx_user_type (user_type),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $conn->exec($createTableSQL);
        }
        
        // ตรวจสอบว่ามี username นี้อยู่แล้วหรือไม่
        $checkStmt = $conn->prepare("SELECT user_id, user_type FROM users WHERE username = ?");
        $checkStmt->execute([$input['username']]);
        $existingUser = $checkStmt->fetch();
        
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        error_log("Creating admin with password: '{$input['password']}' and hash: " . substr($hashedPassword, 0, 20) . "...");
        
        if ($existingUser) {
            // มีผู้ใช้อยู่แล้ว - อัพเดทข้อมูล
            if (isset($input['force_update']) && $input['force_update']) {
                $updateStmt = $conn->prepare("
                    UPDATE users SET 
                        password = ?, 
                        name = ?, 
                        email = ?, 
                        user_type = 'admin', 
                        is_active = 1,
                        last_login = NOW()
                    WHERE username = ?
                ");
                
                $updateStmt->execute([
                    $hashedPassword,
                    $input['name'],
                    $input['email'] ?? '',
                    $input['username']
                ]);
                
                // ทดสอบรหัสผ่านที่อัพเดทแล้ว
                $verifyTest = password_verify($input['password'], $hashedPassword);
                error_log("Password verification test after update: " . ($verifyTest ? 'SUCCESS' : 'FAILED'));
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'อัพเดท Admin User สำเร็จ',
                    'user_id' => (int)$existingUser['user_id'],
                    'action' => 'updated',
                    'user_data' => [
                        'username' => $input['username'],
                        'name' => $input['name'],
                        'email' => $input['email'] ?? '',
                        'user_type' => 'admin'
                    ],
                    'password_test' => $verifyTest
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Username นี้มีอยู่ในระบบแล้ว (ใช้ force_update: true เพื่อบังคับอัพเดท)'
                ], JSON_UNESCAPED_UNICODE);
            }
            exit();
        }
        
        // เพิ่ม admin user ใหม่
        $insertStmt = $conn->prepare("
            INSERT INTO users (username, password, name, email, user_type, is_active, created_at) 
            VALUES (?, ?, ?, ?, 'admin', 1, NOW())
        ");
        
        $insertStmt->execute([
            $input['username'],
            $hashedPassword,
            $input['name'],
            $input['email'] ?? ''
        ]);
        
        $userId = $conn->lastInsertId();
        
        // ทดสอบรหัสผ่านที่สร้างใหม่
        $verifyTest = password_verify($input['password'], $hashedPassword);
        error_log("Password verification test after creation: " . ($verifyTest ? 'SUCCESS' : 'FAILED'));
        
        // Log การสร้าง admin
        error_log("Admin user created: {$input['username']} (ID: {$userId}) from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'สร้าง Admin User สำเร็จ',
            'user_id' => (int)$userId,
            'action' => 'created',
            'user_data' => [
                'username' => $input['username'],
                'name' => $input['name'],
                'email' => $input['email'] ?? '',
                'user_type' => 'admin'
            ],
            'password_test' => $verifyTest
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        error_log("Create admin error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการสร้าง Admin User: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
?>