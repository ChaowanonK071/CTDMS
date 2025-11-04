<?php

// Set error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure proper headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// เริ่ม session ก่อนอื่น
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include การตั้งค่าฐานข้อมูล
try {
    require_once 'config.php';
} catch (Exception $e) {
    error_log("Failed to load config.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'การตั้งค่าระบบผิดพลาด'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Simple authentication check function
function checkAPIAuthentication() {
    // Check if session has required data
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return [
            'status' => 'error',
            'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน',
            'redirect' => '../login.php'
        ];
    }
    
    if (!isset($_SESSION['user_type']) || empty($_SESSION['user_type'])) {
        return [
            'status' => 'error', 
            'message' => 'ข้อมูลสิทธิ์ผู้ใช้ไม่ครบถ้วน',
            'redirect' => '../login.php'
        ];
    }
    
    return ['status' => 'success'];
}

// Check authentication
$auth_check = checkAPIAuthentication();
if ($auth_check['status'] === 'error') {
    echo json_encode($auth_check, JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบคำสั่ง API
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'get_schedule':
            $response = getTeachingSchedule();
            break;
        case 'get_subjects':
            $response = getSubjects();
            break;
        case 'get_teachers':
            $response = getTeachers();
            break;
        case 'get_classrooms':
            $response = getClassrooms();
            break;
        case 'get_year_levels':
            $response = getYearLevels();
            break;
        case 'get_module_groups':
            $response = getModuleGroups();
            break;
        case 'get_year_levels_by_module_group':
            $response = getYearLevelsByModuleGroup();
            break;
        case 'get_time_slots':
            $response = getTimeSlots();
            break;
        case 'add_schedule':
            $response = addSchedule();
            break;
        case 'update_schedule':
            $response = updateSchedule();
            break;
        case 'delete_schedule':
            $response = deleteSchedule();
            break;
        case 'get_academic_years':
            $response = getAcademicYears();
            break;
        case 'force_delete_schedule':
            $response = forceDeleteSchedule();
            break;
        case 'add_subject':
            $response = addSubject();
            break;
        case 'search_subjects':
            $response = searchSubjects();
            break;
        case 'update_subject':
            $response = updateSubject();
            break;
        case 'delete_subject':
            $response = deleteSubject();
            break;
        default:
            $response = handleError('คำสั่งไม่ถูกต้อง');
            break;
    }
} catch (Exception $e) {
    error_log("API Exception: " . $e->getMessage());
    $response = handleError('เกิดข้อผิดพลาดในระบบ', $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

function getTeachingSchedule() {
    try {
        $conn = connectMySQLi();
        
        $auth_user_id = isset($_GET['auth_user_id']) ? intval($_GET['auth_user_id']) : 0;
        $auth_user_type = isset($_GET['auth_user_type']) ? $_GET['auth_user_type'] : '';
        
        $academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : null;
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
        
        $show_all_for_filter = isset($_GET['show_all_for_filter']) && $_GET['show_all_for_filter'] == '1';
        
        $sql = "SELECT ts.schedule_id, ts.academic_year_id, ts.day_of_week, 
                ts.start_time_slot_id, ts.end_time_slot_id, 
                ts.user_id, ts.subject_id, ts.classroom_id, ts.year_level_id,
                ts.is_external_subject, ts.created_by, ts.created_at, ts.updated_at,
                ts.co_user_id, ts.co_user_id_2, ts.max_teachers, ts.current_teachers,
                ts.is_module_subject, ts.group_id,
                s.subject_code, s.subject_name, s.subject_type, 
                CONCAT(u.title, u.name, ' ', u.lastname) AS teacher_name,
                CONCAT(co1.title, co1.name, ' ', co1.lastname) AS co_teacher_1_name,
                CONCAT(co2.title, co2.name, ' ', co2.lastname) AS co_teacher_2_name,
                c.room_number, 
                CONCAT(y.department, ' ', y.class_year, ' ', y.curriculum) AS year_description,
                start_ts.start_time, end_ts.end_time,
                CONCAT(creator.title, creator.name, ' ', creator.lastname) AS created_by_name
            FROM teaching_schedules ts
            JOIN subjects s ON ts.subject_id = s.subject_id
            JOIN users u ON ts.user_id = u.user_id
            LEFT JOIN users co1 ON ts.co_user_id = co1.user_id
            LEFT JOIN users co2 ON ts.co_user_id_2 = co2.user_id
            LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
            LEFT JOIN year_levels y ON ts.year_level_id = y.year_level_id
            JOIN time_slots start_ts ON ts.start_time_slot_id = start_ts.time_slot_id
            JOIN time_slots end_ts ON ts.end_time_slot_id = end_ts.time_slot_id
            LEFT JOIN users creator ON ts.created_by = creator.user_id
            WHERE ts.is_active = 1";
        
        // ปรับเงื่อนไขการกรองตาม user
        if ($show_all_for_filter) {
            error_log("Showing all schedules due to year level/classroom filter");
        } else {
            if ($auth_user_type === 'teacher') {
                $sql .= " AND (ts.user_id = $auth_user_id OR ts.co_user_id = $auth_user_id OR ts.co_user_id_2 = $auth_user_id)";
                error_log("Teacher filter applied: user_id = $auth_user_id (including co-teacher roles)");
            }
        }
        
        if ($academic_year_id) {
            $sql .= " AND ts.academic_year_id = $academic_year_id";
        }
        
        if ($user_id) {
            $sql .= " AND (ts.user_id = $user_id OR ts.co_user_id = $user_id OR ts.co_user_id_2 = $user_id)";
        }
        
        $sql .= " ORDER BY FIELD(ts.day_of_week, 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'), start_ts.start_time";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $conn->error);
        }
        
        $schedules = [];
        while ($row = $result->fetch_assoc()) {
            // เพิ่มข้อมูลอาจารย์ร่วม
            $row['is_external_subject'] = intval($row['is_external_subject']);
            $row['room_display'] = $row['is_external_subject'] ? '-' : ($row['room_number'] ?: '-');
            $row['year_display'] = $row['is_external_subject'] ? '-' : ($row['year_description'] ?: '-');
            
            // สร้างรายชื่ออาจารย์ทั้งหมด
            $all_teachers = [$row['teacher_name']];
            if (!empty($row['co_teacher_1_name'])) {
                $all_teachers[] = $row['co_teacher_1_name'];
            }
            if (!empty($row['co_teacher_2_name'])) {
                $all_teachers[] = $row['co_teacher_2_name'];
            }
            
            $row['all_teachers_display'] = implode(', ', $all_teachers);
            $row['teacher_count'] = count($all_teachers);
            
            $schedules[] = $row;
        }
        $conn->close();
        
        error_log("Retrieved " . count($schedules) . " schedules with co-teacher data");
        
        return ['status' => 'success', 'data' => $schedules];
    } catch (Exception $e) {
        error_log("Error getting schedule: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการดึงข้อมูลตารางสอน', $e->getMessage());
    }
}

function getAcademicYears() {
    try {
        $conn = connectMySQLi();
        
        $sql = "SELECT * FROM academic_years ORDER BY academic_year DESC, semester DESC";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูลปีการศึกษา: ' . $conn->error);
        }
        
        $academicYears = [];
        while ($row = $result->fetch_assoc()) {
            $academicYears[] = $row;
        }
        $conn->close();
        
        return ['status' => 'success', 'data' => $academicYears];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึงข้อมูลปีการศึกษา', $e->getMessage());
    }
}

function getTeachers() {
    try {
        $conn = connectMySQLi();
        
        // ตรวจสอบว่าผู้ใช้เป็น admin หรือไม่เพื่อแสดงประเภทผู้ใช้
        $auth_user_id = isset($_GET['auth_user_id']) ? intval($_GET['auth_user_id']) : 0;
        $auth_user_type = isset($_GET['auth_user_type']) ? $_GET['auth_user_type'] : '';
        $showType = ($auth_user_type === 'admin');
        
        if ($showType) {
            // เรียงลำดับให้ current user มาก่อน แล้วตามด้วย teacher, admin และเรียงตามชื่อ
            $sql = "SELECT user_id, CONCAT(title, name, ' ', lastname) AS fullname, user_type 
                    FROM users 
                    WHERE user_type IN ('admin', 'teacher') AND is_active = 1
                    ORDER BY 
                        CASE WHEN user_id = ? THEN 0 ELSE 1 END,
                        CASE WHEN user_type = 'teacher' THEN 1 ELSE 2 END,
                        name, lastname";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $auth_user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql = "SELECT user_id, CONCAT(title, name, ' ', lastname) AS fullname 
                    FROM users 
                    WHERE user_type IN ('admin', 'teacher') AND is_active = 1
                    ORDER BY 
                        CASE WHEN user_id = ? THEN 0 ELSE 1 END,
                        CASE WHEN user_type = 'teacher' THEN 1 ELSE 2 END,
                        name, lastname";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $auth_user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        }
        
        if (!$result) {
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูลอาจารย์: ' . $conn->error);
        }
        
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            if ($showType && isset($row['user_type'])) {
                // แสดงประเภทผู้ใช้เฉพาะสำหรับ admin
                $typeLabel = $row['user_type'] === 'admin' ? ' (ผู้ดูแลระบบ)' : ' (อาจารย์)';
                $isCurrentUser = ($row['user_id'] == $auth_user_id);
                $currentUserLabel = $isCurrentUser ? ' [ตัวคุณ]' : '';
                
                $teachers[] = [
                    'user_id' => $row['user_id'],
                    'fullname' => $row['fullname'] . $typeLabel . $currentUserLabel,
                    'user_type' => $row['user_type'] ?? 'teacher',
                    'is_current_user' => $isCurrentUser
                ];
            } else {
                // แสดงเฉพาะชื่อสำหรับผู้ใช้ทั่วไป
                $isCurrentUser = ($row['user_id'] == $auth_user_id);
                $currentUserLabel = $isCurrentUser ? ' [ตัวคุณ]' : '';
                
                $teachers[] = [
                    'user_id' => $row['user_id'],
                    'fullname' => $row['fullname'] . $currentUserLabel,
                    'user_type' => $row['user_type'] ?? 'teacher',
                    'is_current_user' => $isCurrentUser
                ];
            }
        }
        $conn->close();
        
        error_log("Teachers loaded with current user priority - found " . count($teachers) . " teachers (current user ID: $auth_user_id)");
        
        return ['status' => 'success', 'data' => $teachers];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึงข้อมูลอาจารย์', $e->getMessage());
    }
}

function getSubjects() {
    try {
        $conn = connectMySQLi();
        
        $sql = "SELECT * FROM subjects ORDER BY subject_code";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูลวิชา: ' . $conn->error);
        }
        
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $conn->close();
        
        return ['status' => 'success', 'data' => $subjects];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึงข้อมูลวิชา', $e->getMessage());
    }
}

