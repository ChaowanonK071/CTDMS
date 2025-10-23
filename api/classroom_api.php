<?php
// classroom_api.php - API สำหรับจัดการข้อมูลห้องเรียน (Updated with Config)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-HTTP-Method-Override");

// Include การตั้งค่าฐานข้อมูล
require_once 'config.php';

// ตรวจสอบโครงสร้างตารางและได้ชื่อคอลัมน์ที่ถูกต้อง
function getTableColumns() {
    static $columns = null;
    
    if ($columns === null) {
        try {
            $conn = connectDB();
            $stmt = $conn->prepare("DESCRIBE classrooms");
            $stmt->execute();
            $tableStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $columns = ['id' => null, 'room_number' => null, 'building' => null];
            
            foreach ($tableStructure as $column) {
                $columnName = $column['Field'];
                $key = $column['Key'];
                
                // หา Primary Key
                if ($key === 'PRI') {
                    $columns['id'] = $columnName;
                }
                
                // หาคอลัมน์ room_number
                if (stripos($columnName, 'room') !== false && stripos($columnName, 'number') !== false) {
                    $columns['room_number'] = $columnName;
                } elseif ($columnName === 'room_number') {
                    $columns['room_number'] = $columnName;
                }
                
                // หาคอลัมน์ building
                if (stripos($columnName, 'building') !== false) {
                    $columns['building'] = $columnName;
                }
            }
            
            // กำหนดค่า default หากไม่พบ
            if (!$columns['id']) {
                // หาคอลัมน์ที่มี id ในชื่อ
                foreach ($tableStructure as $column) {
                    if (stripos($column['Field'], 'id') !== false) {
                        $columns['id'] = $column['Field'];
                        break;
                    }
                }
            }
            if (!$columns['room_number']) $columns['room_number'] = 'room_number';
            if (!$columns['building']) $columns['building'] = 'building';
            
        } catch(PDOException $e) {
            // ถ้าเกิดข้อผิดพลาด ให้ใช้ชื่อ default
            $columns = [
                'id' => 'classroom_id',
                'room_number' => 'room_number', 
                'building' => 'building'
            ];
        }
    }
    
    return $columns;
}

// ตรวจสอบ HTTP Method รวมถึง Method Override
function getActualMethod() {
    // ตรวจสอบ X-HTTP-Method-Override header ก่อน
    if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }
    
    // ตรวจสอบใน POST data
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["_method"])) {
        return strtoupper($_POST["_method"]);
    }
    
    return $_SERVER["REQUEST_METHOD"];
}

// ตรวจสอบการส่ง request แบบ POST
function isPost() {
    return getActualMethod() === "POST";
}

// ตรวจสอบการส่ง request แบบ GET
function isGet() {
    return getActualMethod() === "GET";
}

// ตรวจสอบการส่ง request แบบ PUT
function isPut() {
    return getActualMethod() === "PUT";
}

// ตรวจสอบการส่ง request แบบ DELETE
function isDelete() {
    return getActualMethod() === "DELETE";
}

// รับข้อมูลจาก Request Body (สำหรับ PUT, POST)
function getRequestBody() {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data && isPost()) {
        $data = $_POST;
    }
    
    return $data;
}

// ฟังก์ชันสำหรับดึงข้อมูลห้องเรียนทั้งหมด
function getAllClassrooms() {
    try {
        $conn = connectDB();
        $columns = getTableColumns();
        
        $stmt = $conn->prepare("SELECT * FROM classrooms ORDER BY " . $columns['room_number']);
        $stmt->execute();
        $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            "status" => "success",
            "data" => $classrooms,
            "message" => "ดึงข้อมูลห้องเรียนทั้งหมดสำเร็จ",
            "count" => count($classrooms),
            "debug_columns" => $columns // เพิ่มข้อมูล debug
        ];
    } catch(PDOException $e) {
        return [
            "status" => "error",
            "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
        ];
    }
}

// ฟังก์ชันสำหรับดึงข้อมูลห้องเรียนตาม ID
function getClassroomById($id) {
    try {
        $conn = connectDB();
        $columns = getTableColumns();
        
        $stmt = $conn->prepare("SELECT * FROM classrooms WHERE " . $columns['id'] . " = :id");
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $classroom = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($classroom) {
            return [
                "status" => "success",
                "data" => $classroom,
                "message" => "ดึงข้อมูลห้องเรียนสำเร็จ"
            ];
        } else {
            return [
                "status" => "error",
                "message" => "ไม่พบข้อมูลห้องเรียนที่ต้องการ"
            ];
        }
    } catch(PDOException $e) {
        return [
            "status" => "error",
            "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
        ];
    }
}

