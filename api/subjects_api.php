<?php
// ไฟล์ API สำหรับจัดการข้อมูลวิชา (Updated with Config)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Include การตั้งค่าฐานข้อมูล
require_once 'config.php';

// รับค่า HTTP method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    exit(0);
}

// ประมวลผลตาม HTTP method
switch ($method) {
    case 'GET':
        // ถ้ามีการส่ง ID มา จะดึงข้อมูลวิชาเฉพาะรายการนั้น
        if (isset($_GET['id'])) {
            getSubject($_GET['id']);
        } else {
            // ไม่มี ID = ดึงข้อมูลทั้งหมด
            getAllSubjects();
        }
        break;
    case 'POST':
        // รับข้อมูล JSON จาก request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        // เพิ่มข้อมูลวิชาใหม่
        createSubject($data);
        break;
    case 'PUT':
        // รับข้อมูล JSON จาก request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        // ตรวจสอบว่ามี ID หรือไม่
        if (!isset($data['subject_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ subject_id']);
            exit;
        }
        
        // อัพเดทข้อมูลวิชา
        updateSubject($data);
        break;
    case 'DELETE':
        // รับข้อมูล JSON จาก request body (กรณีใช้ fetch API)
        $data = json_decode(file_get_contents('php://input'), true);
        
        // ตรวจสอบ ID จาก URL param หรือ request body
        $id = isset($_GET['id']) ? $_GET['id'] : (isset($data['subject_id']) ? $data['subject_id'] : null);
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ subject_id']);
            exit;
        }
        
        // ลบข้อมูลวิชา
        deleteSubject($id);
        break;
    default:
        // HTTP method ไม่รองรับ
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}

// ฟังก์ชันดึงข้อมูลวิชาทั้งหมด
function getAllSubjects() {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT * FROM subjects ORDER BY subject_code");
        $stmt->execute();
        $subjects = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $subjects]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันดึงข้อมูลวิชาตาม ID
function getSubject($id) {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT * FROM subjects WHERE subject_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $subject = $stmt->fetch();
        
        if ($subject) {
            echo json_encode(['status' => 'success', 'data' => $subject]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลวิชา']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันเพิ่มข้อมูลวิชาใหม่
function createSubject($data) {
    // ตรวจสอบข้อมูลที่จำเป็น
    if (!isset($data['subject_code']) || !isset($data['subject_name']) || !isset($data['credits']) || !isset($data['subject_type'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
        return;
    }
    
    try {
        $conn = connectDB();
        
        // ตรวจสอบว่ารหัสวิชาซ้ำหรือไม่
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = :code AND subject_type = :type");
        $stmt->bindParam(':code', $data['subject_code']);
        $stmt->bindParam(':type', $data['subject_type']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'รหัสวิชาและประเภทนี้มีอยู่แล้ว']);
            return;
        }

        // ไม่ซ้ำ สามารถเพิ่มได้
        $stmt = $conn->prepare("
            INSERT INTO subjects (subject_code, subject_name, credits, subject_type) 
            VALUES (:code, :name, :credits, :type)
        ");
        
        $stmt->bindParam(':code', $data['subject_code']);
        $stmt->bindParam(':name', $data['subject_name']);
        $stmt->bindParam(':credits', $data['credits'], PDO::PARAM_INT);
        $stmt->bindParam(':type', $data['subject_type']);
        
        $stmt->execute();
        
        // ดึง ID ล่าสุดที่เพิ่ม
        $newId = $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'เพิ่มข้อมูลวิชาสำเร็จ',
            'data' => ['subject_id' => $newId]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันอัพเดทข้อมูลวิชา
function updateSubject($data) {
    try {
        $conn = connectDB();
        
        // ตรวจสอบว่ามีวิชานี้อยู่หรือไม่
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = :id");
        $stmt->bindParam(':id', $data['subject_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลวิชา']);
            return;
        }
        
        // ตรวจสอบว่ารหัสวิชาและประเภทวิชาซ้ำกับรายการอื่นหรือไม่
        if (isset($data['subject_code']) && isset($data['subject_type'])) {
            $stmt = $conn->prepare("
                SELECT subject_id FROM subjects 
                WHERE subject_code = :code AND subject_type = :type AND subject_id != :id
            ");
            $stmt->bindParam(':code', $data['subject_code']);
            $stmt->bindParam(':type', $data['subject_type']);
            $stmt->bindParam(':id', $data['subject_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                http_response_code(409); // Conflict
                echo json_encode(['status' => 'error', 'message' => 'รหัสวิชาและประเภทนี้มีอยู่แล้ว']);
                return;
            }
        }
        
        // สร้าง SQL สำหรับอัพเดท
        $sql = "UPDATE subjects SET ";
        $params = [];
        
        // เพิ่มฟิลด์ที่ต้องการอัพเดท
        if (isset($data['subject_code'])) {
            $sql .= "subject_code = :code, ";
            $params[':code'] = $data['subject_code'];
        }
        
        if (isset($data['subject_name'])) {
            $sql .= "subject_name = :name, ";
            $params[':name'] = $data['subject_name'];
        }
        
        if (isset($data['credits'])) {
            $sql .= "credits = :credits, ";
            $params[':credits'] = $data['credits'];
        }
        
        if (isset($data['subject_type'])) {
            $sql .= "subject_type = :type, ";
            $params[':type'] = $data['subject_type'];
        }
        
        // ตัด comma และ space ตัวสุดท้ายออก
        $sql = rtrim($sql, ", ");
        
        // เพิ่มเงื่อนไข WHERE
        $sql .= " WHERE subject_id = :id";
        $params[':id'] = $data['subject_id'];
        
        // ถ้าไม่มีข้อมูลที่จะอัพเดท
        if (count($params) <= 1) { // มีแค่ :id
            echo json_encode(['status' => 'success', 'message' => 'ไม่มีข้อมูลที่ต้องอัพเดท']);
            return;
        }
        
        // ทำการอัพเดท
        $stmt = $conn->prepare($sql);
        foreach ($params as $param => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($param, $value, $type);
        }
        
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'อัพเดทข้อมูลวิชาสำเร็จ'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันลบข้อมูลวิชา
function deleteSubject($id) {
    try {
        $conn = connectDB();
        
        // ตรวจสอบว่ามีการใช้งานวิชานี้ในตารางสอนหรือไม่
        $stmt = $conn->prepare("SELECT COUNT(*) FROM teaching_schedules WHERE subject_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode([
                'status' => 'error', 
                'message' => 'ไม่สามารถลบวิชานี้ได้เนื่องจากมีการใช้งานในตารางสอน'
            ]);
            return;
        }
        
        // ลบข้อมูลวิชา
        $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลวิชาสำเร็จ']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลวิชา']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>