function getClassrooms() {
    try {
        $conn = connectMySQLi();
        
        $sql = "SELECT * FROM classrooms ORDER BY room_number";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูลห้องเรียน: ' . $conn->error);
        }
        
        $classrooms = [];
        while ($row = $result->fetch_assoc()) {
            $classrooms[] = $row;
        }
        $conn->close();
        
        return ['status' => 'success', 'data' => $classrooms];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึงข้อมูลห้องเรียน', $e->getMessage());
    }
}

function getYearLevels() {
    try {
        $conn = connectMySQLi();
        
        $sql = "SELECT * FROM year_levels ORDER BY department, class_year, curriculum";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูลชั้นปี: ' . $conn->error);
        }
        
        $yearLevels = [];
        while ($row = $result->fetch_assoc()) {
            $yearLevels[] = $row;
        }
        $conn->close();
        
        return ['status' => 'success', 'data' => $yearLevels];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึงข้อมูลชั้นปี', $e->getMessage());
    }
}

function getTimeSlots() {
    try {
        $conn = connectMySQLi();
        
        $sql = "SELECT * FROM time_slots ORDER BY slot_number";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('เกิดข้อผิดพลาดในการดึงข้อมูลช่วงเวลา: ' . $conn->error);
        }
        
        $timeSlots = [];
        while ($row = $result->fetch_assoc()) {
            $timeSlots[] = $row;
        }
        $conn->close();
        
        return ['status' => 'success', 'data' => $timeSlots];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึงข้อมูลช่วงเวลา', $e->getMessage());
    }
}

/**
 * เพิ่มวิชาใหม่
 */
function addSubject() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return handleError('วิธีการร้องขอไม่ถูกต้อง');
    }
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            return handleError('ไม่สามารถอ่านข้อมูล JSON ได้');
        }
        
        // ตรวจสอบข้อมูลที่จำเป็น
        $required_fields = ['subject_code', 'subject_name', 'subject_type'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return handleError("ข้อมูล $field ไม่ครบถ้วน");
            }
        }
        
        $subject_code = trim($data['subject_code']);
        $subject_name = trim($data['subject_name']);
        $subject_type = trim($data['subject_type']);
        $credits = isset($data['credits']) ? intval($data['credits']) : 3;
        // ตรวจสอบประเภทวิชา
        if (!in_array($subject_type, ['ทฤษฎี', 'ปฏิบัติ'])) {
            return handleError('ประเภทวิชาไม่ถูกต้อง');
        }
        
        $conn = connectMySQLi();
        
        // ตรวจสอบความซ้ำซ้อนของรหัสวิชา + ประเภท
        $checkSQL = "SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_type = ?";
        $stmt = $conn->prepare($checkSQL);
        $stmt->bind_param("ss", $subject_code, $subject_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            $conn->close();
            return handleError("รหัสวิชา $subject_code ($subject_type) มีอยู่ในระบบแล้ว");
        }
        $stmt->close();
        
        // บันทึกวิชาใหม่
        $insertSQL = "INSERT INTO subjects (subject_code, subject_name, subject_type, credits) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSQL);
        $stmt->bind_param("sssi", $subject_code, $subject_name, $subject_type, $credits);
        
        if ($stmt->execute()) {
            $subject_id = $conn->insert_id;
            $stmt->close();
            $conn->close();
            
            error_log("New subject added: ID=$subject_id, Code=$subject_code, Name=$subject_name, Type=$subject_type");
            
            return [
                'status' => 'success',
                'message' => 'เพิ่มวิชาใหม่สำเร็จ',
                'subject_id' => $subject_id,
                'subject_code' => $subject_code,
                'subject_name' => $subject_name,
                'subject_type' => $subject_type,
                'credits' => $credits
            ];
        } else {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            
            error_log("Error adding subject: " . $error);
            return handleError('เกิดข้อผิดพลาดในการบันทึกวิชา: ' . $error);
        }
        
    } catch (Exception $e) {
        error_log("Exception in addSubject: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการเพิ่มวิชา: ' . $e->getMessage());
    }
}

/**
 * ค้นหาวิชาตามคำค้นหา
 */
