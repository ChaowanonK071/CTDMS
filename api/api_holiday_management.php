<?php
/**
 * API สำหรับจัดการวันหยุดด้วยตนเอง (เพิ่ม แก้ไข ลบ)
 * ไฟล์: /api/api_holiday_management.php
 * เวอร์ชัน: 1.0
 * วันที่: July 2025
 */

// ล้าง output buffer และตั้งค่า headers
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ปิด error display เพื่อไม่ให้รบกวน JSON output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ตั้งค่า error handler เพื่อจับ fatal errors
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

require_once 'config.php';

startSession();

// ฟังก์ชันช่วยเหลือสำหรับส่ง JSON response
if (!function_exists('jsonSuccess')) {
    function jsonSuccess($message, $data = null) {
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

if (!function_exists('jsonError')) {
    function jsonError($message, $code = 400, $data = null) {
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
if (!isLoggedIn()) {
    jsonError('ไม่ได้รับอนุญาต - กรุณาล็อกอินใหม่', 401);
}

$user_id = $_SESSION['user_id'];

// รับ action ที่ต้องการทำ
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    jsonError('ไม่ได้ระบุ action ที่ต้องการ');
}

// ล้าง buffer ก่อนประมวลผล
ob_clean();

try {
    switch ($action) {
        case 'add_holiday':
            addHoliday();
            break;
        case 'update_holiday':
            updateHoliday();
            break;
        case 'delete_holiday':
            deleteHoliday();
            break;
        case 'get_holiday_details':
            getHolidayDetails();
            break;
        case 'bulk_import':
            bulkImportHolidays();
            break;
        case 'validate_holiday':
            validateHoliday();
            break;
        default:
            jsonError('Action ไม่ถูกต้อง: ' . $action);
            break;
    }
} catch (Exception $e) {
    jsonError('Exception: ' . $e->getMessage(), 500, [
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 3)
    ]);
} catch (Error $e) {
    jsonError('Fatal Error: ' . $e->getMessage(), 500, [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

/**
 * เพิ่มวันหยุดใหม่
 */
function addHoliday() {
    global $user_id;
    
    // รับข้อมูลจากฟอร์ม
    $academic_year_id = $_POST['academic_year_id'] ?? 0;
    $holiday_date = $_POST['holiday_date'] ?? '';
    $holiday_name = trim($_POST['holiday_name'] ?? '');
    $holiday_name_en = trim($_POST['holiday_name_en'] ?? '');
    $holiday_type = $_POST['holiday_type'] ?? 'custom';
    $notes = trim($_POST['notes'] ?? '');
    
    // ตรวจสอบข้อมูลที่จำเป็น
    if (!$academic_year_id) {
        jsonError('ไม่พบข้อมูลปีการศึกษา');
    }
    
    if (empty($holiday_date)) {
        jsonError('กรุณาระบุวันที่หยุด');
    }
    
    if (empty($holiday_name)) {
        jsonError('กรุณาระบุชื่อวันหยุด');
    }
    
    if (empty($holiday_type)) {
        jsonError('กรุณาเลือกประเภทวันหยุด');
    }
    
    // ตรวจสอบรูปแบบวันที่
    if (!validateDate($holiday_date)) {
        jsonError('รูปแบบวันที่ไม่ถูกต้อง');
    }
    
    try {
        $conn = connectMySQLi();
        
        // ดึงข้อมูลปีการศึกษา
        $academic_query = "SELECT academic_year FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($academic_query);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $academic = $stmt->get_result()->fetch_assoc();
        
        if (!$academic) {
            throw new Exception('ไม่พบข้อมูลปีการศึกษา');
        }
        
        $academic_year = $academic['academic_year'];
        
        // ตรวจสอบว่าวันหยุดซ้ำหรือไม่
        $duplicate_query = "SELECT holiday_id, holiday_name FROM public_holidays 
                           WHERE academic_year = ? AND holiday_date = ?";
        $stmt = $conn->prepare($duplicate_query);
        $stmt->bind_param("is", $academic_year, $holiday_date);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            jsonError('มีวันหยุด "' . $existing['holiday_name'] . '" ในวันที่นี้แล้ว', 409);
        }
        
        // สร้างข้อมูล API response สำหรับวันหยุดที่เพิ่มเอง
        $api_data = [
            'source' => 'manual',
            'added_by' => $user_id,
            'thai_name' => $holiday_name,
            'english_name' => $holiday_name_en ?: $holiday_name,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
            'is_custom' => true
        ];
        $api_data_json = json_encode($api_data, JSON_UNESCAPED_UNICODE);
        
        $insert_query = "
            INSERT INTO public_holidays 
            (academic_year, holiday_date, holiday_name, holiday_type, is_active, 
             api_source, api_response_data, created_by, created_at) 
            VALUES (?, ?, ?, ?, 1, 'manual', ?, ?, NOW())
        ";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issssi", 
            $academic_year, 
            $holiday_date, 
            $holiday_name, 
            $holiday_type, 
            $api_data_json, 
            $user_id
        );
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถเพิ่มวันหยุดได้: ' . $stmt->error);
        }
        $holiday_id = $conn->insert_id;
        $find_sessions = $conn->prepare("SELECT session_id, schedule_id, user_id FROM class_sessions WHERE session_date = ?");
        $find_sessions->bind_param("s", $holiday_date);
        $find_sessions->execute();
        $result = $find_sessions->get_result();
        $sessions_to_cancel = [];
        while ($row = $result->fetch_assoc()) {
            $sessions_to_cancel[] = $row;
        }
        $find_sessions->close();

        $cancel_count = 0;
        foreach ($sessions_to_cancel as $session) {
            $reason = "วันหยุดที่เพิ่ม: {$holiday_name}";
            $insert_comp = $conn->prepare("INSERT INTO compensation_logs 
                (schedule_id, cancellation_date, cancellation_type, reason, is_makeup_required, status, user_id, created_at)
                VALUES (?, ?, 'วันหยุดราชการ', ?, 1, 'รอดำเนินการ', ?, NOW())");
            $insert_comp->bind_param("issi", $session['schedule_id'], $holiday_date, $reason, $session['user_id']);
            $insert_comp->execute();
            $insert_comp->close();
            $del = $conn->prepare("DELETE FROM class_sessions WHERE session_id = ?");
            $del->bind_param("i", $session['session_id']);
            $del->execute();
            $del->close();

            $cancel_count++;
        }

        $conn->close();

        error_log("✅ Added custom holiday: {$holiday_name} on {$holiday_date} by user {$user_id}");

        jsonSuccess('เพิ่มวันหยุดสำเร็จ' . ($cancel_count > 0 ? " และยกเลิก {$cancel_count} รายวิชาอัตโนมัติ" : ''), [
            'holiday_id' => $holiday_id,
            'holiday_name' => $holiday_name,
            'holiday_date' => $holiday_date,
            'holiday_type' => $holiday_type,
            'academic_year' => $academic_year,
            'auto_cancelled_sessions' => $cancel_count
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error adding holiday: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * แก้ไขวันหยุด
 */
function updateHoliday() {
    global $user_id;
    
    // รับข้อมูลจากฟอร์ม
    $holiday_id = $_POST['holiday_id'] ?? 0;
    $holiday_date = $_POST['holiday_date'] ?? '';
    $holiday_name = trim($_POST['holiday_name'] ?? '');
    $holiday_name_en = trim($_POST['holiday_name_en'] ?? '');
    $holiday_type = $_POST['holiday_type'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // ตรวจสอบข้อมูลที่จำเป็น
    if (!$holiday_id) {
        jsonError('ไม่พบรหัสวันหยุด');
    }
    
    if (empty($holiday_date)) {
        jsonError('กรุณาระบุวันที่หยุด');
    }
    
    if (empty($holiday_name)) {
        jsonError('กรุณาระบุชื่อวันหยุด');
    }
    
    if (empty($holiday_type)) {
        jsonError('กรุณาเลือกประเภทวันหยุด');
    }
    
    // ตรวจสอบรูปแบบวันที่
    if (!validateDate($holiday_date)) {
        jsonError('รูปแบบวันที่ไม่ถูกต้อง');
    }
    
    try {
        $conn = connectMySQLi();
        
        // ตรวจสอบว่าวันหยุดนี้มีอยู่จริง
        $check_query = "SELECT * FROM public_holidays WHERE holiday_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $holiday_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if (!$existing) {
            throw new Exception('ไม่พบวันหยุดที่ต้องการแก้ไข');
        }
        
        // ตรวจสอบว่าวันที่ใหม่ซ้ำกับวันหยุดอื่นหรือไม่ (ยกเว้นตัวเอง)
        $duplicate_query = "SELECT holiday_id, holiday_name FROM public_holidays 
                           WHERE academic_year = ? AND holiday_date = ? AND holiday_id != ?";
        $stmt = $conn->prepare($duplicate_query);
        $stmt->bind_param("isi", $existing['academic_year'], $holiday_date, $holiday_id);
        $stmt->execute();
        $duplicate = $stmt->get_result()->fetch_assoc();
        
        if ($duplicate) {
            jsonError('มีวันหยุด "' . $duplicate['holiday_name'] . '" ในวันที่นี้แล้ว', 409);
        }
        
        // อัปเดตข้อมูล API response
        $existing_api_data = [];
        if ($existing['api_response_data']) {
            $existing_api_data = json_decode($existing['api_response_data'], true) ?: [];
        }
        
        $api_data = array_merge($existing_api_data, [
            'thai_name' => $holiday_name,
            'english_name' => $holiday_name_en ?: $holiday_name,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $user_id,
            'last_modified' => true
        ]);
        $api_data_json = json_encode($api_data, JSON_UNESCAPED_UNICODE);
        
        // อัปเดตวันหยุด
        $update_query = "
            UPDATE public_holidays 
            SET holiday_date = ?, 
                holiday_name = ?, 
                holiday_type = ?, 
                api_source = 'manual',
                api_response_data = ?,
                updated_at = NOW()
            WHERE holiday_id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssssi", 
            $holiday_date, 
            $holiday_name, 
            $holiday_type, 
            $api_data_json, 
            $holiday_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถแก้ไขวันหยุดได้: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('ไม่มีการเปลี่ยนแปลงข้อมูล');
        }
        
        $conn->close();
        
        error_log("✅ Updated holiday ID {$holiday_id}: {$holiday_name} on {$holiday_date} by user {$user_id}");
        
        jsonSuccess('แก้ไขวันหยุดสำเร็จ', [
            'holiday_id' => $holiday_id,
            'holiday_name' => $holiday_name,
            'holiday_date' => $holiday_date,
            'holiday_type' => $holiday_type
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error updating holiday: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ลบวันหยุด (แก้ไขเพื่อรองรับการลบทั้งวันหยุดที่เพิ่มเองและจาก API)
 */
function deleteHoliday() {
    global $user_id;
    
    $holiday_id = $_POST['holiday_id'] ?? 0;
    
    if (!$holiday_id) {
        jsonError('ไม่พบรหัสวันหยุด');
    }
    
    try {
        $conn = connectMySQLi();
        
        // ตรวจสอบว่าวันหยุดนี้มีอยู่จริง
        $check_query = "SELECT holiday_name, holiday_date, academic_year, api_source FROM public_holidays WHERE holiday_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $holiday_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if (!$existing) {
            throw new Exception('ไม่พบวันหยุดที่ต้องการลบ');
        }
        
        // ตรวจสอบว่าวันหยุดนี้มีการใช้งานในระบบหรือไม่ (เช่น มีการชดเชยแล้ว)
        $usage_query = "
            SELECT COUNT(*) as usage_count 
            FROM compensation_logs 
            WHERE cancellation_date = ? AND cancellation_type = 'วันหยุดราชการ'
        ";
        $stmt = $conn->prepare($usage_query);
        $stmt->bind_param("s", $existing['holiday_date']);
        $stmt->execute();
        $usage = $stmt->get_result()->fetch_assoc();
        
        if ($usage['usage_count'] > 0) {
            throw new Exception('ไม่สามารถลบวันหยุดนี้ได้ เนื่องจากมีการใช้งานในระบบแล้ว (มีการชดเชยแล้ว)');
        }
        
        // ตรวจสอบสิทธิ์การลบ (สำหรับวันหยุดจาก API อาจต้องเป็น admin)
        if (!empty($existing['api_source']) && $existing['api_source'] !== 'manual') {
            // สำหรับวันหยุดจาก API ให้ตรวจสอบสิทธิ์ admin
            $user_query = "SELECT user_type FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($user_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_data = $stmt->get_result()->fetch_assoc();
            
            if (!$user_data || $user_data['user_type'] !== 'admin') {
                throw new Exception('ต้องมีสิทธิ์ผู้ดูแลระบบเท่านั้นที่สามารถลบวันหยุดจาก API ได้');
            }
        }
        
        // ลบวันหยุด
        $delete_query = "DELETE FROM public_holidays WHERE holiday_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $holiday_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถลบวันหยุดได้: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('ไม่พบวันหยุดที่ต้องการลบ');
        }
        
        $conn->close();
        
        $holiday_source = (!empty($existing['api_source']) && $existing['api_source'] !== 'manual') ? 'API' : 'Manual';
        error_log("✅ Deleted holiday ID {$holiday_id}: {$existing['holiday_name']} (Source: {$holiday_source}) by user {$user_id}");
        
        jsonSuccess('ลบวันหยุดสำเร็จ', [
            'holiday_id' => $holiday_id,
            'holiday_name' => $existing['holiday_name'],
            'holiday_date' => $existing['holiday_date'],
            'source' => $holiday_source
        ]);
        
    } catch (Exception $e) {
        error_log("❌ Error deleting holiday: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}
/**
 * ดึงรายละเอียดวันหยุด
 */
function getHolidayDetails() {
    $holiday_id = $_POST['holiday_id'] ?? $_GET['holiday_id'] ?? 0;
    
    if (!$holiday_id) {
        jsonError('ไม่พบรหัสวันหยุด');
    }
    
    try {
        $conn = connectMySQLi();
        
        $query = "
            SELECT h.*, 
                   u.name as created_by_name,
                   u.lastname as created_by_lastname,
                   ay.academic_year, ay.semester
            FROM public_holidays h
            LEFT JOIN users u ON h.created_by = u.user_id
            LEFT JOIN academic_years ay ON h.academic_year = ay.academic_year
            WHERE h.holiday_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $holiday_id);
        $stmt->execute();
        $holiday = $stmt->get_result()->fetch_assoc();
        
        if (!$holiday) {
            throw new Exception('ไม่พบวันหยุดที่ต้องการ');
        }
        
        // แปลงข้อมูล API response
        if ($holiday['api_response_data']) {
            $api_data = json_decode($holiday['api_response_data'], true);
            $holiday['api_data'] = $api_data;
            $holiday['notes'] = $api_data['notes'] ?? '';
            $holiday['english_name'] = $api_data['english_name'] ?? '';
            $holiday['is_custom'] = $api_data['is_custom'] ?? false;
        } else {
            $holiday['notes'] = '';
            $holiday['english_name'] = '';
            $holiday['is_custom'] = false;
        }
        
        // จัดรูปแบบวันที่
        $holiday['formatted_date'] = formatThaiDatePHP($holiday['holiday_date']);
        $holiday['day_of_week'] = getDayOfWeekThai($holiday['holiday_date']);
        
        $conn->close();
        
        jsonSuccess('ดึงรายละเอียดวันหยุดสำเร็จ', $holiday);
        
    } catch (Exception $e) {
        error_log("❌ Error getting holiday details: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * นำเข้าวันหยุดแบบจำนวนมาก
 */
function bulkImportHolidays() {
    global $user_id;
    
    $academic_year_id = $_POST['academic_year_id'] ?? 0;
    $holidays_data = $_POST['holidays_data'] ?? '';
    
    if (!$academic_year_id) {
        jsonError('ไม่พบข้อมูลปีการศึกษา');
    }
    
    if (empty($holidays_data)) {
        jsonError('ไม่พบข้อมูลวันหยุดที่จะนำเข้า');
    }
    
    // แปลงข้อมูล JSON
    $holidays = json_decode($holidays_data, true);
    if (!$holidays || !is_array($holidays)) {
        jsonError('รูปแบบข้อมูลวันหยุดไม่ถูกต้อง');
    }
    
    try {
        $conn = connectMySQLi();
        
        // ดึงข้อมูลปีการศึกษา
        $academic_query = "SELECT academic_year FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($academic_query);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $academic = $stmt->get_result()->fetch_assoc();
        
        if (!$academic) {
            throw new Exception('ไม่พบข้อมูลปีการศึกษา');
        }
        
        $academic_year = $academic['academic_year'];
        
        // เริ่ม transaction
        $conn->begin_transaction();
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($holidays as $index => $holiday) {
            try {
                $holiday_date = $holiday['date'] ?? '';
                $holiday_name = trim($holiday['name'] ?? '');
                $holiday_name_en = trim($holiday['name_en'] ?? '');
                $holiday_type = $holiday['type'] ?? 'custom';
                $notes = trim($holiday['notes'] ?? '');
                
                // ตรวจสอบข้อมูลพื้นฐาน
                if (empty($holiday_date) || empty($holiday_name)) {
                    throw new Exception("ข้อมูลไม่ครบถ้วน");
                }
                
                // ตรวจสอบรูปแบบวันที่
                if (!validateDate($holiday_date)) {
                    throw new Exception("รูปแบบวันที่ไม่ถูกต้อง");
                }
                
                // ตรวจสอบวันหยุดซ้ำ
                $duplicate_query = "SELECT holiday_id FROM public_holidays 
                                   WHERE academic_year = ? AND holiday_date = ?";
                $stmt = $conn->prepare($duplicate_query);
                $stmt->bind_param("is", $academic_year, $holiday_date);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                
                if ($existing) {
                    throw new Exception("วันหยุดซ้ำ");
                }
                
                // สร้างข้อมูล API response
                $api_data = [
                    'source' => 'bulk_import',
                    'imported_by' => $user_id,
                    'thai_name' => $holiday_name,
                    'english_name' => $holiday_name_en ?: $holiday_name,
                    'notes' => $notes,
                    'import_date' => date('Y-m-d H:i:s'),
                    'is_custom' => true,
                    'batch_index' => $index
                ];
                $api_data_json = json_encode($api_data, JSON_UNESCAPED_UNICODE);
                
                // เพิ่มวันหยุด
                $insert_query = "
                    INSERT INTO public_holidays 
                    (academic_year, holiday_date, holiday_name, holiday_type, 
                     is_active, api_source, api_response_data, created_by, created_at) 
                    VALUES (?, ?, ?, ?, 1, 'bulk_import', ?, ?, NOW())
                ";
                
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("issssi", 
                    $academic_year, 
                    $holiday_date, 
                    $holiday_name, 
                    $holiday_type, 
                    $api_data_json, 
                    $user_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("ไม่สามารถเพิ่มวันหยุดได้");
                }
                
                $success_count++;
                
            } catch (Exception $e) {
                $error_count++;
                $errors[] = [
                    'index' => $index,
                    'data' => $holiday,
                    'error' => $e->getMessage()
                ];
                
                error_log("❌ Bulk import error at index {$index}: " . $e->getMessage());
            }
        }
        
        // Commit transaction
        $conn->commit();
        $conn->close();
        
        $message = "นำเข้าวันหยุดเสร็จสิ้น - สำเร็จ: {$success_count} วัน";
        if ($error_count > 0) {
            $message .= ", ข้อผิดพลาด: {$error_count} วัน";
        }
        
        error_log("✅ Bulk import completed: {$success_count} success, {$error_count} errors");
        
        jsonSuccess($message, [
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors,
            'academic_year' => $academic_year
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log("❌ Bulk import failed: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ตรวจสอบความถูกต้องของวันหยุด
 */
function validateHoliday() {
    $academic_year_id = $_POST['academic_year_id'] ?? 0;
    $holiday_date = $_POST['holiday_date'] ?? '';
    $holiday_id = $_POST['holiday_id'] ?? 0; // สำหรับกรณีแก้ไข

    if (!$academic_year_id) {
        jsonError('ไม่พบข้อมูลปีการศึกษา');
    }

    if (empty($holiday_date)) {
        jsonError('กรุณาระบุวันที่หยุด');
    }

    // ตรวจสอบรูปแบบวันที่
    if (!validateDate($holiday_date)) {
        jsonError('รูปแบบวันที่ไม่ถูกต้อง');
    }

    try {
        $conn = connectMySQLi();

        // ดึงข้อมูลปีการศึกษา
        $academic_query = "SELECT academic_year, start_date, end_date FROM academic_years WHERE academic_year_id = ?";
        $stmt = $conn->prepare($academic_query);
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $academic = $stmt->get_result()->fetch_assoc();

        if (!$academic) {
            throw new Exception('ไม่พบข้อมูลปีการศึกษา');
        }

        $academic_year = $academic['academic_year'];
        $start_date = $academic['start_date'];
        $end_date = $academic['end_date'];

        $validation_result = [
            'is_valid' => true,
            'warnings' => [],
            'errors' => [],
            'suggestions' => []
        ];

        // ตรวจสอบวันหยุดซ้ำ
        $duplicate_query = "SELECT holiday_id, holiday_name FROM public_holidays 
                           WHERE academic_year = ? AND holiday_date = ?";
        if ($holiday_id) {
            $duplicate_query .= " AND holiday_id != ?";
        }

        $stmt = $conn->prepare($duplicate_query);
        if ($holiday_id) {
            $stmt->bind_param("isi", $academic_year, $holiday_date, $holiday_id);
        } else {
            $stmt->bind_param("is", $academic_year, $holiday_date);
        }
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $validation_result['is_valid'] = false;
            $validation_result['errors'][] = 'มีวันหยุด "' . $existing['holiday_name'] . '" ในวันที่นี้แล้ว';
        }

        // ตรวจสอบว่าวันที่อยู่ในช่วงปีการศึกษาหรือไม่
        if ($start_date && $end_date) {
            $holiday_timestamp = strtotime($holiday_date);
            $start_timestamp = strtotime($start_date);
            $end_timestamp = strtotime($end_date);

            if ($holiday_timestamp < $start_timestamp || $holiday_timestamp > $end_timestamp) {
                $validation_result['warnings'][] = 'วันหยุดอยู่นอกช่วงปีการศึกษา (' . 
                    formatThaiDatePHP($start_date) . ' - ' . formatThaiDatePHP($end_date) . ')';
            }
        }

        // ตรวจสอบว่าเป็นวันหยุดในอดีตหรือไม่
        if (strtotime($holiday_date) < strtotime(date('Y-m-d'))) {
            $validation_result['warnings'][] = 'วันหยุดอยู่ในอดีต';
        }

        // ตรวจสอบว่าเป็นวันเสาร์-อาทิตย์หรือไม่
        $day_of_week = date('w', strtotime($holiday_date));
        if ($day_of_week == 0 || $day_of_week == 6) {
            $validation_result['suggestions'][] = 'วันนี้เป็นวันหยุดสุดสัปดาห์อยู่แล้ว';
        }

        // ตรวจสอบว่ามีตารางเรียนในวันนี้หรือไม่ (รองรับโมดูล)
        $thai_day = getDayOfWeekThai($holiday_date);

        // ตารางเรียนแบบปกติ
        $schedule_query = "
            SELECT COUNT(*) as class_count
            FROM teaching_schedules ts
            JOIN academic_years ay ON ts.academic_year_id = ay.academic_year_id
            WHERE ay.academic_year = ?
            AND ts.day_of_week = ?
            AND ts.is_active = 1
            AND ts.is_module_subject = 0
        ";
        $stmt = $conn->prepare($schedule_query);
        $stmt->bind_param("is", $academic_year, $thai_day);
        $stmt->execute();
        $schedule_result = $stmt->get_result()->fetch_assoc();
        if ($schedule_result['class_count'] > 0) {
            $validation_result['suggestions'][] = 'มีตารางเรียนปกติ ' . $schedule_result['class_count'] . ' คลาสในวันนี้';
        }

        // ตารางเรียนแบบโมดูล
        $module_query = "
            SELECT COUNT(*) as module_class_count
            FROM teaching_schedules ts
            JOIN academic_years ay ON ts.academic_year_id = ay.academic_year_id
            WHERE ay.academic_year = ?
            AND ts.day_of_week = ?
            AND ts.is_active = 1
            AND ts.is_module_subject = 1
        ";
        $stmt = $conn->prepare($module_query);
        $stmt->bind_param("is", $academic_year, $thai_day);
        $stmt->execute();
        $module_result = $stmt->get_result()->fetch_assoc();
        if ($module_result['module_class_count'] > 0) {
            $validation_result['suggestions'][] = 'มีตารางเรียนแบบโมดูล ' . $module_result['module_class_count'] . ' คลาสในวันนี้';
        }

        $conn->close();

        jsonSuccess('ตรวจสอบความถูกต้องเสร็จสิ้น', $validation_result);

    } catch (Exception $e) {
        error_log("❌ Error validating holiday: " . $e->getMessage());
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// ===== ฟังก์ชันช่วยเหลือ =====

/**
 * ตรวจสอบรูปแบบวันที่
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * เชื่อมต่อฐานข้อมูล MySQLi
 */
function connectMySQLi() {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        return $conn;
    }
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * จัดรูปแบบวันที่เป็นภาษาไทย
 */
function formatThaiDatePHP($date_str) {
    if (!$date_str) return '';
    
    $months_th = [
        1 => "ม.ค.", 2 => "ก.พ.", 3 => "มี.ค.", 4 => "เม.ย.", 
        5 => "พ.ค.", 6 => "มิ.ย.", 7 => "ก.ค.", 8 => "ส.ค.",
        9 => "ก.ย.", 10 => "ต.ค.", 11 => "พ.ย.", 12 => "ธ.ค."
    ];

    $timestamp = strtotime($date_str);
    $day = date("j", $timestamp);
    $month = (int)date("n", $timestamp);
    $year = (int)date("Y", $timestamp) + 543;

    return "{$day} {$months_th[$month]} {$year}";
}

/**
 * แปลงวันที่เป็นวันในสัปดาห์ภาษาไทย
 */
function getDayOfWeekThai($date_str) {
    if (!$date_str) return '';
    
    $day_number = date('w', strtotime($date_str));
    $days = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
    
    return $days[$day_number];
}

?>