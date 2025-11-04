<?php
// ไฟล์ API สำหรับจัดการข้อมูลชั้นปี (Updated with Config)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Include การตั้งค่าฐานข้อมูล
require_once 'config.php';

// Debug: Log ข้อมูลที่ได้รับ
error_log("=== API Request Debug ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Headers: " . json_encode(getallheaders()));
error_log("GET: " . json_encode($_GET));
error_log("POST: " . json_encode($_POST));
error_log("Raw Input: " . file_get_contents('php://input'));
$method = $_SERVER['REQUEST_METHOD'];
// ตรวจสอบว่าเป็นคำขอ OPTIONS หรือไม่ (สำหรับ CORS preflight)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'update_module_group') {
    // อ่าน raw input และ decode JSON
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg()]);
        exit;
    }

    if (
        !isset($data['group_id']) ||
        !isset($data['module_id']) ||
        !isset($data['year_level_ids']) ||
        !is_array($data['year_level_ids']) ||
        count($data['year_level_ids']) === 0
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ต้องระบุ group_id, module_id และ year_level_ids (array อย่างน้อย 1 รายการ)',
            'debug' => [
                'group_id' => isset($data['group_id']) ? $data['group_id'] : null,
                'module_id' => isset($data['module_id']) ? $data['module_id'] : null,
                'year_level_ids' => isset($data['year_level_ids']) ? $data['year_level_ids'] : null,
                'raw_input' => $rawInput
            ]
        ]);
        exit;
    }

    $group_name = isset($data['group_name']) ? $data['group_name'] : null;
    updateModuleGroup($data['group_id'], $data['module_id'], $data['year_level_ids'], $group_name);
    exit;
}

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_all_modules') {
    getAllModules();
    exit;
}

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_all_year_levels') {
    getAllYearLevels();
    exit;
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'set_year_levels_for_module') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['module_id']) || !isset($data['year_level_ids'])) {
        echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ module_id และ year_level_ids']);
        exit;
    }
    setYearLevelsForModule($data['module_id'], $data['year_level_ids']);
    exit;
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_module_group') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['module_id']) || !isset($data['year_level_ids'])) {
        echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ module_id และ year_level_ids']);
        exit;
    }
    $group_name = isset($data['group_name']) ? $data['group_name'] : null;
    createModuleGroup($data['module_id'], $data['year_level_ids'], $group_name);
    exit;
}

// ดึง group ทั้งหมดของโมดูล พร้อม year_levels ในแต่ละ group
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_module_groups') {
    $module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : null;
    getModuleGroups($module_id);
    exit;
}

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_module') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ id']);
        exit;
    }
    getModule($id);
    exit;
}

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_module') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg()]);
        exit;
    }
    createModule($data);
    exit;
}

if ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'update_module') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg()]);
        exit;
    }
    updateModule($data);
    exit;
}

if ($method === 'DELETE' && isset($_GET['action']) && $_GET['action'] === 'delete_module' && isset($_GET['module_id'])) {
    $module_id = intval($_GET['module_id']);
    deleteModule($module_id);
    exit;
}

if (in_array($method, ['POST', 'PUT'])) {
    $rawInput = file_get_contents('php://input');
    error_log("Raw input: " . $rawInput);
    
    if (empty($rawInput)) {
        error_log("No input data received");
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ไม่ได้รับข้อมูล']);
        exit;
    }
}

