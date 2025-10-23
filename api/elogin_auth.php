<?php
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

// ฟังก์ชันแยกคำนำหน้าชื่อจากชื่อเต็ม
function extractTitle($fullName) {
    $titles = ['นาย', 'นาง', 'นางสาว', 'ดร.', 'ศ.ดร.', 'รศ.ดร.', 'ผศ.ดร.', 'อาจารย์', 'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์'];
    
    foreach ($titles as $title) {
        if (strpos($fullName, $title) === 0) {
            $nameWithoutTitle = trim(str_replace($title, '', $fullName));
            return ['title' => $title, 'name' => $nameWithoutTitle];
        }
    }
    
    // หากไม่พบคำนำหน้า
    return ['title' => '', 'name' => $fullName];
}

// ฟังก์ชันตรวจสอบและเพิ่มผู้ใช้ใหม่
function createOrUpdateUser($userData) {
    try {
        $conn = connectDB();
        
        // ตรวจสอบว่าผู้ใช้มีอยู่แล้วหรือไม่ โดยใช้ username หรือ cid
        $checkUser = $conn->prepare("SELECT user_id, user_type FROM users WHERE username = ? OR (cid IS NOT NULL AND cid = ?)");
        $checkUser->execute([$userData['username'], $userData['cid'] ?? '']);
        $existingUser = $checkUser->fetch(PDO::FETCH_ASSOC);
        
        // แยกชื่อและนามสกุล
        $titleAndName = extractTitle($userData['name'] ?? '');
        $nameParts = explode(' ', trim($titleAndName['name']), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        // กำหนด user_type จากข้อมูล eLogin (เฉพาะกรณีผู้ใช้ใหม่)
        $userType = 'teacher'; // default
        
        if ($existingUser) {
            // *** ใช้ user_type จากฐานข้อมูลสำหรับผู้ใช้ที่มีอยู่แล้ว ***
            $userType = $existingUser['user_type'];
            error_log("Using existing user_type from database: " . $userType . " for user: " . $userData['username']);
        } else {
            // สำหรับผู้ใช้ใหม่ ให้กำหนดจากข้อมูล eLogin
            if (isset($userData['type'])) {
                if ($userData['type'] === 'staff') {
                    // ตรวจสอบว่าเป็น admin หรือไม่จากหน่วยงาน
                    if (strpos(strtolower($userData['depname'] ?? ''), 'admin') !== false ||
                        strpos(strtolower($userData['secname'] ?? ''), 'admin') !== false ||
                        strpos(strtolower($userData['depname'] ?? ''), 'ผู้บริหาร') !== false) {
                        $userType = 'admin';
                    }
                } elseif ($userData['type'] === 'student') {
                    // ให้สิทธิ์ teacher ชั่วคราวแก่ student
                    $userType = 'teacher';
                }
            }
        }
        
        $isTemporaryAccess = false;
        if (isset($userData['type']) && $userData['type'] === 'student' && $userType === 'teacher') {
            $isTemporaryAccess = true;
            error_log("Temporary teacher access granted to student: " . $userData['username']);
        }
        
        if ($existingUser) {
            // ดึง title เดิมจากฐานข้อมูล
            $getTitle = $conn->prepare("SELECT title FROM users WHERE user_id = ?");
            $getTitle->execute([$existingUser['user_id']]);
            $dbTitle = $getTitle->fetchColumn();

            // อัพเดทข้อมูลผู้ใช้ที่มีอยู่ (ไม่เปลี่ยน title ถ้ามีอยู่แล้ว)
            $updateUser = $conn->prepare("
                UPDATE users SET
                    name = ?,
                    lastname = ?,
                    email = ?,
                    faccode = ?,
                    facname = ?,
                    depcode = ?,
                    depname = ?,
                    seccode = ?,
                    secname = ?,
                    cid = ?,
                    elogin_token = ?
                WHERE user_id = ?
            ");

            $updateUser->execute([
                $firstName,
                $lastName,
                $userData['email'] ?? '',
                $userData['faccode'] ?? '',
                $userData['facname'] ?? '',
                $userData['depcode'] ?? '',
                $userData['depname'] ?? '',
                $userData['seccode'] ?? '',
                $userData['secname'] ?? '',
                $userData['cid'] ?? '',
                $userData['token'] ?? '',
                $existingUser['user_id']
            ]);
            
            error_log("Updated existing user: " . $userData['username'] . " (ID: " . $existingUser['user_id'] . ") with preserved type: " . $userType);
            return $existingUser['user_id'];
        } else {
            // เพิ่มผู้ใช้ใหม่
            $securePassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            
            $insertUser = $conn->prepare("
                INSERT INTO users (
                    username, password, title, name, lastname, cid, email, 
                    elogin_token, faccode, facname, depcode, depname, 
                    seccode, secname, user_type, is_active, last_login
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $insertUser->execute([
                $userData['username'],
                $securePassword,
                $titleAndName['title'],
                $firstName,
                $lastName,
                $userData['cid'] ?? null,
                $userData['email'] ?? '',
                $userData['token'] ?? null,
                $userData['faccode'] ?? null,
                $userData['facname'] ?? null,
                $userData['depcode'] ?? null,
                $userData['depname'] ?? null,
                $userData['seccode'] ?? null,
                $userData['secname'] ?? null,
                $userType
            ]);
            
            $newUserId = $conn->lastInsertId();
            error_log("Created new user: " . $userData['username'] . " (ID: " . $newUserId . ") with type: " . $userType);
            return $newUserId;
        }
    } catch(PDOException $e) {
        error_log("Database error in createOrUpdateUser: " . $e->getMessage());
        throw new Exception('ไม่สามารถบันทึกข้อมูลผู้ใช้ได้: ' . $e->getMessage());
    }
}

// ฟังก์ชันเรียก eLogin API
function callELoginAPI($username, $password) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.rmutsv.ac.th/elogin');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // สำหรับ development
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        return [
            'status' => 'error', 
            'message' => 'ไม่สามารถเชื่อมต่อกับระบบ eLogin ได้: ' . $curlError
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'status' => 'error', 
            'message' => 'ระบบ eLogin ตอบกลับด้วยรหัสข้อผิดพลาด: ' . $httpCode
        ];
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error', 
            'message' => 'ข้อมูลที่ได้รับจากระบบ eLogin ไม่ถูกต้อง'
        ];
    }
    
    return $decoded;
}

// จัดการ Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // ตรวจสอบการส่งข้อมูล JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'status' => 'json', 
            'message' => 'ข้อมูล JSON ไม่ถูกต้อง'
        ]);
        exit();
    }
    
    if (!isset($input['username']) || !isset($input['password'])) {
        echo json_encode([
            'status' => 'json', 
            'message' => 'กรุณาระบุ username และ password'
        ]);
        exit();
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    if (empty($username) || empty($password)) {
        echo json_encode([
            'status' => 'password', 
            'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ]);
        exit();
    }
    
    // เรียก eLogin API
    $eloginResult = callELoginAPI($username, $password);
    
    // Debug: แสดงข้อมูลที่ได้จาก eLogin API
    error_log("eLogin API Response for user " . $username . ": " . json_encode($eloginResult));
    
    if ($eloginResult['status'] === 'ok') {
        try {
            // เพิ่ม username ในกรณีที่ eLogin ไม่ส่งมา
            if (!isset($eloginResult['username'])) {
                $eloginResult['username'] = $username;
            }
            
            // บันทึกข้อมูลผู้ใช้ลงฐานข้อมูล
            $userId = createOrUpdateUser($eloginResult);
            
            // *** ดึง user_type จริงจากฐานข้อมูลหลังจากอัปเดต ***
            $conn = connectDB();
            $getUserType = $conn->prepare("SELECT user_type FROM users WHERE user_id = ?");
            $getUserType->execute([$userId]);
            $dbUserType = $getUserType->fetch(PDO::FETCH_ASSOC);
            
            if ($dbUserType) {
                $eloginResult['user_type'] = $dbUserType['user_type'];
                error_log("Using final user_type from database: " . $dbUserType['user_type'] . " for user: " . $username);
            }
            
            // เพิ่ม user_id ลงใน response
            $eloginResult['user_id'] = (int)$userId;
            $eloginResult['database_saved'] = true;
            
            // Debug: แสดงข้อมูลที่จะส่งกลับ
            error_log("Final response with user_id and correct user_type: " . json_encode($eloginResult));
            
            // Log การเข้าสู่ระบบ (สำหรับ debug)
            error_log("User login successful: " . $username . " (ID: " . $userId . ", Type: " . ($eloginResult['user_type'] ?? 'unknown') . ")");
            
            echo json_encode($eloginResult);
        } catch(Exception $e) {
            // หากบันทึกฐานข้อมูลไม่สำเร็จ แต่ login สำเร็จ
            $eloginResult['user_id'] = null;
            $eloginResult['database_saved'] = false;
            $eloginResult['database_error'] = $e->getMessage();
            
            // เพิ่ม username ในกรณีที่เกิดข้อผิดพลาด
            if (!isset($eloginResult['username'])) {
                $eloginResult['username'] = $username;
            }
            
            // Log ข้อผิดพลาด
            error_log("Database error for user " . $username . ": " . $e->getMessage());
            
            echo json_encode($eloginResult);
        }
    } else {
        // ส่งผลลัพธ์จาก eLogin API ตามเดิม
        echo json_encode($eloginResult);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // API สำหรับทดสอบการเชื่อมต่อ
    echo json_encode([
        'status' => 'ok',
        'message' => 'eLogin Auth API is working',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Method not allowed'
    ]);
}
?>