function searchSubjects() {
    try {
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
        
        if (empty($query)) {
            return ['status' => 'success', 'data' => []];
        }
        
        $conn = connectMySQLi();
        
        // ค้นหาจากรหัสวิชาและชื่อวิชา
        $sql = "SELECT subject_id, subject_code, subject_name, subject_type, credits 
                FROM subjects 
                WHERE subject_code LIKE ? OR subject_name LIKE ? 
                ORDER BY subject_code, subject_type";
        
        $searchTerm = '%' . $query . '%';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $subjects = [];
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        return ['status' => 'success', 'data' => $subjects];
        
    } catch (Exception $e) {
        error_log("Error in searchSubjects: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการค้นหาวิชา: ' . $e->getMessage());
    }
}

/**
 * อัปเดตวิชา
 */
function updateSubject() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        return handleError('วิธีการร้องขอไม่ถูกต้อง');
    }
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            return handleError('ไม่สามารถอ่านข้อมูล JSON ได้');
        }
        
        // ตรวจสอบข้อมูลที่จำเป็น
        $required_fields = ['subject_id', 'subject_code', 'subject_name', 'subject_type'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return handleError("ข้อมูล $field ไม่ครบถ้วน");
            }
        }
        
        $subject_id = intval($data['subject_id']);
        $subject_code = trim($data['subject_code']);
        $subject_name = trim($data['subject_name']);
        $subject_type = trim($data['subject_type']);
        $credits = isset($data['credits']) ? intval($data['credits']) : 3;
        
        // ตรวจสอบประเภทวิชา
        if (!in_array($subject_type, ['ทฤษฎี', 'ปฏิบัติ'])) {
            return handleError('ประเภทวิชาไม่ถูกต้อง');
        }
        
        $conn = connectMySQLi();
        
        // ตรวจสอบความซ้ำซ้อนของรหัสวิชา
        $checkSQL = "SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_type = ? AND subject_id != ?";
        $stmt = $conn->prepare($checkSQL);
        $stmt->bind_param("ssi", $subject_code, $subject_type, $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            $conn->close();
            return handleError("รหัสวิชา $subject_code ($subject_type) มีอยู่ในระบบแล้ว");
        }
        $stmt->close();
        
        // อัปเดตข้อมูลวิชา
        $updateSQL = "UPDATE subjects SET subject_code = ?, subject_name = ?, subject_type = ?, credits = ? WHERE subject_id = ?";
        $stmt = $conn->prepare($updateSQL);
        $stmt->bind_param("sssii", $subject_code, $subject_name, $subject_type, $credits, $subject_id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            $conn->close();
            
            if ($affected_rows > 0) {
                error_log("Subject updated: ID=$subject_id, Code=$subject_code");
                return [
                    'status' => 'success',
                    'message' => 'อัปเดตข้อมูลวิชาสำเร็จ',
                    'subject_id' => $subject_id
                ];
            } else {
                return handleError('ไม่พบข้อมูลวิชาที่ต้องการอัปเดต');
            }
        } else {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            
            error_log("Error updating subject: " . $error);
            return handleError('เกิดข้อผิดพลาดในการอัปเดตวิชา: ' . $error);
        }
        
    } catch (Exception $e) {
        error_log("Exception in updateSubject: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการอัปเดตวิชา: ' . $e->getMessage());
    }
}

/**
 * ลบวิชา
 */
function deleteSubject() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        return handleError('วิธีการร้องขอไม่ถูกต้อง');
    }
    
    try {
        $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
        
        if ($subject_id <= 0) {
            return handleError('รหัสวิชาไม่ถูกต้อง');
        }
        
        $conn = connectMySQLi();
        
        // ตรวจสอบว่ามีการใช้งานในตารางสอนหรือไม่
        $checkUsageSQL = "SELECT COUNT(*) as usage_count FROM teaching_schedules WHERE subject_id = ?";
        $stmt = $conn->prepare($checkUsageSQL);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $usage = $result->fetch_assoc();
        $stmt->close();
        
        if ($usage['usage_count'] > 0) {
            $conn->close();
            return handleError('ไม่สามารถลบวิชานี้ได้เนื่องจากมีการใช้งานในตารางสอนแล้ว');
        }
        
        // ลบวิชา
        $deleteSQL = "DELETE FROM subjects WHERE subject_id = ?";
        $stmt = $conn->prepare($deleteSQL);
        $stmt->bind_param("i", $subject_id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            $conn->close();
            
            if ($affected_rows > 0) {
                error_log("Subject deleted: ID=$subject_id");
                return [
                    'status' => 'success',
                    'message' => 'ลบวิชาสำเร็จ',
                    'deleted_id' => $subject_id
                ];
            } else {
                return handleError('ไม่พบข้อมูลวิชาที่ต้องการลบ');
            }
        } else {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            
            error_log("Error deleting subject: " . $error);
            return handleError('เกิดข้อผิดพลาดในการลบวิชา: ' . $error);
        }
        
    } catch (Exception $e) {
        error_log("Exception in deleteSubject: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการลบวิชา: ' . $e->getMessage());
    }
}

