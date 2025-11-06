<?php
/**
 * API สำหรับดึงข้อมูลวันหยุด
 */

// ตั้งค่า Error Reporting และ Output Buffer
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// เริ่ม Output Buffer
if (!ob_get_level()) {
    ob_start();
}

// ตั้งค่า Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ตั้งค่า error handler สำหรับ fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fatal Error: ' . $error['message'],
            'error' => 'Internal Server Error',
            'file' => basename($error['file']),
            'line' => $error['line'],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
});

// ตรวจสอบและโหลด config file
$config_loaded = false;
$config_paths = [
    __DIR__ . '/config.php'
];

foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        try {
            require_once $config_path;
            $config_loaded = true;
            error_log("Config loaded from: " . $config_path);
            break;
        } catch (Exception $e) {
            error_log("Error loading config from {$config_path}: " . $e->getMessage());
            continue;
        }
    }
}

if (!$config_loaded) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่พบไฟล์ config หรือโหลดไม่สำเร็จ',
        'error' => 'Configuration Error',
        'searched_paths' => $config_paths,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบฟังก์ชันที่จำเป็น
$required_functions = ['connectMySQLi', 'isLoggedIn', 'startSession'];
$missing_functions = [];

foreach ($required_functions as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}

if (!empty($missing_functions)) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ฟังก์ชันที่จำเป็นขาดหายไป: ' . implode(', ', $missing_functions),
        'error' => 'Missing Functions',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// เริ่ม session
try {
    startSession();
} catch (Exception $e) {
    error_log("Session start error: " . $e->getMessage());
}

// ฟังก์ชันช่วยเหลือสำหรับส่ง JSON response
if (!function_exists('apiJsonSuccess')) {
    function apiJsonSuccess($message, $data = null) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('apiJsonError')) {
    function apiJsonError($message, $code = 400, $data = null) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error' => 'API Error',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ตรวจสอบการเข้าสู่ระบบ
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !isLoggedIn()) {
    apiJsonError('ไม่ได้รับอนุญาต - กรุณาล็อกอินใหม่', 401);
}

// รับ action ที่ต้องการทำ
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ล้าง buffer ก่อนประมวลผล
if (ob_get_length()) {
    ob_clean();
}

// Log การเรียกใช้ API
error_log("API Holiday Data called - Action: {$action}, User: {$user_id}");

try {
    switch ($action) {
        case 'get_all_holidays':
            getAllHolidays();
            break;
        case 'get_holidays':
            getHolidays();
            break;
        case 'get_teacher_list':
            getTeacherList();
            break;
        case 'get_teaching_schedules':
            getTeachingSchedules();
            break;
        case 'get_compensation_logs':
            getCompensationLogs();
            break;
        case 'get_class_sessions':
            getClassSessions();
            break;
        case 'get_dashboard_data':
            getDashboardData();
            break;
        case 'get_stats':
            getStats();
            break;
        case 'get_holiday_summary':
            getHolidaySummary();
            break;
        default:
            apiJsonError('Action ไม่ถูกต้อง: ' . htmlspecialchars($action));
            break;
    }
} catch (Exception $e) {
    error_log('Holiday Data API Exception: ' . $e->getMessage());
    apiJsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
}
function getTeacherList() {
    global $user_id;
    try {
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }

        // ตรวจสอบสิทธิ์ผู้ใช้เรียก (admin หรือไม่)
        $stmt = $conn->prepare("SELECT user_type FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $teachers = [];

        if ($user && $user['user_type'] === 'admin') {
            // ผู้ดูแลระบบ: ส่งรายชื่ออาจารย์ทั้งหมด
            $sql = "SELECT user_id, title, name, lastname FROM users WHERE user_type IN ('teacher','admin') ORDER BY name, lastname";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }
        } else {
            // ผู้ใช้ทั่วไป (เช่น teacher): ส่งข้อมูลตัวเองเท่านั้น
            $sql = "SELECT user_id, title, name, lastname FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }
            $stmt->close();
        }

        $conn->close();
        apiJsonSuccess('ดึงรายชื่ออาจารย์สำเร็จ', ['teachers' => $teachers]);
    } catch (Exception $e) {
        error_log('getTeacherList Error: ' . $e->getMessage());
        apiJsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}
