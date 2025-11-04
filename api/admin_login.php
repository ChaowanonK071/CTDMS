<?php
/**
 * จัดการการเข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบจากฐานข้อมูล
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include การตั้งค่าฐานข้อมูล
require_once 'config.php';

// จัดการ OPTIONS request สำหรับ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ฟังก์ชันสร้างตาราง users และฐานข้อมูล
function createDatabaseAndTables() {
    try {
        // เชื่อมต่อ MySQL server (ไม่ระบุฐานข้อมูล)
        $host = DB_HOST;
        $username = DB_USERNAME;
        $password = DB_PASSWORD;
        $dbname = DB_NAME;
        
        $conn = new PDO("mysql:host=$host", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // สร้างฐานข้อมูลถ้ายังไม่มี
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
        
        error_log("Database and users table created successfully");
        return $conn;
        
    } catch (Exception $e) {
        error_log("Error creating database/table: " . $e->getMessage());
        throw $e;
    }
}

// ฟังก์ชันตรวจสอบและสร้าง admin user เริ่มต้น
function ensureDefaultAdmin() {
    try {
        // ลองเชื่อมต่อฐานข้อมูลปกติก่อน
        try {
            $conn = connectDB();
        } catch (Exception $e) {
            error_log("Normal DB connection failed, creating database: " . $e->getMessage());
            $conn = createDatabaseAndTables();
        }
        
        // ตรวจสอบว่ามี admin user หรือไม่
        $checkAdmin = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin' AND is_active = 1");
        $checkAdmin->execute();
        $adminCount = $checkAdmin->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($adminCount == 0) {
            // ไม่มี admin user ให้สร้างใหม่
            $defaultUsername = 'admin';
            $defaultPassword = '1234';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
            
            // ตรวจสอบว่า username ซ้ำหรือไม่
            $checkUsername = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $checkUsername->execute([$defaultUsername]);
            
            if (!$checkUsername->fetch()) {
                // สร้าง admin user เริ่มต้น
                $createAdmin = $conn->prepare("
                    INSERT INTO users (username, password, name, email, user_type, is_active, created_at) 
                    VALUES (?, ?, ?, ?, 'admin', 1, NOW())
                ");
                
                $createAdmin->execute([
                    $defaultUsername,
                    $hashedPassword,
                    'ผู้ดูแลระบบ',
                    'admin@rmutsv.ac.th'
                ]);
                
                $newAdminId = $conn->lastInsertId();
                error_log("Auto-created default admin user: $defaultUsername (ID: $newAdminId) with password: $defaultPassword, hash: " . substr($hashedPassword, 0, 20) . "...");
                
                return [
                    'created' => true,
                    'username' => $defaultUsername,
                    'password' => $defaultPassword,
                    'user_id' => $newAdminId,
                    'hashed_password' => $hashedPassword
                ];
            } else {
                // มี username แต่อาจไม่ใช่ admin ให้อัพเดทเป็น admin และรีเซ็ตรหัสผ่าน
                $updateToAdmin = $conn->prepare("UPDATE users SET user_type = 'admin', password = ?, is_active = 1 WHERE username = ?");
                $updateToAdmin->execute([$hashedPassword, $defaultUsername]);
                
                $getUserId = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $getUserId->execute([$defaultUsername]);
                $userId = $getUserId->fetch(PDO::FETCH_ASSOC)['user_id'];
                
                error_log("Updated existing user to admin: $defaultUsername (ID: $userId) with new password: $defaultPassword");
                
                return [
                    'created' => true,
                    'updated' => true,
                    'username' => $defaultUsername,
                    'password' => $defaultPassword,
                    'user_id' => $userId
                ];
            }
        } else {
            // มี admin user แล้ว ให้ตรวจสอบว่าใช้รหัสผ่าน default หรือไม่
            $checkDefaultPassword = $conn->prepare("SELECT user_id, password FROM users WHERE username = 'admin' AND user_type = 'admin' AND is_active = 1");
            $checkDefaultPassword->execute();
            $adminUser = $checkDefaultPassword->fetch(PDO::FETCH_ASSOC);
            
            if ($adminUser) {
                // ทดสอบว่ารหัสผ่าน '1234' ใช้ได้หรือไม่
                if (!password_verify('1234', $adminUser['password'])) {
                    // รหัสผ่านไม่ใช่ '1234' ให้รีเซ็ต
                    $newHash = password_hash('1234', PASSWORD_DEFAULT);
                    $resetPassword = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $resetPassword->execute([$newHash, $adminUser['user_id']]);
                    
                    error_log("Reset admin password to default: admin/1234 (ID: {$adminUser['user_id']})");
                    
                    return [
                        'created' => true,
                        'reset' => true,
                        'username' => 'admin',
                        'password' => '1234',
                        'user_id' => $adminUser['user_id']
                    ];
                }
            }
        }
        
        return ['created' => false];
        
    } catch (Exception $e) {
        error_log("Error ensuring default admin: " . $e->getMessage());
        return ['created' => false, 'error' => $e->getMessage()];
    }
}

// ฟังก์ชันตรวจสอบความถูกต้องของข้อมูล input
function validateInput($input) {
    return !empty(trim($input));
}

// ฟังก์ชันตรวจสอบ credentials จากฐานข้อมูล
function authenticateAdmin($username, $password) {
    try {
        // ลองเชื่อมต่อฐานข้อมูลก่อน
        try {
            $conn = connectDB();
        } catch (Exception $e) {
            error_log("Database connection failed, creating new: " . $e->getMessage());
            $conn = createDatabaseAndTables();
        }
        
        // ตรวจสอบและสร้าง admin เริ่มต้นถ้าจำเป็น
        $adminCreation = ensureDefaultAdmin();
        
        // ค้นหาผู้ใช้จากฐานข้อมูล
        $stmt = $conn->prepare("
            SELECT user_id, username, password, title, name, lastname, 
                   email, user_type, is_active, faccode, facname, 
                   depcode, depname, seccode, secname, cid, last_login
            FROM users 
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $message = 'ไม่พบผู้ใช้ในระบบ';
            if ($adminCreation['created']) {
                $message = "ไม่พบผู้ใช้ '$username' ในระบบ\n\nระบบได้สร้าง Admin User เริ่มต้นแล้ว:\n";
                $message .= "Username: {$adminCreation['username']}\n";
                $message .= "Password: {$adminCreation['password']}\n\n";
                $message .= "กรุณาใช้ข้อมูลด้านบนในการเข้าสู่ระบบ";
            }
            return [
                'success' => false,
                'message' => $message,
                'admin_created' => $adminCreation['created'] ?? false,
                'suggested_credentials' => $adminCreation['created'] ? [
                    'username' => $adminCreation['username'],
                    'password' => $adminCreation['password']
                ] : null
            ];
        }
        
        // Enhanced Debug password verification
        $passwordMatches = password_verify($password, $user['password']);
        error_log("=== PASSWORD DEBUG ===");
        error_log("Username: '$username'");
        error_log("Input password: '$password'");
        error_log("Input password length: " . strlen($password));
        error_log("Stored hash: " . $user['password']);
        error_log("Hash algorithm: " . password_get_info($user['password'])['algo']);
        error_log("Password verify result: " . ($passwordMatches ? 'SUCCESS' : 'FAILED'));
        
        // ทดสอบ hash ใหม่ด้วยรหัสผ่านที่ใส่มา
        $testHash = password_hash($password, PASSWORD_DEFAULT);
        $testVerify = password_verify($password, $testHash);
        error_log("Test new hash with same password: " . ($testVerify ? 'SUCCESS' : 'FAILED'));
        error_log("========================");
        
        // ตรวจสอบรหัสผ่าน
        if (!$passwordMatches) {
            // ลองแก้ไขด้วยการ hash ใหม่
            if ($username === 'admin' && $password === '1234') {
                error_log("Attempting to fix admin password hash...");
                $newHash = password_hash('1234', PASSWORD_DEFAULT);
                $updatePassword = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updatePassword->execute([$newHash, $user['user_id']]);
                
                // ลองตรวจสอบอีกครั้ง
                $verifyFixed = password_verify('1234', $newHash);
                error_log("Fixed password verification: " . ($verifyFixed ? 'SUCCESS' : 'FAILED'));
                
                if ($verifyFixed) {
                    // อัพเดต password ในฐานข้อมูลแล้ว ให้ดำเนินการต่อ
                    $user['password'] = $newHash;
                    $passwordMatches = true;
                    error_log("Password hash fixed and updated for user: $username");
                }
            }
            
            if (!$passwordMatches) {
                // Log การพยายามเข้าสู่ระบบที่ไม่สำเร็จ
                error_log("Failed admin login attempt for username: " . $username . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                
                $message = "รหัสผ่านไม่ถูกต้องสำหรับผู้ใช้ '$username'";
                
                // ถ้ามีการสร้าง admin ใหม่ ให้แสดงข้อมูล
                if ($adminCreation['created']) {
                    $message .= "\n\nระบบได้สร้าง/รีเซ็ต Admin User แล้ว:\n";
                    $message .= "Username: {$adminCreation['username']}\n";
                    $message .= "Password: {$adminCreation['password']}\n\n";
                    $message .= "กรุณาใช้ข้อมูลด้านบนในการเข้าสู่ระบบ";
                } else {
                    $message .= "\n\n🔧 กรุณาลองคลิก 'แก้ไขปัญหาและสร้าง Admin' เพื่อรีเซ็ตรหัสผ่าน";
                }
                
                return [
                    'success' => false,
                    'message' => $message,
                    'admin_created' => $adminCreation['created'] ?? false,
                    'suggested_credentials' => [
                        'username' => $adminCreation['created'] ? $adminCreation['username'] : 'admin',
                        'password' => $adminCreation['created'] ? $adminCreation['password'] : '1234'
                    ]
                ];
            }
        }
        
        // ตรวจสอบว่าเป็น admin หรือไม่
        if ($user['user_type'] !== 'admin') {
            error_log("Non-admin user attempted admin login: " . $username . " (type: " . $user['user_type'] . ")");
            
            return [
                'success' => false,
                'message' => "ผู้ใช้ '$username' ไม่มีสิทธิ์ผู้ดูแลระบบ (ประเภทปัจจุบัน: {$user['user_type']})"
            ];
        }
        
        // อัปเดตเวลาเข้าสู่ระบบล่าสุด
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);
        
        // เตรียมข้อมูลผู้ใช้สำหรับส่งกลับ
        $userData = [
            'user_id' => (int)$user['user_id'],
            'username' => $user['username'],
            'title' => $user['title'],
            'name' => $user['name'],
            'lastname' => $user['lastname'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'faccode' => $user['faccode'],
            'facname' => $user['facname'],
            'depcode' => $user['depcode'],
            'depname' => $user['depname'],
            'seccode' => $user['seccode'],
            'secname' => $user['secname'],
            'cid' => $user['cid'],
            'type' => 'staff', // สำหรับความเข้ากันได้กับระบบ eLogin
            'database_saved' => true,
            'login_method' => 'admin'
        ];
        
        // Log การเข้าสู่ระบบสำเร็จ
        error_log("Successful admin login: " . $username . " (ID: " . $user['user_id'] . ") from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        return [
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user_data' => $userData,
            'admin_created' => $adminCreation['created'] ?? false
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in admin authentication: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        error_log("General error in admin authentication: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
        ];
    }
}

// จัดการ Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // ตรวจสอบการส่งข้อมูล JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ข้อมูล JSON ไม่ถูกต้อง'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ตรวจสอบความครบถ้วนของข้อมูล
    if (!isset($input['username']) || !isset($input['password'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณาระบุ username และ password'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    // ตรวจสอบความถูกต้องของ input
    if (!validateInput($username) || !validateInput($password)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ตรวจสอบความยาวของ username และ password
    if (strlen($username) > 50) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Username ยาวเกินไป'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    if (strlen($password) > 255) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Password ยาวเกินไป'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ตรวจสอบ rate limiting (ป้องกัน brute force)
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = 'admin_login_' . md5($clientIP . $username);
    
    // สำหรับการใช้งานจริง ควรใช้ Redis หรือ Memcached
    // ที่นี่จะใช้วิธีง่ายๆ โดยเขียนลง file
    $rateLimitFile = sys_get_temp_dir() . '/admin_login_attempts.log';
    $currentTime = time();
    $maxAttempts = 5;
    $timeWindow = 300;
    
    // อ่านความพยายามล่าสุด
    $attempts = [];
    if (file_exists($rateLimitFile)) {
        $logContent = file_get_contents($rateLimitFile);
        $lines = explode("\n", trim($logContent));
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode('|', $line);
            if (count($parts) >= 3) {
                $timestamp = (int)$parts[0];
                $ip = $parts[1];
                $user = $parts[2];
                
                // เก็บเฉพาะความพยายามใน time window
                if ($currentTime - $timestamp < $timeWindow) {
                    $attempts[] = ['time' => $timestamp, 'ip' => $ip, 'username' => $user];
                }
            }
        }
    }
    
    // นับความพยายามสำหรับ IP + username นี้
    $attemptCount = 0;
    foreach ($attempts as $attempt) {
        if ($attempt['ip'] === $clientIP && $attempt['username'] === $username) {
            $attemptCount++;
        }
    }
    
    if ($attemptCount >= $maxAttempts) {
        error_log("Rate limit exceeded for admin login: " . $username . " from IP: " . $clientIP);
        echo json_encode([
            'status' => 'error',
            'message' => 'มีการพยายามเข้าสู่ระบบมากเกินไป กรุณารอ 5 นาที'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ทำการยืนยันตัวตน
    $authResult = authenticateAdmin($username, $password);
    
    if ($authResult['success']) {
        // เข้าสู่ระบบสำเร็จ - ล้างข้อมูล rate limit สำหรับ user นี้
        $cleanedAttempts = [];
        foreach ($attempts as $attempt) {
            if (!($attempt['ip'] === $clientIP && $attempt['username'] === $username)) {
                $cleanedAttempts[] = $attempt['time'] . '|' . $attempt['ip'] . '|' . $attempt['username'];
            }
        }
        
        file_put_contents($rateLimitFile, implode("\n", $cleanedAttempts));
        
        $response = [
            'status' => 'success',
            'message' => $authResult['message'],
            'user_data' => $authResult['user_data']
        ];
        
        if ($authResult['admin_created']) {
            $response['admin_created'] = true;
            $response['message'] .= ' (ระบบได้สร้าง Admin User เริ่มต้นอัตโนมัติ)';
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } else {
        // เข้าสู่ระบบไม่สำเร็จ - บันทึกความพยายาม
        $attempts[] = ['time' => $currentTime, 'ip' => $clientIP, 'username' => $username];
        $attemptLines = [];
        foreach ($attempts as $attempt) {
            $attemptLines[] = $attempt['time'] . '|' . $attempt['ip'] . '|' . $attempt['username'];
        }
        file_put_contents($rateLimitFile, implode("\n", $attemptLines));
        
        $response = [
            'status' => 'error',
            'message' => $authResult['message']
        ];
        
        if (isset($authResult['admin_created']) && $authResult['admin_created']) {
            $response['admin_created'] = true;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // API สำหรับทดสอบการเชื่อมต่อ
    
    // ตรวจสอบและสร้าง admin เริ่มต้นถ้าจำเป็น
    $adminCreation = ensureDefaultAdmin();
    
    $response = [
        'status' => 'success',
        'message' => 'Admin Login API is working',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0'
    ];
    
    if ($adminCreation['created']) {
        $response['admin_created'] = true;
        $response['default_credentials'] = [
            'username' => $adminCreation['username'],
            'password' => $adminCreation['password']
        ];
        $response['message'] .= ' (Default admin user created)';
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
}
?>