// === Schedule Management Functions ===
function addSchedule() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return handleError('วิธีการร้องขอไม่ถูกต้อง');
    }

    try {
        $json_input = file_get_contents("php://input");
        if (empty($json_input)) {
            error_log("Empty JSON input received in addSchedule");
            return handleError('ไม่ได้รับข้อมูล JSON');
        }
        $data = json_decode($json_input, true);
        if ($data === null) {
            $json_error = json_last_error_msg();
            error_log("JSON decode error in addSchedule: " . $json_error);
            return handleError('ไม่สามารถอ่านข้อมูล JSON ได้: ' . $json_error);
        }

        error_log("AddSchedule input data: " . json_encode($data));
        
        // ตรวจสอบ session
        $auth_user_id = $_SESSION['user_id'] ?? null;
        $auth_user_type = $_SESSION['user_type'] ?? null;
        if (!$auth_user_id || !$auth_user_type) {
            return handleError('กรุณาเข้าสู่ระบบใหม่');
        }

        // ฟิลด์พื้นฐาน
        $required_fields = ['academic_year_id', 'user_id', 'subject_id', 'day_of_week', 'start_time_slot_id', 'end_time_slot_id'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                return handleError("ข้อมูล $field ไม่ครบถ้วน");
            }
        }

        // แปลงข้อมูล
        $academic_year_id = intval($data['academic_year_id']);
        $user_id = intval($data['user_id']);
        $subject_id = intval($data['subject_id']);
        $start_time_slot_id = intval($data['start_time_slot_id']);
        $end_time_slot_id = intval($data['end_time_slot_id']);
        $day_of_week = trim($data['day_of_week']);

        // ตรวจสอบความถูกต้อง
        if ($academic_year_id <= 0 || $user_id <= 0 || $subject_id <= 0 || $start_time_slot_id <= 0 || $end_time_slot_id <= 0) {
            return handleError('ข้อมูลรหัสไม่ถูกต้อง');
        }
        if ($end_time_slot_id < $start_time_slot_id) {
            return handleError('เวลาสิ้นสุดต้องมากกว่าหรือเท่ากับเวลาเริ่มต้น');
        }
        $valid_days = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
        if (!in_array($day_of_week, $valid_days)) {
            return handleError('รูปแบบวันไม่ถูกต้อง: ' . $day_of_week);
        }

        // อาจารย์ร่วม
        $co_user_id = isset($data['co_user_id']) && $data['co_user_id'] !== '' && $data['co_user_id'] !== null ? intval($data['co_user_id']) : null;
        $co_user_id_2 = isset($data['co_user_id_2']) && $data['co_user_id_2'] !== '' && $data['co_user_id_2'] !== null ? intval($data['co_user_id_2']) : null;
        $teacher_ids = array_filter([$user_id, $co_user_id, $co_user_id_2], function($id) {
            return $id !== null && $id > 0;
        });
        if (count($teacher_ids) !== count(array_unique($teacher_ids))) {
            return handleError('ไม่สามารถเลือกอาจารย์คนเดียวกันได้');
        }
        $current_teachers = count($teacher_ids);
        $max_teachers = intval($data['max_teachers'] ?? ($current_teachers > 1 ? 3 : 1));

        // โมดูล
        $is_module_subject = isset($data['is_module_subject']) ? intval($data['is_module_subject']) : 0;
        $group_id = isset($data['group_id']) ? intval($data['group_id']) : null;
        // ห้องเรียน/ชั้นปี
        $classroom_id = isset($data['classroom_id']) && $data['classroom_id'] ? intval($data['classroom_id']) : null;
        $year_level_id = isset($data['year_level_id']) && $data['year_level_id'] ? intval($data['year_level_id']) : null;
        error_log("DEBUG year_level_id: " . var_export($year_level_id, true));
        error_log("DEBUG group_id: " . var_export($group_id, true));
        error_log("DEBUG is_module_subject: " . var_export($is_module_subject, true));
        
        // ตรวจสอบข้อมูลโมดูล
        $conn = connectMySQLi();

        // ตรวจสอบความซ้ำซ้อน
        $teacher_conflict = checkAllTeacherConflicts($conn, $teacher_ids, $academic_year_id, $day_of_week, $start_time_slot_id, $end_time_slot_id);
        if ($teacher_conflict) {
            $conn->close();
            return handleError($teacher_conflict);
        }
        $classroom_conflict = null;
        if ($is_module_subject != 1) {
            $classroom_conflict = checkClassroomConflict($conn, $academic_year_id, $classroom_id, $day_of_week, $start_time_slot_id, $end_time_slot_id);
            if ($classroom_conflict) {
                $conn->close();
                return handleError($classroom_conflict);
            }
        }
        // ตรวจสอบความซ้ำซ้อนของชั้นปี (เฉพาะรายวิชาปกติ)
        $year_level_conflict = checkYearLevelConflict($conn, $academic_year_id, $year_level_id, $day_of_week, $start_time_slot_id, $end_time_slot_id);
        if ($year_level_conflict) {
            $conn->close();
            return handleError($year_level_conflict);
        }
        // ตรวจสอบความซ้ำซ้อนระหว่างชั้นปี-โมดูล
        $conflict_msg = checkYearLevelModuleConflict($conn, $academic_year_id, $year_level_id, $group_id, $day_of_week, $start_time_slot_id, $end_time_slot_id);
        if ($conflict_msg) {
            $conn->close();
            return handleError($conflict_msg);
        }
        if ($is_module_subject == 1) {
            // ตรวจสอบ group_id
            if (!$group_id || $group_id <= 0) {
                $conn->close();
                return handleError('กรุณาเลือกกลุ่มโมดูล');
            }

            $insert_sql = "INSERT INTO teaching_schedules 
                (academic_year_id, user_id, subject_id, classroom_id, day_of_week, 
                start_time_slot_id, end_time_slot_id, is_external_subject, 
                created_by, co_user_id, co_user_id_2, max_teachers, current_teachers, is_active, is_module_subject, group_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 1, 1, ?)";
            $stmt = $conn->prepare($insert_sql);
            if (!$stmt) {
                $conn->close();
                error_log("Prepare failed: " . $conn->error);
                return handleError('เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error);
            }
            $stmt->bind_param("iiiisiiiiiiii", 
                $academic_year_id, $user_id, $subject_id, $classroom_id, $day_of_week,
                $start_time_slot_id, $end_time_slot_id, $auth_user_id,
                $co_user_id, $co_user_id_2, $max_teachers, $current_teachers, $group_id
            );
        } else {
            // กรณีไม่ใช่โมดูล ใช้ year_level_id ปกติ
            if (!$year_level_id || $year_level_id <= 0) {
                $conn->close();
                return handleError('กรุณาเลือกชั้นปี');
            }
        $insert_sql = "INSERT INTO teaching_schedules 
                       (academic_year_id, user_id, subject_id, classroom_id, day_of_week, 
                        year_level_id, start_time_slot_id, end_time_slot_id, is_external_subject, 
                        created_by, co_user_id, co_user_id_2, max_teachers, current_teachers, is_active) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $conn->prepare($insert_sql);
        if (!$stmt) {
            $conn->close();
            error_log("Prepare failed: " . $conn->error);
            return handleError('เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error);
        }
        
        // Bind parameters
        $stmt->bind_param("iiiisiiiiiiii", 
            $academic_year_id,     
            $user_id,              
            $subject_id,          
            $classroom_id,        
            $day_of_week,         
            $year_level_id,       
            $start_time_slot_id,  
            $end_time_slot_id,    
            $auth_user_id,        
            $co_user_id,          
            $co_user_id_2,        
            $max_teachers,        
            $current_teachers     
        );
}

        error_log("Executing SQL with teachers: main=$user_id, co1=$co_user_id, co2=$co_user_id_2");

        if ($stmt->execute()) {
            $schedule_id = $conn->insert_id;
            $stmt->close();
            $conn->close();
            $teacher_info = $current_teachers > 1 ? " (อาจารย์ $current_teachers คน)" : "";
            error_log("Schedule added successfully: ID=$schedule_id with $current_teachers teachers");
            return [
                'status' => 'success',
                'message' => 'เพิ่มตารางสอนสำเร็จ' . $teacher_info,
                'schedule_id' => $schedule_id,
                'teacher_count' => $current_teachers
            ];
        } else {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            error_log("Error executing addSchedule: " . $error);
            return handleError('เกิดข้อผิดพลาดในการบันทึกตารางสอน: ' . $error);
        }

    } catch (Exception $e) {
        error_log("Exception in addSchedule: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return handleError('เกิดข้อผิดพลาดในการเพิ่มตารางสอน: ' . $e->getMessage());
    }
}
// ฟังก์ชันตรวจสอบความซ้ำซ้อนของอาจารย์ทุกคน
function checkAllTeacherConflicts($conn, $teacher_ids, $academic_year_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $exclude_schedule_id = 0) {
    if (empty($teacher_ids)) {
        return null;
    }
    
    try {
        // สร้าง placeholders สำหรับ teacher_ids
        $placeholders = str_repeat('?,', count($teacher_ids) - 1) . '?';
        
        $sql = "SELECT ts.schedule_id, ts.user_id, ts.co_user_id, ts.co_user_id_2,
                       s.subject_code, s.subject_name, 
                       CONCAT(u.title, u.name, ' ', u.lastname) AS main_teacher_name,
                       CONCAT(co1.title, co1.name, ' ', co1.lastname) AS co_teacher_1_name,
                       CONCAT(co2.title, co2.name, ' ', co2.lastname) AS co_teacher_2_name,
                       start_ts.start_time, end_ts.end_time
                FROM teaching_schedules ts 
                JOIN subjects s ON ts.subject_id = s.subject_id 
                JOIN users u ON ts.user_id = u.user_id
                LEFT JOIN users co1 ON ts.co_user_id = co1.user_id
                LEFT JOIN users co2 ON ts.co_user_id_2 = co2.user_id
                JOIN time_slots start_ts ON ts.start_time_slot_id = start_ts.time_slot_id
                JOIN time_slots end_ts ON ts.end_time_slot_id = end_ts.time_slot_id
                WHERE ts.academic_year_id = ? 
                AND ts.day_of_week = ? 
                AND ts.is_active = 1
                AND (
                    ts.user_id IN ($placeholders) OR 
                    ts.co_user_id IN ($placeholders) OR 
                    ts.co_user_id_2 IN ($placeholders)
                )
                AND (
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                    (ts.start_time_slot_id >= ? AND ts.end_time_slot_id <= ?)
                )";
        
        if ($exclude_schedule_id > 0) {
            $sql .= " AND ts.schedule_id != ?";
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for teacher conflict check: " . $conn->error);
            return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของอาจารย์";
        }
        
        // สร้าง parameters array
        $params = [];
        $types = "is";
        
        $params[] = $academic_year_id;
        $params[] = $day_of_week;
        
        // เพิ่ม teacher_ids สำหรับ 3 IN clauses
        for ($i = 0; $i < 3; $i++) {
            foreach ($teacher_ids as $teacher_id) {
                $params[] = $teacher_id;
                $types .= "i";
            }
        }
        
        $time_params = [
            $start_time_slot_id, $start_time_slot_id,  
            $end_time_slot_id, $end_time_slot_id,     
            $start_time_slot_id, $end_time_slot_id  
        ];
        
        foreach ($time_params as $time_param) {
            $params[] = $time_param;
            $types .= "i";
        }
        
        // เพิ่ม exclude_schedule_id ถ้ามี
        if ($exclude_schedule_id > 0) {
            $params[] = $exclude_schedule_id;
            $types .= "i";
        }
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            error_log("Execute failed for teacher conflict check: " . $stmt->error);
            $stmt->close();
            return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของอาจารย์";
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $conflict = $result->fetch_assoc();
            $stmt->close();
            
            // หาชื่ออาจารย์ที่ขัดแย้ง
            $conflicted_teacher_name = '';
            foreach ($teacher_ids as $teacher_id) {
                if ($conflict['user_id'] == $teacher_id) {
                    $conflicted_teacher_name = $conflict['main_teacher_name'];
                    break;
                } elseif ($conflict['co_user_id'] == $teacher_id) {
                    $conflicted_teacher_name = $conflict['co_teacher_1_name'];
                    break;
                } elseif ($conflict['co_user_id_2'] == $teacher_id) {
                    $conflicted_teacher_name = $conflict['co_teacher_2_name'];
                    break;
                }
            }
            
            $start_time = date('H:i', strtotime($conflict['start_time']));
            $end_time = date('H:i', strtotime($conflict['end_time']));
            
            return "อาจารย์ " . $conflicted_teacher_name . 
                   " ไม่สามารถสอนได้ในช่วงเวลานี้ เนื่องจากมีการสอนวิชา " . 
                   $conflict['subject_code'] . " - " . $conflict['subject_name'] . 
                   " ในเวลา " . $start_time . " - " . $end_time . " น. อยู่แล้วในวัน" . $day_of_week;
        }
        
        $stmt->close();
        return null;
        
    } catch (Exception $e) {
        error_log("Exception in checkAllTeacherConflicts: " . $e->getMessage());
        return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของอาจารย์: " . $e->getMessage();
    }
}
function checkClassroomConflict($conn, $academic_year_id, $classroom_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $exclude_schedule_id = 0) {
    try {
        $sql = "SELECT ts.schedule_id, s.subject_code, s.subject_name, 
                       CONCAT(u.title, u.name, ' ', u.lastname) AS teacher_name,
                       c.room_number,
                       start_ts.start_time, end_ts.end_time
                FROM teaching_schedules ts 
                JOIN subjects s ON ts.subject_id = s.subject_id 
                JOIN users u ON ts.user_id = u.user_id
                JOIN classrooms c ON ts.classroom_id = c.classroom_id
                JOIN time_slots start_ts ON ts.start_time_slot_id = start_ts.time_slot_id
                JOIN time_slots end_ts ON ts.end_time_slot_id = end_ts.time_slot_id
                WHERE ts.academic_year_id = ? 
                AND ts.classroom_id = ? 
                AND ts.day_of_week = ? 
                AND ts.is_active = 1
                AND (
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                    (ts.start_time_slot_id >= ? AND ts.end_time_slot_id <= ?)
                )";
        
        if ($exclude_schedule_id > 0) {
            $sql .= " AND ts.schedule_id != ?";
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for classroom conflict check: " . $conn->error);
            return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของห้องเรียน";
        }
        
        if ($exclude_schedule_id > 0) {
            $stmt->bind_param("iisiiiiiii", 
                $academic_year_id, $classroom_id, $day_of_week,
                $start_time_slot_id, $start_time_slot_id,  
                $end_time_slot_id, $end_time_slot_id,      
                $start_time_slot_id, $end_time_slot_id,    
                $exclude_schedule_id
            );
        } else {
            $stmt->bind_param("iisiiiiii", 
                $academic_year_id, $classroom_id, $day_of_week,
                $start_time_slot_id, $start_time_slot_id,  
                $end_time_slot_id, $end_time_slot_id,      
                $start_time_slot_id, $end_time_slot_id     
            );
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed for classroom conflict check: " . $stmt->error);
            $stmt->close();
            return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของห้องเรียน";
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $conflict = $result->fetch_assoc();
            $stmt->close();
            
            $start_time = date('H:i', strtotime($conflict['start_time']));
            $end_time = date('H:i', strtotime($conflict['end_time']));
            
            return "ห้อง {$conflict['room_number']} ไม่สามารถใช้ได้ในช่วงเวลานี้ " .
                   "เนื่องจากมีการสอนวิชา {$conflict['subject_code']} - {$conflict['subject_name']} " .
                   "โดยอาจารย์ {$conflict['teacher_name']} " .
                   "ในเวลา {$start_time} - {$end_time} น. อยู่แล้วในวัน{$day_of_week}";
        }
        
        $stmt->close();
        return null;
        
    } catch (Exception $e) {
        error_log("Exception in checkClassroomConflict: " . $e->getMessage());
        return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของห้องเรียน: " . $e->getMessage();
    }
}
function checkYearLevelConflict($conn, $academic_year_id, $year_level_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $exclude_schedule_id = 0) {
    try {
        $sql = "SELECT ts.schedule_id, s.subject_code, s.subject_name, 
                       CONCAT(u.title, u.name, ' ', u.lastname) AS teacher_name,
                       CONCAT(y.department, ' ', y.class_year, ' ', y.curriculum) AS year_description,
                       c.room_number,
                       start_ts.start_time, end_ts.end_time
                FROM teaching_schedules ts 
                JOIN subjects s ON ts.subject_id = s.subject_id 
                JOIN users u ON ts.user_id = u.user_id
                JOIN year_levels y ON ts.year_level_id = y.year_level_id
                LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
                JOIN time_slots start_ts ON ts.start_time_slot_id = start_ts.time_slot_id
                JOIN time_slots end_ts ON ts.end_time_slot_id = end_ts.time_slot_id
                WHERE ts.academic_year_id = ? 
                AND ts.year_level_id = ? 
                AND ts.day_of_week = ? 
                AND ts.is_active = 1
                AND (
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?) OR
                    (ts.start_time_slot_id >= ? AND ts.end_time_slot_id <= ?)
                )";
        
        if ($exclude_schedule_id > 0) {
            $sql .= " AND ts.schedule_id != ?";
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for year level conflict check: " . $conn->error);
            return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของชั้นปี";
        }
        
        if ($exclude_schedule_id > 0) {
            $stmt->bind_param("iisiiiiiii", 
                $academic_year_id, $year_level_id, $day_of_week,
                $start_time_slot_id, $start_time_slot_id,  
                $end_time_slot_id, $end_time_slot_id,      
                $start_time_slot_id, $end_time_slot_id,    
                $exclude_schedule_id
            );
        } else {
            $stmt->bind_param("iisiiiiii", 
                $academic_year_id, $year_level_id, $day_of_week,
                $start_time_slot_id, $start_time_slot_id,  
                $end_time_slot_id, $end_time_slot_id,      
                $start_time_slot_id, $end_time_slot_id     
            );
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed for year level conflict check: " . $stmt->error);
            $stmt->close();
            return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของชั้นปี";
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $conflict = $result->fetch_assoc();
            $stmt->close();
            
            $start_time = date('H:i', strtotime($conflict['start_time']));
            $end_time = date('H:i', strtotime($conflict['end_time']));
            $room_info = $conflict['room_number'] ? " ห้อง {$conflict['room_number']}" : "";
            
            return "ชั้นปี {$conflict['year_description']} ไม่สามารถเรียนได้ในช่วงเวลานี้ " .
                   "เนื่องจากมีการเรียนวิชา {$conflict['subject_code']} - {$conflict['subject_name']} " .
                   "กับอาจารย์ {$conflict['teacher_name']}{$room_info} " .
                   "ในเวลา {$start_time} - {$end_time} น. อยู่แล้วในวัน{$day_of_week}";
        }
        
        $stmt->close();
        return null;
        
    } catch (Exception $e) {
        error_log("Exception in checkYearLevelConflict: " . $e->getMessage());
        return "เกิดข้อผิดพลาดในการตรวจสอบความซ้ำซ้อนของชั้นปี: " . $e->getMessage();
    }
}

