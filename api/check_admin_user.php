<?php
/**
 * ใช้สำหรับการทดสอบและ debug
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $conn = connectDB();
        
        // ตรวจสอบว่ามีตาราง users หรือไม่
        $tablesQuery = $conn->query("SHOW TABLES LIKE 'users'");
        $tableExists = $tablesQuery->rowCount() > 0;
        
        if (!$tableExists) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ตาราง users ไม่พบในฐานข้อมูล',
                'table_exists' => false,
                'suggestions' => [
                    'กรุณาสร้างตาราง users ในฐานข้อมูล',
                    'หรือตรวจสอบการเชื่อมต่อฐานข้อมูล'
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        // ตรวจสอบโครงสร้างตาราง
        $structureQuery = $conn->query("DESCRIBE users");
        $columns = $structureQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // ค้นหา admin users
        $adminQuery = $conn->prepare("
            SELECT user_id, username, title, name, lastname, email, 
                   user_type, is_active, last_login, facname, depname, secname
            FROM users 
            WHERE user_type = 'admin'
            ORDER BY user_id
        ");
        $adminQuery->execute();
        $adminUsers = $adminQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // นับจำนวนผู้ใช้แต่ละประเภท
        $countQuery = $conn->query("
            SELECT user_type, COUNT(*) as count, 
                   SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
            FROM users 
            GROUP BY user_type
        ");
        $userTypeCounts = $countQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // ตรวจสอบว่ามี admin user หรือไม่
        $hasAdminUser = count($adminUsers) > 0;
        
        $result = [
            'status' => 'success',
            'message' => 'ตรวจสอบฐานข้อมูลเรียบร้อย',
            'database_info' => [
                'table_exists' => true,
                'total_columns' => count($columns),
                'has_admin_users' => $hasAdminUser,
                'admin_count' => count($adminUsers)
            ],
            'table_structure' => array_map(function($col) {
                return [
                    'field' => $col['Field'],
                    'type' => $col['Type'],
                    'null' => $col['Null'],
                    'key' => $col['Key'],
                    'default' => $col['Default']
                ];
            }, $columns),
            'user_type_summary' => $userTypeCounts,
            'admin_users' => $adminUsers
        ];
        
        if (!$hasAdminUser) {
            $result['suggestions'] = [
                'ไม่พบผู้ใช้ประเภท admin ในฐานข้อมูล',
                'กรุณาสร้าง admin user ด้วยคำสั่ง SQL:',
                "INSERT INTO users (username, password, name, user_type, is_active) VALUES ('admin', '" . password_hash('1234', PASSWORD_DEFAULT) . "', 'ผู้ดูแลระบบ', 'admin', 1)"
            ];
            $result['sample_sql'] = [
                'create_admin' => "INSERT INTO users (username, password, name, user_type, is_active) VALUES ('admin', '" . password_hash('1234', PASSWORD_DEFAULT) . "', 'ผู้ดูแลระบบ', 'admin', 1);",
                'update_existing' => "UPDATE users SET user_type = 'admin' WHERE username = 'your_username';"
            ];
        }
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage(),
            'error_code' => $e->getCode(),
            'suggestions' => [
                'ตรวจสอบการตั้งค่าฐานข้อมูลในไฟล์ config.php',
                'ตรวจสอบว่า MySQL Server ทำงานอยู่',
                'ตรวจสอบชื่อฐานข้อมูล username และ password'
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
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