/**
 * ดึงข้อมูลวันหยุดทั้งหมด
 */
function getAllHolidays() {
    global $user_id;
    
    try {
        // ทดสอบการเชื่อมต่อฐานข้อมูล
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . ($conn->connect_error ?? 'Unknown error'));
        }
        
        // รับพารามิเตอร์
        $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? null;
        
        $sql = "SELECT 
                    h.holiday_id,
                    h.academic_year,
                    h.holiday_date,
                    h.holiday_name,
                    COALESCE(h.holiday_name, 'ไม่ระบุชื่อ') as english_name,
                    h.holiday_type,
                    h.is_active,
                    h.api_source,
                    h.created_at,
                    h.updated_at,
                    h.created_by,
                    CASE 
                        WHEN h.api_source = 'manual' THEN 1 
                        WHEN h.api_source IS NULL OR h.api_source = '' THEN 1 
                        ELSE 0 
                    END as is_custom,
                    ay.academic_year as academic_year_display,
                    ay.semester,
                    CONCAT(ay.academic_year, '/', COALESCE(ay.semester, 1)) as academic_year_semester,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(h.api_response_data, '$.notes')), 
                        ''
                    ) as notes,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(h.api_response_data, '$.english_name')), 
                        h.holiday_name
                    ) as english_name_from_api
                FROM public_holidays h
                LEFT JOIN academic_years ay ON h.academic_year = ay.academic_year";
        
        $params = [];
        $types = '';
        
        // เพิ่มเงื่อนไขกรองตาม academic_year_id
        if ($academic_year_id && is_numeric($academic_year_id)) {
            $sql .= " WHERE ay.academic_year_id = ?";
            $params[] = (int)$academic_year_id;
            $types .= 'i';
        } else {
            // หากไม่ระบุ academic_year_id ให้แสดงปีปัจจุบันหรือปีล่าสุด
            $sql .= " WHERE (ay.is_current = 1 OR h.academic_year >= YEAR(CURDATE()) + 543 - 1)";
        }
        
        // เพิ่มเงื่อนไขที่แสดงทั้งวันหยุดที่ active
        $sql .= " AND h.is_active = 1 ORDER BY h.holiday_date ASC";
        
        // Execute query
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Get result failed: ' . $stmt->error);
        }
        
        $holidays = [];
        while ($row = $result->fetch_assoc()) {
            // แปลงข้อมูล API response ถ้ามี
            $api_data = null;
            if (!empty($row['api_response_data'])) {
                $api_data = json_decode($row['api_response_data'], true);
            }
            
            // ตรวจสอบและแปลงข้อมูล
            $holiday = [
                'holiday_id' => (int)$row['holiday_id'],
                'academic_year' => (int)$row['academic_year'],
                'academic_year_display' => $row['academic_year_display'],
                'semester' => $row['semester'] ? (int)$row['semester'] : null,
                'academic_year_semester' => $row['academic_year_semester'],
                'holiday_date' => $row['holiday_date'],
                'holiday_name' => $row['holiday_name'] ?: 'ไม่ระบุชื่อ',
                'holiday_type' => $row['holiday_type'] ?: 'national',
                'is_active' => (bool)$row['is_active'],
                'is_custom' => (bool)$row['is_custom'],
                'api_source' => $row['api_source'],
                'notes' => $row['notes'] ?: '',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'created_by' => $row['created_by'],
                'api_data' => $api_data
            ];
            
            // กำหนด english_name ตามลำดับความสำคัญ
            if ($api_data && !empty($api_data['english_name'])) {
                $holiday['english_name'] = $api_data['english_name'];
            } else if (!empty($row['english_name_from_api'])) {
                $holiday['english_name'] = $row['english_name_from_api'];
            } else {
                $holiday['english_name'] = $row['holiday_name'];
            }
            
            $holidays[] = $holiday;
        }
        
        $stmt->close();
        $conn->close();
        
        // คำนวณสถิติ
        $total_count = count($holidays);
        $custom_count = count(array_filter($holidays, fn($h) => $h['is_custom']));
        $api_count = $total_count - $custom_count;
        
        // Log สำหรับ debug
        error_log("getAllHolidays - Total: {$total_count}, Custom: {$custom_count}, API: {$api_count}");
        
        // ส่งข้อมูลกลับ
        apiJsonSuccess('ดึงข้อมูลวันหยุดสำเร็จ', [
            'holidays' => $holidays,
            'total_count' => $total_count,
            'custom_count' => $custom_count,
            'api_count' => $api_count,
            'academic_year_id' => $academic_year_id,
            'query_info' => [
                'sql_executed' => !empty($params) ? 'With academic_year_id filter' : 'Current/Recent years only',
                'params_count' => count($params),
                'has_custom_holidays' => $custom_count > 0
            ]
        ]);
        
    } catch (Exception $e) {
        error_log('getAllHolidays Error: ' . $e->getMessage());
        apiJsonError('เกิดข้อผิดพลาดในการดึงข้อมูลวันหยุด: ' . $e->getMessage());
    }
}