function updateSchedule() {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        return handleError('วิธีการร้องขอไม่ถูกต้อง');
    }
    
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            return handleError('ไม่สามารถอ่านข้อมูล JSON ได้');
        }

        $auth_user_id = $_SESSION['user_id'] ?? (isset($_GET['auth_user_id']) ? intval($_GET['auth_user_id']) : 0);
        $auth_user_type = $_SESSION['user_type'] ?? (isset($_GET['auth_user_type']) ? $_GET['auth_user_type'] : 'teacher');

        // ตรวจสอบข้อมูลพื้นฐานที่จำเป็น
        $basic_required_fields = ['schedule_id', 'academic_year_id', 'user_id', 'subject_id', 
                                'day_of_week', 'start_time_slot_id', 'end_time_slot_id'];
        foreach ($basic_required_fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                return handleError("ข้อมูล $field ไม่ครบถ้วน");
            }
        }

        $schedule_id = intval($data['schedule_id']);
        $academic_year_id = intval($data['academic_year_id']);
        $user_id = intval($data['user_id']);
        $subject_id = intval($data['subject_id']);
        $start_time_slot_id = intval($data['start_time_slot_id']);
        $end_time_slot_id = intval($data['end_time_slot_id']);
        $day_of_week = trim($data['day_of_week']);

        $co_user_id = isset($data['co_user_id']) && $data['co_user_id'] ? intval($data['co_user_id']) : null;
        $co_user_id_2 = isset($data['co_user_id_2']) && $data['co_user_id_2'] ? intval($data['co_user_id_2']) : null;
        $current_teachers = intval($data['current_teachers'] ?? 1);
        $max_teachers = intval($data['max_teachers'] ?? 1);

        $is_module_subject = isset($data['is_module_subject']) ? intval($data['is_module_subject']) : 0;
        $group_id = isset($data['group_id']) ? intval($data['group_id']) : null;
        $classroom_id = isset($data['classroom_id']) ? intval($data['classroom_id']) : null;
        $year_level_id = isset($data['year_level_id']) ? intval($data['year_level_id']) : null;

        $conn = connectMySQLi();

        // ตรวจสอบสิทธิ์และข้อมูลเดิม
        $checkSQL = "SELECT user_id, co_user_id, co_user_id_2, is_external_subject FROM teaching_schedules WHERE schedule_id = ? AND is_active = 1";
        $stmt = $conn->prepare($checkSQL);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            return handleError('ไม่พบตารางสอนที่ต้องการแก้ไข');
        }
        $existing_schedule = $result->fetch_assoc();
        $stmt->close();

        if ($auth_user_type === 'teacher') {
            $can_edit = (intval($existing_schedule['user_id']) === $auth_user_id) ||
                       (intval($existing_schedule['co_user_id']) === $auth_user_id) ||
                       (intval($existing_schedule['co_user_id_2']) === $auth_user_id);
            if (!$can_edit) {
                $conn->close();
                return handleError('คุณไม่มีสิทธิ์แก้ไขตารางสอนนี้');
            }
        }

        $is_external_subject = intval($existing_schedule['is_external_subject'] ?? 0);

        // ตรวจสอบรูปแบบวันและเวลา
        $valid_days = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
        if (!in_array($day_of_week, $valid_days)) {
            $conn->close();
            return handleError('รูปแบบวันไม่ถูกต้อง: ' . $day_of_week);
        }
        if ($end_time_slot_id < $start_time_slot_id) {
            $conn->close();
            return handleError('เวลาสิ้นสุดต้องมากกว่าหรือเท่ากับเวลาเริ่มต้น');
        }

        // ตรวจสอบความซ้ำซ้อนของอาจารย์
        $teacher_ids = array_filter([$user_id, $co_user_id, $co_user_id_2]);
        if (count($teacher_ids) !== count(array_unique($teacher_ids))) {
            $conn->close();
            return handleError('ไม่สามารถเลือกอาจารย์คนเดียวกันได้');
        }
        $all_teacher_conflicts = checkAllTeacherConflicts($conn, $teacher_ids, $academic_year_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $schedule_id);
        if (!empty($all_teacher_conflicts)) {
            $conn->close();
            return handleError($all_teacher_conflicts);
        }
        $classroom_conflict = null;
        if ($is_module_subject != 1) {
            $classroom_conflict = checkClassroomConflict($conn, $academic_year_id, $classroom_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $schedule_id);
            if ($classroom_conflict) {
                $conn->close();
                return handleError($classroom_conflict);
            }
        }
        // ตรวจสอบความซ้ำซ้อนของชั้นปี (เฉพาะรายวิชาปกติ)
        $year_level_conflict = checkYearLevelConflict($conn, $academic_year_id, $year_level_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $schedule_id);
        if ($year_level_conflict) {
            $conn->close();
            return handleError($year_level_conflict);
        }
        // ตรวจสอบความซ้ำซ้อนระหว่างชั้นปี-โมดูล
        $conflict_msg = checkYearLevelModuleConflict($conn, $academic_year_id, $year_level_id, $group_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $schedule_id);
        if ($conflict_msg) {
            $conn->close();
            return handleError($conflict_msg);
        }

        // เงื่อนไขสำหรับวิชาโมดูล
        if ($is_module_subject == 1) {
            // ต้องเลือก group_id
            if (!$group_id || $group_id <= 0) {
                $conn->close();
                return handleError('กรุณาเลือกกลุ่มโมดูล');
            }
            // ไม่ต้องบังคับ year_level_id
            if (!$classroom_id || $classroom_id <= 0) {
                $conn->close();
                return handleError("กรุณาเลือกห้องเรียนสำหรับวิชาโมดูล");
            }
            // อัปเดตเฉพาะ group_id
            $update_sql = "UPDATE teaching_schedules SET 
                           academic_year_id = ?, user_id = ?, subject_id = ?, classroom_id = ?, 
                           day_of_week = ?, start_time_slot_id = ?, end_time_slot_id = ?, 
                           co_user_id = ?, co_user_id_2 = ?, max_teachers = ?, current_teachers = ?,
                           is_module_subject = 1, group_id = ?, updated_at = CURRENT_TIMESTAMP 
                           WHERE schedule_id = ? AND is_active = 1";
            $stmt = $conn->prepare($update_sql);
            if (!$stmt) {
                $conn->close();
                return handleError('เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error);
            }
            $stmt->bind_param("iiiisiiiiiiii", 
                $academic_year_id, $user_id, $subject_id, $classroom_id, 
                $day_of_week, $start_time_slot_id, $end_time_slot_id,
                $co_user_id, $co_user_id_2, $max_teachers, $current_teachers,
                $group_id, $schedule_id
            );
        } else {
            // กรณีไม่ใช่โมดูล ต้องเลือก year_level_id
            if (!$classroom_id || $classroom_id <= 0) {
                $conn->close();
                return handleError("กรุณาเลือกห้องเรียนสำหรับวิชาในสาขา");
            }
            if (!$year_level_id || $year_level_id <= 0) {
                $conn->close();
                return handleError("กรุณาเลือกชั้นปีสำหรับวิชาในสาขา");
            }
            $update_sql = "UPDATE teaching_schedules SET 
                           academic_year_id = ?, user_id = ?, subject_id = ?, classroom_id = ?, 
                           day_of_week = ?, year_level_id = ?, start_time_slot_id = ?, end_time_slot_id = ?, 
                           co_user_id = ?, co_user_id_2 = ?, max_teachers = ?, current_teachers = ?,
                           is_module_subject = 0, group_id = NULL, updated_at = CURRENT_TIMESTAMP 
                           WHERE schedule_id = ? AND is_active = 1";
            $stmt = $conn->prepare($update_sql);
            if (!$stmt) {
                $conn->close();
                return handleError('เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error);
            }
            $stmt->bind_param("iiiisiiiiiiii", 
                $academic_year_id, $user_id, $subject_id, $classroom_id, 
                $day_of_week, $year_level_id, $start_time_slot_id, $end_time_slot_id,
                $co_user_id, $co_user_id_2, $max_teachers, $current_teachers,
                $schedule_id
            );
        }

        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            $conn->close();
            if ($affected_rows > 0) {
                $teacher_info = $current_teachers > 1 ? " (อาจารย์ $current_teachers คน)" : "";
                error_log("Schedule updated successfully: ID=$schedule_id by user=$auth_user_id with $current_teachers teachers");
                return [
                    'status' => 'success',
                    'message' => 'อัปเดตตารางสอนสำเร็จ' . $teacher_info,
                    'schedule_id' => $schedule_id,
                    'updated_by' => $auth_user_id
                ];
            } else {
                return handleError('ไม่พบข้อมูลตารางสอนที่ต้องการอัปเดต');
            }
        } else {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            error_log("Error updating schedule: " . $error);
            return handleError('เกิดข้อผิดพลาดในการอัปเดตตารางสอน: ' . $error);
        }
        
    } catch (Exception $e) {
        error_log("Exception in updateSchedule: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการอัปเดตตารางสอน: ' . $e->getMessage());
    }
}