try {
    switch ($method) {
        case 'GET':
            error_log("Processing GET request");
            if (isset($_GET['id'])) {
                getYearLevel($_GET['id']);
            } else {
                getAllYearLevels();
            }
            break;
            
        case 'POST':
            error_log("Processing POST request");
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg()]);
                exit;
            }
            error_log("POST data: " . json_encode($data));
            createYearLevel($data);
            break;
            
        case 'PUT':
            error_log("Processing PUT request");
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg()]);
                exit;
            }
            error_log("PUT data: " . json_encode($data));
            
            if (!isset($data['year_level_id'])) {
                error_log("Missing year_level_id in PUT request");
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ year_level_id']);
                exit;
            }
            updateYearLevel($data);
            break;
            
        case 'DELETE':
            error_log("Processing DELETE request");
            $data = json_decode(file_get_contents('php://input'), true);
            $id = isset($_GET['id']) ? $_GET['id'] : (isset($data['year_level_id']) ? $data['year_level_id'] : null);
            
            if (!$id) {
                error_log("Missing ID in DELETE request");
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ year_level_id']);
                exit;
            }
            error_log("DELETE ID: " . $id);
            deleteYearLevel($id);
            break;
        case 'get_modules':
            if (isset($_GET['year_level_id'])) {
                getModulesByYearLevel($_GET['year_level_id']);
                exit;
            }
            break;
        case 'get_year_levels_by_module':
            if (isset($_GET['module_id'])) {
                getYearLevelsByModule($_GET['module_id']);
                exit;
            }
            break;
        case 'set_modules':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                if (!isset($data['year_level_id']) || !isset($data['module_ids'])) {
                    echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ year_level_id และ module_ids']);
                    exit;
                }
                setYearLevelModules($data['year_level_id'], $data['module_ids']);
                exit;
            }
            break;
        default:
            error_log("Method not allowed: " . $method);
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed: ' . $method]);
            break;
    }
} catch (Exception $e) {
    error_log("Uncaught exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในเซิร์ฟเวอร์: ' . $e->getMessage()]);
}

// ฟังก์ชันดึงข้อมูลชั้นปีทั้งหมด
function getAllYearLevels() {
    try {
        error_log("Getting all year levels");
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT * FROM year_levels ORDER BY department, class_year, curriculum");
        $stmt->execute();
        $yearLevels = $stmt->fetchAll();
        
        error_log("Found " . count($yearLevels) . " year levels");
        echo json_encode(['status' => 'success', 'data' => $yearLevels]);
    } catch (PDOException $e) {
        error_log("Error in getAllYearLevels: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันดึงข้อมูลชั้นปีตาม ID
function getYearLevel($id) {
    try {
        error_log("Getting year level ID: " . $id);
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT * FROM year_levels WHERE year_level_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $yearLevel = $stmt->fetch();
        
        if ($yearLevel) {
            error_log("Year level found");
            echo json_encode(['status' => 'success', 'data' => $yearLevel]);
        } else {
            error_log("Year level not found");
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลชั้นปี']);
        }
    } catch (PDOException $e) {
        error_log("Error in getYearLevel: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันเพิ่มข้อมูลชั้นปีใหม่
function createYearLevel($data) {
    try {
        error_log("Creating new year level with data: " . json_encode($data));
        
        // ตรวจสอบข้อมูลที่จำเป็น
        if (!isset($data['department']) || !isset($data['class_year']) || !isset($data['curriculum'])) {
            error_log("Missing required fields");
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ department, class_year และ curriculum']);
            return;
        }
        
        $conn = connectDB();
        
        // ตรวจสอบว่าชั้นปีซ้ำหรือไม่
        $stmt = $conn->prepare("SELECT year_level_id FROM year_levels WHERE department = :department AND class_year = :class_year AND curriculum = :curriculum");
        $stmt->bindParam(':department', $data['department'], PDO::PARAM_STR);
        $stmt->bindParam(':class_year', $data['class_year'], PDO::PARAM_STR);
        $stmt->bindParam(':curriculum', $data['curriculum'], PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            error_log("Duplicate year level found");
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'ชั้นปีนี้มีอยู่แล้ว']);
            return;
        }

        // เพิ่มข้อมูลใหม่
        $sql = "INSERT INTO year_levels (department, class_year, curriculum) VALUES (:department, :class_year, :curriculum)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':department', $data['department'], PDO::PARAM_STR);
        $stmt->bindParam(':class_year', $data['class_year'], PDO::PARAM_STR);
        $stmt->bindParam(':curriculum', $data['curriculum'], PDO::PARAM_STR);
        $stmt->execute();
        
        $newId = $conn->lastInsertId();
        error_log("Year level created with ID: " . $newId);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'เพิ่มข้อมูลชั้นปีสำเร็จ',
            'data' => ['year_level_id' => $newId]
        ]);
    } catch (PDOException $e) {
        error_log("Error in createYearLevel: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันอัพเดทข้อมูลชั้นปี
function updateYearLevel($data) {
    try {
        error_log("Updating year level with data: " . json_encode($data));
        
        $conn = connectDB();
        
        // ตรวจสอบว่ามีชั้นปีนี้อยู่หรือไม่
        $stmt = $conn->prepare("SELECT year_level_id FROM year_levels WHERE year_level_id = :id");
        $stmt->bindParam(':id', $data['year_level_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            error_log("Year level not found for update");
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลชั้นปี']);
            return;
        }
        
        // ตรวจสอบว่าชั้นปีซ้ำกับรายการอื่นหรือไม่
        if (isset($data['department']) && isset($data['class_year']) && isset($data['curriculum'])) {
            $stmt = $conn->prepare("
                SELECT year_level_id FROM year_levels 
                WHERE department = :department AND class_year = :class_year AND curriculum = :curriculum 
                AND year_level_id != :id
            ");
            $stmt->bindParam(':department', $data['department'], PDO::PARAM_STR);
            $stmt->bindParam(':class_year', $data['class_year'], PDO::PARAM_STR);
            $stmt->bindParam(':curriculum', $data['curriculum'], PDO::PARAM_STR);
            $stmt->bindParam(':id', $data['year_level_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                error_log("Duplicate year level found during update");
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'ชั้นปีนี้มีอยู่แล้ว']);
                return;
            }
        }
        
        // สร้าง SQL สำหรับอัพเดท
        $sql = "UPDATE year_levels SET ";
        $params = [];
        
        if (isset($data['department'])) {
            $sql .= "department = :department, ";
            $params[':department'] = $data['department'];
        }
        
        if (isset($data['class_year'])) {
            $sql .= "class_year = :class_year, ";
            $params[':class_year'] = $data['class_year'];
        }
        
        if (isset($data['curriculum'])) {
            $sql .= "curriculum = :curriculum, ";
            $params[':curriculum'] = $data['curriculum'];
        }
        
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE year_level_id = :id";
        $params[':id'] = $data['year_level_id'];
        
        if (count($params) <= 1) {
            error_log("No data to update");
            echo json_encode(['status' => 'success', 'message' => 'ไม่มีข้อมูลที่ต้องอัพเดท']);
            return;
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $param => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($param, $value, $type);
        }
        
        $stmt->execute();
        error_log("Year level updated successfully");
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'อัพเดทข้อมูลชั้นปีสำเร็จ'
        ]);
    } catch (PDOException $e) {
        error_log("Error in updateYearLevel: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันลบข้อมูลชั้นปี
function deleteYearLevel($id) {
    try {
        error_log("Deleting year level ID: " . $id);
        
        $conn = connectDB();
        
        // ตรวจสอบว่ามีการใช้งานชั้นปีนี้ในตารางอื่นหรือไม่
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM teaching_schedules WHERE year_level_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $teachingScheduleCount = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM student_courses WHERE year_level_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $studentCourseCount = $stmt->fetch()['count'];
        
        if ($teachingScheduleCount > 0 || $studentCourseCount > 0) {
            error_log("Cannot delete: year level is in use");
            http_response_code(409);
            echo json_encode([
                'status' => 'error', 
                'message' => 'ไม่สามารถลบชั้นปีนี้ได้เนื่องจากมีการใช้งานในตารางสอนหรือตารางนักศึกษา'
            ]);
            return;
        }
        
        // ลบข้อมูลชั้นปี
        $stmt = $conn->prepare("DELETE FROM year_levels WHERE year_level_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            error_log("Year level deleted successfully");
            echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลชั้นปีสำเร็จ']);
        } else {
            error_log("Year level not found for deletion");
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลชั้นปี']);
        }
    } catch (PDOException $e) {
        error_log("Error in deleteYearLevel: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ====== Mapping Functions ======
function getModulesByYearLevel($year_level_id) {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT m.module_id, m.module_name, m.description
            FROM year_level_modules ylm
            JOIN modules m ON ylm.module_id = m.module_id
            WHERE ylm.year_level_id = :year_level_id");
        $stmt->bindParam(':year_level_id', $year_level_id, PDO::PARAM_INT);
        $stmt->execute();
        $modules = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $modules]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getYearLevelsByModule($module_id) {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT yl.year_level_id, yl.department, yl.class_year, yl.curriculum
            FROM year_level_modules ylm
            JOIN year_levels yl ON ylm.year_level_id = yl.year_level_id
            WHERE ylm.module_id = :module_id");
        $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $stmt->execute();
        $year_levels = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $year_levels]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function setYearLevelModules($year_level_id, $module_ids) {
    try {
        $conn = connectDB();
        $conn->beginTransaction();
        // ลบ mapping เดิม
        $stmt = $conn->prepare("DELETE FROM year_level_modules WHERE year_level_id = :year_level_id");
        $stmt->bindParam(':year_level_id', $year_level_id, PDO::PARAM_INT);
        $stmt->execute();
        // เพิ่ม mapping ใหม่
        $stmt = $conn->prepare("INSERT INTO year_level_modules (year_level_id, module_id) VALUES (:year_level_id, :module_id)");
        foreach ($module_ids as $module_id) {
            $stmt->bindParam(':year_level_id', $year_level_id, PDO::PARAM_INT);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกการจับคู่สำเร็จ']);
    } catch (PDOException $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
// เพิ่มฟังก์ชัน mapping โมดูลกับหลายชั้นปี
function setYearLevelsForModule($module_id, $year_level_ids) {
    try {
        $conn = connectDB();
        $conn->beginTransaction();
        // ลบ mapping เดิมของโมดูลนี้
        $stmt = $conn->prepare("DELETE FROM year_level_modules WHERE module_id = :module_id");
        $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $stmt->execute();
        // เพิ่ม mapping ใหม่
        $stmt = $conn->prepare("INSERT INTO year_level_modules (year_level_id, module_id) VALUES (:year_level_id, :module_id)");
        foreach ($year_level_ids as $year_level_id) {
            $stmt->bindParam(':year_level_id', $year_level_id, PDO::PARAM_INT);
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกการจับคู่สำเร็จ']);
    } catch (PDOException $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
function getAllModules() {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT * FROM modules ORDER BY module_id ASC");
        $stmt->execute();
        $modules = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $modules]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันสร้าง group ใหม่และ mapping กับ year_levels
function createModuleGroup($module_id, $year_level_ids, $group_name = null) {
    try {
        $conn = connectDB();
        $conn->beginTransaction();

        // สร้าง group ใหม่
        $stmt = $conn->prepare("INSERT INTO module_groups (module_id, group_name) VALUES (:module_id, :group_name)");
        $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $stmt->bindParam(':group_name', $group_name, PDO::PARAM_STR);
        $stmt->execute();
        $group_id = $conn->lastInsertId();

        // mapping กับ year_levels
        $stmt = $conn->prepare("INSERT INTO module_group_year_levels (group_id, year_level_id) VALUES (:group_id, :year_level_id)");
        foreach ($year_level_ids as $year_level_id) {
            $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
            $stmt->bindParam(':year_level_id', $year_level_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'สร้างกลุ่มและจับคู่สำเร็จ', 'group_id' => $group_id]);
    } catch (PDOException $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันดึง group ทั้งหมดของโมดูล (หรือทั้งหมดถ้าไม่ระบุ module_id)
function getModuleGroups($module_id = null) {
    try {
        $conn = connectDB();
        if ($module_id) {
            $stmt = $conn->prepare("SELECT * FROM module_groups WHERE module_id = :module_id ORDER BY group_id ASC");
            $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("SELECT * FROM module_groups ORDER BY group_id ASC");
            $stmt->execute();
        }
        $groups = $stmt->fetchAll();

        // ดึง year_levels ของแต่ละ group
        foreach ($groups as &$group) {
            $stmt2 = $conn->prepare("SELECT yl.* FROM module_group_year_levels mgyl JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id WHERE mgyl.group_id = :group_id");
            $stmt2->bindParam(':group_id', $group['group_id'], PDO::PARAM_INT);
            $stmt2->execute();
            $group['year_levels'] = $stmt2->fetchAll();
        }

        echo json_encode(['status' => 'success', 'data' => $groups]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

if ($method === 'DELETE' && isset($_GET['action']) && $_GET['action'] === 'delete_module_group' && isset($_GET['group_id'])) {
    $group_id = intval($_GET['group_id']);
    deleteModuleGroup($group_id);
    exit;
}

// ฟังก์ชันแก้ไข group และ mapping กับ year_levels
function updateModuleGroup($group_id, $module_id, $year_level_ids, $group_name = null) {
    try {
        $conn = connectDB();
        $conn->beginTransaction();

        $stmt = $conn->prepare("UPDATE module_groups SET module_id = :module_id, group_name = :group_name WHERE group_id = :group_id");
        $stmt->bindParam(':module_id', $module_id, PDO::PARAM_INT);
        $stmt->bindParam(':group_name', $group_name, PDO::PARAM_STR);
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM module_group_year_levels WHERE group_id = :group_id");
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO module_group_year_levels (group_id, year_level_id) VALUES (:group_id, :year_level_id)");
        foreach ($year_level_ids as $year_level_id) {
            $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
            $stmt->bindParam(':year_level_id', $year_level_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'อัปเดตกลุ่มสำเร็จ']);
    } catch (PDOException $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// ฟังก์ชันลบ group
function deleteModuleGroup($group_id) {
    try {
        $conn = connectDB();
        $conn->beginTransaction();

        // ลบ mapping กับ year_levels
        $stmt = $conn->prepare("DELETE FROM module_group_year_levels WHERE group_id = :group_id");
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        // ลบ group
        $stmt = $conn->prepare("DELETE FROM module_groups WHERE group_id = :group_id");
        $stmt->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'ลบกลุ่มสำเร็จ']);
    } catch (PDOException $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// เพิ่มฟังก์ชันจัดการโมดูล
function getModule($id) {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT module_id, module_name, description FROM modules WHERE module_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $module = $stmt->fetch();
        if ($module) {
            echo json_encode(['status' => 'success', 'data' => $module]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบโมดูล']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function createModule($data) {
    try {
        if (!isset($data['module_name']) || trim($data['module_name']) === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'module_name ต้องไม่ว่าง']);
            return;
        }
        $module_name = trim($data['module_name']);
        $description = isset($data['description']) ? trim($data['description']) : null;

        $conn = connectDB();
        $stmt = $conn->prepare("SELECT module_id FROM modules WHERE module_name = :module_name");
        $stmt->bindParam(':module_name', $module_name, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'ชื่อโมดูลนี้มีอยู่แล้ว']);
            return;
        }

        $stmt = $conn->prepare("INSERT INTO modules (module_name, description) VALUES (:module_name, :description)");
        $stmt->bindParam(':module_name', $module_name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->execute();
        $newId = $conn->lastInsertId();
        echo json_encode(['status' => 'success', 'message' => 'สร้างโมดูลสำเร็จ', 'data' => ['module_id' => $newId]]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function updateModule($data) {
    try {
        if (!isset($data['module_id']) || !is_numeric($data['module_id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ต้องระบุ module_id สำหรับการอัพเดท']);
            return;
        }
        $module_id = intval($data['module_id']);
        $module_name = isset($data['module_name']) ? trim($data['module_name']) : null;
        $description = isset($data['description']) ? trim($data['description']) : null;

        if (($module_name === null || $module_name === '') && $description === null) {
            echo json_encode(['status' => 'success', 'message' => 'ไม่มีข้อมูลที่ต้องอัพเดท']);
            return;
        }

        $conn = connectDB();

        $stmt = $conn->prepare("SELECT module_id FROM modules WHERE module_id = :id");
        $stmt->bindParam(':id', $module_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบโมดูลที่ต้องการอัพเดท']);
            return;
        }

        if ($module_name !== null && $module_name !== '') {
            $stmt = $conn->prepare("SELECT module_id FROM modules WHERE module_name = :module_name AND module_id != :id");
            $stmt->bindParam(':module_name', $module_name, PDO::PARAM_STR);
            $stmt->bindParam(':id', $module_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'ชื่อโมดูลนี้มีอยู่แล้ว']);
                return;
            }
        }

        $fields = [];
        $params = [];
        if ($module_name !== null && $module_name !== '') {
            $fields[] = "module_name = :module_name";
            $params[':module_name'] = $module_name;
        }
        if ($description !== null) {
            $fields[] = "description = :description";
            $params[':description'] = $description;
        }
        $sql = "UPDATE modules SET " . implode(", ", $fields) . " WHERE module_id = :id";
        $params[':id'] = $module_id;
        $stmt = $conn->prepare($sql);
        foreach ($params as $p => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($p, $v, $type);
        }
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'อัพเดทโมดูลสำเร็จ']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function deleteModule($module_id) {
    try {
        $conn = connectDB();
        $stmt = $conn->prepare("DELETE FROM modules WHERE module_id = :id");
        $stmt->bindParam(':id', $module_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'ลบโมดูลสำเร็จ']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบโมดูล']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

?>