// ฟังก์ชันสำหรับเพิ่มข้อมูลห้องเรียน
function createClassroom($data) {
    if (!isset($data["room_number"]) || empty($data["room_number"])) {
        return [
            "status" => "error",
            "message" => "กรุณากรอกหมายเลขห้องเรียน"
        ];
    }
    
    try {
        $conn = connectDB();
        $columns = getTableColumns();
        
        // ตรวจสอบว่ามีห้องเรียนนี้อยู่แล้วหรือไม่
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE " . $columns['room_number'] . " = :room_number");
        $checkStmt->bindParam(":room_number", $data["room_number"]);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                "status" => "error",
                "message" => "มีห้องเรียนนี้อยู่ในระบบแล้ว"
            ];
        }
        
        // เพิ่มข้อมูลห้องเรียน
        $stmt = $conn->prepare("INSERT INTO classrooms (" . $columns['room_number'] . ", " . $columns['building'] . ") VALUES (:room_number, :building)");
        $stmt->bindParam(":room_number", $data["room_number"]);
        $stmt->bindParam(":building", $data["building"]);
        $stmt->execute();
        
        $newId = $conn->lastInsertId();
        
        return [
            "status" => "success",
            "message" => "เพิ่มข้อมูลห้องเรียนสำเร็จ",
            "id" => $newId
        ];
    } catch(PDOException $e) {
        return [
            "status" => "error",
            "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
        ];
    }
}

// ฟังก์ชันสำหรับอัปเดตข้อมูลห้องเรียน
function updateClassroom($id, $data) {
    if (!isset($data["room_number"]) || empty($data["room_number"])) {
        return [
            "status" => "error",
            "message" => "กรุณากรอกหมายเลขห้องเรียน"
        ];
    }
    
    try {
        $conn = connectDB();
        $columns = getTableColumns();
        
        // ตรวจสอบว่าข้อมูลห้องเรียนที่ต้องการแก้ไขมีอยู่จริงหรือไม่
        $existStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE " . $columns['id'] . " = :id");
        $existStmt->bindParam(":id", $id);
        $existStmt->execute();
        
        if ($existStmt->fetchColumn() == 0) {
            return [
                "status" => "error",
                "message" => "ไม่พบข้อมูลห้องเรียนที่ต้องการแก้ไข"
            ];
        }
        
        // ตรวจสอบว่ามีห้องเรียนอื่นที่ใช้หมายเลขห้องนี้อยู่แล้วหรือไม่ (ยกเว้นห้องที่กำลังแก้ไข)
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM classrooms WHERE " . $columns['room_number'] . " = :room_number AND " . $columns['id'] . " != :id");
        $checkStmt->bindParam(":room_number", $data["room_number"]);
        $checkStmt->bindParam(":id", $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                "status" => "error",
                "message" => "มีห้องเรียนอื่นที่ใช้หมายเลขห้องนี้อยู่แล้ว"
            ];
        }
        
        // ดึงข้อมูลปัจจุบันเพื่อเปรียบเทียบ
        $currentStmt = $conn->prepare("SELECT * FROM classrooms WHERE " . $columns['id'] . " = :id");
        $currentStmt->bindParam(":id", $id);
        $currentStmt->execute();
        $currentData = $currentStmt->fetch(PDO::FETCH_ASSOC);
        
        // ตรวจสอบว่ามีการเปลี่ยนแปลงข้อมูลหรือไม่
        $building = $data["building"] ?? null;
        if ($currentData[$columns['room_number']] == $data["room_number"] && 
            $currentData[$columns['building']] == $building) {
            return [
                "status" => "success",
                "message" => "ข้อมูลไม่มีการเปลี่ยนแปลง"
            ];
        }
        
        // อัปเดตข้อมูลห้องเรียน
        $stmt = $conn->prepare("UPDATE classrooms SET " . $columns['room_number'] . " = :room_number, " . $columns['building'] . " = :building WHERE " . $columns['id'] . " = :id");
        $stmt->bindParam(":room_number", $data["room_number"]);
        $stmt->bindParam(":building", $building);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        return [
            "status" => "success",
            "message" => "อัปเดตข้อมูลห้องเรียนสำเร็จ",
            "debug_info" => [
                "affected_rows" => $stmt->rowCount(),
                "old_data" => $currentData,
                "new_data" => $data,
                "sql" => "UPDATE classrooms SET " . $columns['room_number'] . " = :room_number, " . $columns['building'] . " = :building WHERE " . $columns['id'] . " = :id"
            ]
        ];
        
    } catch(PDOException $e) {
        return [
            "status" => "error",
            "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
        ];
    }
}