function deleteSchedule() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        return handleError('วิธีการร้องขอไม่ถูกต้อง');
    }
    
    try {
        $schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
        
        // Get auth from session (preferred) or URL parameters
        $auth_user_id = $_SESSION['user_id'] ?? (isset($_GET['auth_user_id']) ? intval($_GET['auth_user_id']) : 0);
        $auth_user_type = $_SESSION['user_type'] ?? (isset($_GET['auth_user_type']) ? $_GET['auth_user_type'] : 'teacher');
        
        if ($schedule_id <= 0) {
            return handleError('รหัสตารางสอนไม่ถูกต้อง');
        }
        
        error_log("Deleting schedule ID: $schedule_id by user: $auth_user_id (type: $auth_user_type)");
        
        $conn = connectMySQLi();
        
        // ตรวจสอบการมีอยู่และสิทธิ์
        $checkSQL = "SELECT user_id, is_external_subject FROM teaching_schedules WHERE schedule_id = ? AND is_active = 1";
        $stmt = $conn->prepare($checkSQL);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            return handleError('ไม่พบตารางสอนที่ต้องการลบ');
        }
        
        $schedule_data = $result->fetch_assoc();
        $stmt->close();
        
        // ตรวจสอบสิทธิ์: teacher ลบได้เฉพาะตารางตัวเอง
        if ($auth_user_type === 'teacher' && intval($schedule_data['user_id']) !== $auth_user_id) {
            $conn->close();
            return handleError('คุณไม่มีสิทธิ์ลบตารางสอนนี้');
        }
        
        // ตรวจสอบการใช้งานใน class_sessions
        $checkSessionsSQL = "SELECT COUNT(*) as session_count FROM class_sessions WHERE schedule_id = ?";
        $stmt = $conn->prepare($checkSessionsSQL);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $sessions = $result->fetch_assoc();
        $stmt->close();
        
        if ($sessions['session_count'] > 0) {
            $conn->close();
            return handleError('ไม่สามารถลบได้เนื่องจากมีการบันทึกการเรียนแล้ว กรุณาติดต่อผู้ดูแลระบบ');
        }
        
        // ลบตารางสอน (soft delete)
        $deleteSQL = "UPDATE teaching_schedules SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE schedule_id = ?";
        $stmt = $conn->prepare($deleteSQL);
        $stmt->bind_param("i", $schedule_id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            $conn->close();
            
            if ($affected_rows > 0) {
                error_log("Schedule deleted successfully: ID=$schedule_id by user=$auth_user_id");
                
                $is_external = intval($schedule_data['is_external_subject']);
                $message = $is_external ? 'ลบตารางสอนวิชานอกสาขาสำเร็จ' : 'ลบตารางสอนสำเร็จ';
                
                return [
                    'status' => 'success',
                    'message' => $message,
                    'deleted_id' => $schedule_id,
                    'deleted_by' => $auth_user_id,
                    'was_external_subject' => $is_external
                ];
            } else {
                return handleError('ไม่พบข้อมูลตารางสอนที่ต้องการลบ');
            }
        } else {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            
            error_log("Error deleting schedule: " . $error);
            return handleError('เกิดข้อผิดพลาดในการลบตารางสอน: ' . $error);
        }
        
    } catch (Exception $e) {
        error_log("Exception in deleteSchedule: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการลบตารางสอน: ' . $e->getMessage());
    }
}