/**
 * ดึงข้อมูลวันหยุดแบบเดิม
 */
function getHolidays() {
    getAllHolidays();
}

/**
 * ดึงข้อมูลตารางสอน
 */
function getTeachingSchedules() {
    global $user_id;
    try {
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? null;
        $teacher_id = $_POST['teacher_id'] ?? $_GET['teacher_id'] ?? null;

        $sql = "SELECT 
                    ts.schedule_id,
                    ts.day_of_week,
                    ts.is_active,
                    s.subject_code,
                    s.subject_name,
                    s.credits,
                    s.subject_type,
                    c.room_number,
                    c.building,
                    yl.department,
                    yl.class_year,
                    yl.curriculum,
                    start_ts.start_time,
                    end_ts.end_time,
                    start_ts.slot_number as start_slot,
                    end_ts.slot_number as end_slot,
                    CONCAT(start_ts.slot_number, '-', end_ts.slot_number) as time_slot_range,
                    u.title,
                    u.name,
                    u.lastname,
                    ay.academic_year,
                    ay.semester
                FROM teaching_schedules ts
                JOIN subjects s ON ts.subject_id = s.subject_id
                JOIN classrooms c ON ts.classroom_id = c.classroom_id
                JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
                JOIN time_slots start_ts ON ts.start_time_slot_id = start_ts.time_slot_id
                JOIN time_slots end_ts ON ts.end_time_slot_id = end_ts.time_slot_id
                JOIN users u ON ts.user_id = u.user_id
                JOIN academic_years ay ON ts.academic_year_id = ay.academic_year_id
                WHERE ts.is_active = 1";

        $params = [];
        $types = '';

        // เงื่อนไขกรองอาจารย์
        if ($teacher_id && is_numeric($teacher_id)) {
            $sql .= " AND ts.user_id = ?";
            $params[] = (int)$teacher_id;
            $types .= 'i';
        } else {
            $sql .= " AND ts.user_id = ?";
            $params[] = $user_id;
            $types .= 'i';
        }

        // เงื่อนไขกรองปีการศึกษา
        if ($academic_year_id && is_numeric($academic_year_id)) {
            $sql .= " AND ts.academic_year_id = ?";
            $params[] = (int)$academic_year_id;
            $types .= 'i';
        }

        $sql .= " ORDER BY 
                    FIELD(ts.day_of_week, 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'),
                    start_ts.start_time";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $schedules = [];
        while ($row = $result->fetch_assoc()) {
            $schedules[] = $row;
        }

        $stmt->close();
        $conn->close();

        apiJsonSuccess('ดึงข้อมูลตารางสอนสำเร็จ', [
            'schedules' => $schedules,
            'total_count' => count($schedules),
            'academic_year_id' => $academic_year_id
        ]);

    } catch (Exception $e) {
        error_log('getTeachingSchedules Error: ' . $e->getMessage());
        apiJsonError('เกิดข้อผิดพลาดในการดึงข้อมูลตารางสอน: ' . $e->getMessage());
    }
}

/**
 * ดึงข้อมูลการชดเชย
 */
function getCompensationLogs() {
    global $user_id;
    
    try {
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        $sql = "SELECT 
                    cl.*,
                    ts.schedule_id,
                    s.subject_code,
                    s.subject_name,
                    yl.class_year,
                    c.room_number as original_room,
                    mc.room_number as makeup_room,
                    start_ts.start_time as original_start_time,
                    end_ts.end_time as original_end_time,
                    makeup_start_ts.start_time as makeup_start_time,
                    makeup_end_ts.end_time as makeup_end_time
                FROM compensation_logs cl
                LEFT JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
                LEFT JOIN subjects s ON ts.subject_id = s.subject_id
                LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
                LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
                LEFT JOIN classrooms mc ON cl.makeup_classroom_id = mc.classroom_id
                LEFT JOIN time_slots start_ts ON ts.start_time_slot_id = start_ts.time_slot_id
                LEFT JOIN time_slots end_ts ON ts.end_time_slot_id = end_ts.time_slot_id
                LEFT JOIN time_slots makeup_start_ts ON cl.makeup_start_time_slot_id = makeup_start_ts.time_slot_id
                LEFT JOIN time_slots makeup_end_ts ON cl.makeup_end_time_slot_id = makeup_end_ts.time_slot_id
                WHERE cl.user_id = ?
                ORDER BY cl.cancellation_date DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $compensations = [];
        while ($row = $result->fetch_assoc()) {
            $compensations[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        apiJsonSuccess('ดึงข้อมูลการชดเชยสำเร็จ', [
            'compensations' => $compensations,
            'total_count' => count($compensations)
        ]);
        
    } catch (Exception $e) {
        error_log('getCompensationLogs Error: ' . $e->getMessage());
        apiJsonError('เกิดข้อผิดพลาดในการดึงข้อมูลการชดเชย: ' . $e->getMessage());
    }
}

/**
 * ดึงข้อมูล Class Sessions
 */
function getClassSessions() {
    global $user_id;
    try {
        $academic_year_id = $_POST['academic_year_id'] ?? $_GET['academic_year_id'] ?? 0;
        $date_from = $_POST['date_from'] ?? $_GET['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? $_GET['date_to'] ?? '';
        $teacher_id = $_POST['teacher_id'] ?? $_GET['teacher_id'] ?? '';

        if (!$academic_year_id) {
            apiJsonError('ไม่พบข้อมูลปีการศึกษา');
        }

        $conn = connectMySQLi();

        // เงื่อนไข filter
        $where = "cs.session_date IS NOT NULL AND ts.academic_year_id = ?";
        $params = [$academic_year_id];
        $types = "i";

        if ($date_from && $date_to) {
            $where .= " AND cs.session_date BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
            $types .= "ss";
        }
        if ($teacher_id) {
            $where .= " AND ts.user_id = ?";
            $params[] = $teacher_id;
            $types .= "i";
        }

        $sql = "
            SELECT 
                cs.session_id,
                cs.schedule_id,
                cs.session_date,
                cs.actual_classroom_id,
                cs.actual_start_time_slot_id,
                cs.actual_end_time_slot_id,
                cs.notes,
                cs.user_id,
                ts.subject_id,
                s.subject_code,
                s.subject_name,
                ts.day_of_week,
                ts.is_module_subject,
                ts.group_id,
                ts.year_level_id,
                yl.class_year,
                yl.department,
                yl.curriculum,
                c.room_number AS original_room,
                c2.room_number AS actual_room,
                mg.group_name,
                m.module_name
            FROM class_sessions cs
            LEFT JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
            LEFT JOIN subjects s ON ts.subject_id = s.subject_id
            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
            LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
            LEFT JOIN classrooms c2 ON cs.actual_classroom_id = c2.classroom_id
            LEFT JOIN module_groups mg ON ts.group_id = mg.group_id
            LEFT JOIN modules m ON mg.module_id = m.module_id
            WHERE $where
            ORDER BY cs.session_date ASC, cs.session_id ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $sessions = [];
        while ($row = $result->fetch_assoc()) {
            // กำหนดข้อมูลชั้นปี/กลุ่มโมดูล
            if ($row['is_module_subject'] == 1 && $row['group_id']) {
                $row['class_year'] = null;
                $row['group_name'] = $row['group_name'] ?? '';
                $row['module_name'] = $row['module_name'] ?? '';
            }
            $sessions[] = $row;
        }
        $stmt->close();
        $conn->close();

        apiJsonSuccess('ดึงข้อมูล Class Sessions สำเร็จ', [
            'sessions' => $sessions,
            'total_count' => count($sessions)
        ]);
    } catch (Exception $e) {
        apiJsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ดึงข้อมูลสำหรับ Dashboard
 */
function getDashboardData() {
    global $user_id;
    
    try {
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        // สถิติพื้นฐาน
        $today = date('Y-m-d');
        $thai_days = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
        $today_thai = $thai_days[date('w')];
        
        // นับตารางสอนวันนี้
        $today_classes_sql = "SELECT COUNT(*) as count FROM teaching_schedules ts
                              JOIN academic_years ay ON ts.academic_year_id = ay.academic_year_id
                              WHERE ts.user_id = ? AND ts.is_active = 1 AND ay.is_current = 1
                              AND ts.day_of_week = ?";
        
        $stmt = $conn->prepare($today_classes_sql);
        if (!$stmt) {
            throw new Exception('Prepare failed for today classes: ' . $conn->error);
        }
        
        $stmt->bind_param('is', $user_id, $today_thai);
        $stmt->execute();
        $today_classes = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // นับวันหยุดเดือนนี้
        $this_month_holidays_sql = "SELECT COUNT(*) as count FROM public_holidays 
                                   WHERE YEAR(holiday_date) = YEAR(CURDATE()) 
                                   AND MONTH(holiday_date) = MONTH(CURDATE())
                                   AND is_active = 1";
        $stmt = $conn->prepare($this_month_holidays_sql);
        $stmt->execute();
        $this_month_holidays = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // นับการชดเชยที่รอดำเนินการ
        $pending_compensations_sql = "SELECT COUNT(*) as count FROM compensation_logs 
                                     WHERE user_id = ? AND status = 'รอดำเนินการ'";
        $stmt = $conn->prepare($pending_compensations_sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $pending_compensations = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // รายการตารางสอนวันนี้
        $today_schedule_sql = "SELECT 
                                ts.*,
                                s.subject_code,
                                s.subject_name,
                                c.room_number,
                                yl.class_year,
                                start_ts.start_time,
                                end_ts.end_time,
                                start_ts.slot_number as start_slot,
                                end_ts.slot_number as end_slot
                              FROM teaching_schedules ts
                              JOIN subjects s ON ts.subject_id = s.subject_id
                              JOIN classrooms c ON ts.classroom_id = c.classroom_id
                              JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
                              JOIN time_slots start_ts ON ts.start_time_slot_id = start_ts.time_slot_id
                              JOIN time_slots end_ts ON ts.end_time_slot_id = end_ts.time_slot_id
                              JOIN academic_years ay ON ts.academic_year_id = ay.academic_year_id
                              WHERE ts.user_id = ? AND ts.is_active = 1 AND ay.is_current = 1
                              AND ts.day_of_week = ?
                              ORDER BY start_ts.start_time";
        
        $stmt = $conn->prepare($today_schedule_sql);
        $stmt->bind_param('is', $user_id, $today_thai);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $today_schedule = [];
        while ($row = $result->fetch_assoc()) {
            $today_schedule[] = $row;
        }
        $stmt->close();
        
        $conn->close();
        
        apiJsonSuccess('ดึงข้อมูล Dashboard สำเร็จ', [
            'stats' => [
                'today_classes' => (int)$today_classes,
                'this_month_holidays' => (int)$this_month_holidays,
                'pending_compensations' => (int)$pending_compensations
            ],
            'today_schedule' => $today_schedule,
            'today_date' => $today,
            'today_thai_day' => $today_thai
        ]);
        
    } catch (Exception $e) {
        error_log('getDashboardData Error: ' . $e->getMessage());
        apiJsonError('เกิดข้อผิดพลาดในการดึงข้อมูล Dashboard: ' . $e->getMessage());
    }
}

/**
 * ดึงสถิติรวม
 */
function getStats() {
    global $user_id;
    
    try {
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        $stats = [];
        
        // นับตารางสอนทั้งหมด
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM teaching_schedules WHERE user_id = ? AND is_active = 1");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stats['total_schedules'] = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // นับวันหยุดปีนี้
        $current_thai_year = date('Y') + 543;
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM public_holidays WHERE academic_year = ? AND is_active = 1");
        $stmt->bind_param('i', $current_thai_year);
        $stmt->execute();
        $stats['total_holidays'] = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // นับการชดเชยทั้งหมด
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM compensation_logs WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stats['total_compensations'] = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // นับ Class Sessions เดือนนี้
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM class_sessions 
                               WHERE user_id = ? 
                               AND YEAR(session_date) = YEAR(CURDATE()) 
                               AND MONTH(session_date) = MONTH(CURDATE())");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stats['this_month_sessions'] = (int)$stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        $conn->close();
        
        apiJsonSuccess('ดึงสถิติสำเร็จ', $stats);
        
    } catch (Exception $e) {
        error_log('getStats Error: ' . $e->getMessage());
        apiJsonError('เกิดข้อผิดพลาดในการดึงสถิติ: ' . $e->getMessage());
    }
}

/**
 * ดึงสรุปข้อมูลวันหยุด
 */
function getHolidaySummary() {
    try {
        $conn = connectMySQLi();
        if (!$conn || $conn->connect_error) {
            throw new Exception('การเชื่อมต่อฐานข้อมูลล้มเหลว');
        }
        
        $academic_year = $_POST['academic_year'] ?? $_GET['academic_year'] ?? (date('Y') + 543);
        
        $sql = "SELECT 
                    holiday_type,
                    COUNT(*) as count,
                    GROUP_CONCAT(holiday_name SEPARATOR ', ') as holiday_names
                FROM public_holidays 
                WHERE academic_year = ? AND is_active = 1
                GROUP BY holiday_type
                ORDER BY count DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $summary = [];
        $total_holidays = 0;
        
        while ($row = $result->fetch_assoc()) {
            $summary[$row['holiday_type']] = [
                'count' => (int)$row['count'],
                'holidays' => explode(', ', $row['holiday_names'])
            ];
            $total_holidays += (int)$row['count'];
        }
        
        $stmt->close();
        $conn->close();
        
        apiJsonSuccess('ดึงสรุปวันหยุดสำเร็จ', [
            'summary' => $summary,
            'total_holidays' => $total_holidays,
            'academic_year' => (int)$academic_year
        ]);
        
    } catch (Exception $e) {
        error_log('getHolidaySummary Error: ' . $e->getMessage());
        apiJsonError('เกิดข้อผิดพลาดในการดึงสรุปวันหยุด: ' . $e->getMessage());
    }
}
?>