// ฟังก์ชันสำหรับลบข้อมูลห้องเรียน
function deleteClassroom($id) {
    try {
        $conn = connectDB();
        $columns = getTableColumns();
        
        // ตรวจสอบว่าห้องเรียนนี้ถูกใช้ในตารางสอนหรือไม่
        // ใช้ชื่อคอลัมน์ที่ถูกต้องจากตาราง teaching_schedules
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM teaching_schedules WHERE classroom_id = :id");
        $checkStmt->bindParam(":id", $id);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            return [
                "status" => "error",
                "message" => "ไม่สามารถลบห้องเรียนนี้ได้ เนื่องจากมีการใช้งานในตารางสอน"
            ];
        }
        
        // ตรวจสอบว่าห้องเรียนนี้ถูกใช้ในตาราง compensation_logs หรือไม่
        try {
            $checkCompStmt = $conn->prepare("SELECT COUNT(*) FROM compensation_logs WHERE makeup_classroom_id = :id");
            $checkCompStmt->bindParam(":id", $id);
            $checkCompStmt->execute();
            
            if ($checkCompStmt->fetchColumn() > 0) {
                return [
                    "status" => "error",
                    "message" => "ไม่สามารถลบห้องเรียนนี้ได้ เนื่องจากมีการใช้งานในบันทึกการชดเชย"
                ];
            }
        } catch(PDOException $e) {
            // ถ้าไม่มีตาราง compensation_logs หรือคอลัมน์ ให้ข้ามไป
            // ไม่ต้องทำอะไร
        }
        
        // ลบข้อมูลห้องเรียน
        $stmt = $conn->prepare("DELETE FROM classrooms WHERE " . $columns['id'] . " = :id");
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return [
                "status" => "success",
                "message" => "ลบข้อมูลห้องเรียนสำเร็จ"
            ];
        } else {
            return [
                "status" => "error",
                "message" => "ไม่พบข้อมูลห้องเรียนที่ต้องการลบ"
            ];
        }
    } catch(PDOException $e) {
        return [
            "status" => "error",
            "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
        ];
    }
}

// ฟังก์ชันค้นหาห้องเรียน
function searchClassrooms($keyword) {
    try {
        $conn = connectDB();
        $columns = getTableColumns();
        $keyword = "%$keyword%";
        
        $stmt = $conn->prepare("SELECT * FROM classrooms WHERE " . $columns['room_number'] . " LIKE :keyword OR " . $columns['building'] . " LIKE :keyword ORDER BY " . $columns['room_number']);
        $stmt->bindParam(":keyword", $keyword);
        $stmt->execute();
        $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            "status" => "success",
            "data" => $classrooms,
            "message" => "ค้นหาข้อมูลห้องเรียนสำเร็จ",
            "count" => count($classrooms)
        ];
    } catch(PDOException $e) {
        return [
            "status" => "error",
            "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
        ];
    }
}

// เพิ่มข้อมูลเกี่ยวกับ API endpoints ที่มีให้ใช้งาน
function getApiDocumentation() {
    return [
        "status" => "info",
        "message" => "API Documentation - ต้องระบุ action เพื่อเรียกใช้งาน",
        "available_endpoints" => [
            [
                "action" => "getAll",
                "method" => "GET",
                "description" => "ดึงข้อมูลห้องเรียนทั้งหมด",
                "example" => "api_classroom.php?action=getAll"
            ],
            [
                "action" => "getById",
                "method" => "GET",
                "description" => "ดึงข้อมูลห้องเรียนตาม ID",
                "example" => "api_classroom.php?action=getById&id=1"
            ],
            [
                "action" => "create",
                "method" => "POST",
                "description" => "เพิ่มข้อมูลห้องเรียน",
                "example" => "api_classroom.php?action=create",
                "body" => '{"room_number": "18501", "building": "อาคารวิศวกรรม"}'
            ],
            [
                "action" => "update",
                "method" => "PUT (via POST with X-HTTP-Method-Override)",
                "description" => "อัปเดตข้อมูลห้องเรียน",
                "example" => "api_classroom.php?action=update&id=1",
                "body" => '{"room_number": "18501", "building": "อาคารวิศวกรรม"}'
            ],
            [
                "action" => "delete",
                "method" => "DELETE (via POST with X-HTTP-Method-Override)",
                "description" => "ลบข้อมูลห้องเรียน",
                "example" => "api_classroom.php?action=delete&id=1"
            ],
            [
                "action" => "search",
                "method" => "GET",
                "description" => "ค้นหาห้องเรียน",
                "example" => "api_classroom.php?action=search&keyword=18"
            ]
        ]
    ];
}