function forceDeleteSchedule() {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        return handleError('วิธีการร้องขอไม่ถูกต้อง');
    }
    
    try {
        $schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
        $auth_user_id = isset($_GET['auth_user_id']) ? intval($_GET['auth_user_id']) : 0;
        $auth_user_type = isset($_GET['auth_user_type']) ? $_GET['auth_user_type'] : '';
        
        if ($schedule_id <= 0) {
            return handleError('รหัสตารางสอนไม่ถูกต้อง');
        }
        
        // เฉพาะ admin เท่านั้นที่สามารถ force delete ได้
        if ($auth_user_type !== 'admin') {
            return handleError('คุณไม่มีสิทธิ์ลบแบบบังคับ');
        }
        
        $conn = connectMySQLi();
        
        // ตรวจสอบข้อมูลตารางสอนก่อนลบ
        $checkSQL = "SELECT is_external_subject FROM teaching_schedules WHERE schedule_id = ?";
        $stmt = $conn->prepare($checkSQL);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            return handleError('ไม่พบตารางสอนที่ต้องการลบ');
        }
        
        $schedule_data = $result->fetch_assoc();
        $is_external = intval($schedule_data['is_external_subject']);
        $stmt->close();
        
        $conn->begin_transaction();
        
        // ลบ class_sessions ที่เกี่ยวข้อง
        $deleteSessionsSQL = "DELETE FROM class_sessions WHERE schedule_id = ?";
        $stmt = $conn->prepare($deleteSessionsSQL);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $sessionsDeleted = $stmt->affected_rows;
        $stmt->close();
        
        // ลบ compensation_logs ที่เกี่ยวข้อง
        $deleteCompensationSQL = "DELETE FROM compensation_logs WHERE schedule_id = ?";
        $stmt = $conn->prepare($deleteCompensationSQL);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $compensationDeleted = $stmt->affected_rows;
        $stmt->close();
        
        // ลบตารางสอน
        $deleteScheduleSQL = "DELETE FROM teaching_schedules WHERE schedule_id = ?";
        $stmt = $conn->prepare($deleteScheduleSQL);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $scheduleDeleted = $stmt->affected_rows;
        $stmt->close();
        
        $conn->commit();
        $conn->close();
        
        error_log("Force delete completed: Schedule=$scheduleDeleted, Sessions=$sessionsDeleted, Compensations=$compensationDeleted");
        
        $subject_type = $is_external ? 'วิชานอกสาขา' : 'วิชาในสาขา';
        $message = "ลบตารางสอน{$subject_type}และข้อมูลที่เกี่ยวข้องสำเร็จ\n- บันทึกการเรียน: $sessionsDeleted รายการ\n- บันทึกการชดเชย: $compensationDeleted รายการ";
        
        return [
            'status' => 'success',
            'message' => $message,
            'deleted_id' => $schedule_id,
            'deleted_by' => $auth_user_id,
            'force_delete' => true,
            'was_external_subject' => $is_external,
            'details' => [
                'schedule_deleted' => $scheduleDeleted,
                'sessions_deleted' => $sessionsDeleted,
                'compensations_deleted' => $compensationDeleted
            ]
        ];
        
    } catch (Exception $e) {
        if (isset($conn) && $conn) {
            $conn->rollback();
            $conn->close();
        }
        
        error_log("Error in force delete: " . $e->getMessage());
        return handleError('เกิดข้อผิดพลาดในการลบแบบบังคับ: ' . $e->getMessage());
    }
}

function handleError($message, $details = null) {
    $error_info = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'details' => $details,
        'session_user_id' => $_SESSION['user_id'] ?? 'unknown',
        'session_user_type' => $_SESSION['user_type'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    error_log("API_ERROR: " . json_encode($error_info));
    
    return [
        'status' => 'error',
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getModuleGroups() {
    try {
        $conn = connectMySQLi();
        $sql = "SELECT mg.group_id, mg.module_id, mg.group_name, m.module_name,
                       CONCAT(mg.group_name, ' ', m.module_name) AS display_name
                FROM module_groups mg
                JOIN modules m ON mg.module_id = m.module_id
                ORDER BY mg.group_name, m.module_name";
        $result = $conn->query($sql);
        $groups = [];
        while ($row = $result->fetch_assoc()) {
            // เพิ่ม display_name สำหรับแสดงผล
            $groups[] = [
                'group_id' => $row['group_id'],
                'module_id' => $row['module_id'],
                'group_name' => $row['group_name'],
                'module_name' => $row['module_name'],
                'display_name' => $row['display_name']
            ];
        }
        $conn->close();
        return ['status' => 'success', 'data' => $groups];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึงกลุ่มโมดูล', $e->getMessage());
    }
}

function getYearLevelsByModuleGroup() {
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    if ($group_id <= 0) {
        return handleError('group_id ไม่ถูกต้อง');
    }
    try {
        $conn = connectMySQLi();
        $sql = "SELECT yl.year_level_id, yl.department, yl.class_year, yl.curriculum
                FROM module_group_year_levels mgyl
                JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                WHERE mgyl.group_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        $conn->close();
        return ['status' => 'success', 'data' => $data];
    } catch (Exception $e) {
        return handleError('เกิดข้อผิดพลาดในการดึง year_level', $e->getMessage());
    }
}
function checkYearLevelModuleConflict($conn, $academic_year_id, $year_level_id, $group_id, $day_of_week, $start_time_slot_id, $end_time_slot_id, $exclude_schedule_id = 0) {
    // ตรวจสอบรายวิชาปกติซ้ำกับโมดูล
    if ($year_level_id) {
        $sql = "SELECT ts.schedule_id
                FROM teaching_schedules ts
                WHERE ts.is_active = 1
                AND ts.academic_year_id = ?
                AND ts.day_of_week = ?
                AND (
                    (ts.year_level_id = ? AND ts.is_module_subject = 0)
                    OR
                    (ts.group_id IN (
                        SELECT group_id FROM module_group_year_levels WHERE year_level_id = ?
                    ) AND ts.is_module_subject = 1)
                )
                AND (
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?)
                )";
        if ($exclude_schedule_id > 0) {
            $sql .= " AND ts.schedule_id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isiiiii", $academic_year_id, $day_of_week, $year_level_id, $year_level_id, $end_time_slot_id, $start_time_slot_id, $exclude_schedule_id);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isiiii", $academic_year_id, $day_of_week, $year_level_id, $year_level_id, $end_time_slot_id, $start_time_slot_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $conflict = $result->num_rows > 0;
        $stmt->close();
        if ($conflict) {
            return "ชั้นปีนี้มีเรียนในกลุ่มโมดูลหรือรายวิชาปกติช่วงเวลานี้แล้ว";
        }
    }
    // ตรวจสอบรายวิชาโมดูลซ้ำกับรายวิชาปกติ
    if ($group_id) {
        $sql = "SELECT ts.schedule_id
                FROM teaching_schedules ts
                WHERE ts.is_active = 1
                AND ts.academic_year_id = ?
                AND ts.day_of_week = ?
                AND (
                    (ts.year_level_id IN (
                        SELECT year_level_id FROM module_group_year_levels WHERE group_id = ?
                    ) AND ts.is_module_subject = 0)
                    OR
                    (ts.group_id = ? AND ts.is_module_subject = 1)
                )
                AND (
                    (ts.start_time_slot_id <= ? AND ts.end_time_slot_id >= ?)
                )";
        if ($exclude_schedule_id > 0) {
            $sql .= " AND ts.schedule_id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isiiiii", $academic_year_id, $day_of_week, $group_id, $group_id, $end_time_slot_id, $start_time_slot_id, $exclude_schedule_id);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isiiii", $academic_year_id, $day_of_week, $group_id, $group_id, $end_time_slot_id, $start_time_slot_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $conflict = $result->num_rows > 0;
        $stmt->close();
        if ($conflict) {
            return "ชั้นปีในกลุ่มโมดูลนี้มีเรียนรายวิชาปกติหรือโมดูลช่วงเวลานี้แล้ว";
        }
    }
    return null;
}
?>