// ประมวลผล API Request
$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id = isset($_GET["id"]) ? $_GET["id"] : "";
$keyword = isset($_GET["keyword"]) ? $_GET["keyword"] : "";

// สำหรับ API Endpoint
switch ($action) {
    case "getAll":
        echo json_encode(getAllClassrooms(), JSON_UNESCAPED_UNICODE);
        break;
        
    case "getById":
        if (!$id) {
            echo json_encode([
                "status" => "error",
                "message" => "ไม่ได้ระบุ ID ของห้องเรียน"
            ], JSON_UNESCAPED_UNICODE);
            break;
        }
        echo json_encode(getClassroomById($id), JSON_UNESCAPED_UNICODE);
        break;
        
    case "create":
        if (isPost()) {
            $data = getRequestBody();
            echo json_encode(createClassroom($data), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "ต้องใช้เมธอด POST สำหรับการเพิ่มข้อมูล"
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case "update":
        if (isPut()) {
            $data = getRequestBody();
            echo json_encode(updateClassroom($id, $data), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "ต้องใช้เมธอด PUT สำหรับการอัปเดตข้อมูล"
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case "delete":
        if (isDelete()) {
            echo json_encode(deleteClassroom($id), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "ต้องใช้เมธอด DELETE สำหรับการลบข้อมูล"
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case "search":
        echo json_encode(searchClassrooms($keyword), JSON_UNESCAPED_UNICODE);
        break;
        
    case "":
        // ถ้าไม่ได้ระบุ action ให้แสดงรายละเอียด API
        echo json_encode(getApiDocumentation(), JSON_UNESCAPED_UNICODE);
        break;
        
    case "debug":
        // เพิ่ม debug endpoint เพื่อตรวจสอบโครงสร้างตาราง
        try {
            $columns = getTableColumns();
            $conn = connectDB();
            $stmt = $conn->prepare("DESCRIBE classrooms");
            $stmt->execute();
            $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ดึงข้อมูลตัวอย่าง
            $dataStmt = $conn->prepare("SELECT * FROM classrooms LIMIT 3");
            $dataStmt->execute();
            $sampleData = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                "status" => "debug",
                "table_structure" => $structure,
                "detected_columns" => $columns,
                "sample_data" => $sampleData,
                "message" => "ข้อมูลโครงสร้างตาราง classrooms"
            ], JSON_UNESCAPED_UNICODE);
        } catch(PDOException $e) {
            echo json_encode([
                "status" => "error",
                "message" => "ไม่สามารถตรวจสอบโครงสร้างตารางได้: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case "test_update":
        // เพิ่ม endpoint สำหรับทดสอบการอัปเดต
        $id = isset($_GET["id"]) ? $_GET["id"] : "";
        if (!$id) {
            echo json_encode([
                "status" => "error",
                "message" => "ไม่ได้ระบุ ID"
            ], JSON_UNESCAPED_UNICODE);
            break;
        }
        
        $testData = [
            "room_number" => "TEST" . time(),
            "building" => "ทดสอบ"
        ];
        
        echo json_encode(updateClassroom($id, $testData), JSON_UNESCAPED_UNICODE);
        break;
        
    default:
        echo json_encode([
            "status" => "error",
            "message" => "ไม่พบ API Endpoint ที่ต้องการเรียกใช้ '" . $action . "'",
            "hint" => "โปรดใช้ API endpoint ที่ถูกต้อง หรือดูรายละเอียดได้ที่ api_classroom.php (ไม่ต้องระบุ action)"
        ], JSON_UNESCAPED_UNICODE);
}
?>