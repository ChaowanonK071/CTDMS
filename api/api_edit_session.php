<?php
/**
 * API สำหรับแก้ไขและยกเลิก Class Session
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
        ob_clean();
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
        ob_clean();
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
        ob_clean();
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
    jsonError('ไม่ได้รับอนุญาต', 401);
}

$user_id = $_SESSION['user_id'];

// รับ action ที่ต้องการทำ
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ล้าง buffer ก่อนประมวลผล
ob_clean();

try {
    switch ($action) {
        case 'get_session':
            getSession();
            break;
        case 'update_session':
            updateSession();
            break;
        case 'cancel_session':
            cancelSession();
            break;
        case 'request_cancellation':
            requestCancellation();
            break;
        case 'get_classrooms':
            getClassrooms();
            break;
        case 'get_time_slots':
            getTimeSlots();
            break;
        case 'update_google_event_id':
            updateGoogleEventId();
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
function updateGoogleEventId() {
    global $user_id;
    
    $session_id = $_POST['session_id'] ?? 0;
    $google_event_id = $_POST['google_event_id'] ?? '';
    
    if (!$session_id || !$google_event_id) {
        jsonError('ข้อมูลไม่ครบถ้วน: ต้องการ session_id และ google_event_id');
    }
    
    try {
        $conn = connectMySQLi();
        
        // ตรวจสอบว่า session นี้เป็นของ user นี้
        $check_query = "
            SELECT cs.session_id 
            FROM class_sessions cs
            JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
            WHERE cs.session_id = ? AND ts.user_id = ?
        ";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $session_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            throw new Exception('ไม่พบข้อมูล Class Session หรือไม่มีสิทธิ์เข้าถึง');
        }
        
        // อัปเดต Google Event ID
        $update_query = "
            UPDATE class_sessions 
            SET google_event_id = ?, updated_at = NOW() 
            WHERE session_id = ?
        ";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $google_event_id, $session_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถอัปเดท Google Event ID ได้');
        }
        
        $stmt->close();
        $conn->close();
        
        jsonSuccess('อัปเดท Google Event ID สำเร็จ', [
            'session_id' => $session_id,
            'google_event_id' => $google_event_id,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ลบ Google Calendar Event เมื่อยกเลิก Class Session
 */
function deleteGoogleCalendarEvent($session_id, $google_event_id) {
    global $user_id;
    
    try {
        if (!function_exists('deleteGoogleCalendarEvent')) {
            error_log("Google Calendar Integration not available for deletion");
            return false;
        }
        
        error_log("Attempting to delete Google Calendar event: {$google_event_id}");
        
        // เรียกใช้ฟังก์ชันลบจาก Google Calendar Integration
        $result = deleteGoogleCalendarEvent($user_id, $google_event_id);
        
        if ($result['success']) {
            error_log("Successfully deleted Google Calendar event: {$google_event_id}");
            return true;
        } else {
            error_log("Failed to delete Google Calendar event: " . ($result['error'] ?? 'Unknown error'));
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Exception deleting Google Calendar event: " . $e->getMessage());
        return false;
    }
}

/**
 * ดึงข้อมูล Class Session
 */
function getSession() {
    global $user_id;
    
    $session_id = $_POST['session_id'] ?? $_GET['session_id'] ?? 0;
    
    if (!$session_id) {
        jsonError('ไม่พบ Session ID');
    }
    
    try {
        $conn = connectMySQLi();
        
        $query = "
            SELECT 
                cs.*,
                ts.schedule_id,
                ts.day_of_week,
                ts.user_id as teacher_id,
                s.subject_code, 
                s.subject_name,
                s.credits,
                COALESCE(u.title, '') as title, 
                COALESCE(u.name, '') as name, 
                COALESCE(u.lastname, '') as lastname,
                COALESCE(yl.class_year, '') as class_year,
                COALESCE(c.room_number, '') as room_number,
                COALESCE(c.building, '') as building,
                COALESCE(start_slot.start_time, '00:00:00') as start_time,
                COALESCE(end_slot.end_time, '00:00:00') as end_time,
                start_slot.slot_number as start_slot_number,
                end_slot.slot_number as end_slot_number
            FROM class_sessions cs
            JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
            LEFT JOIN subjects s ON ts.subject_id = s.subject_id
            LEFT JOIN users u ON ts.user_id = u.user_id
            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
            LEFT JOIN classrooms c ON cs.actual_classroom_id = c.classroom_id
            LEFT JOIN time_slots start_slot ON cs.actual_start_time_slot_id = start_slot.time_slot_id
            LEFT JOIN time_slots end_slot ON cs.actual_end_time_slot_id = end_slot.time_slot_id
            WHERE cs.session_id = ? AND ts.user_id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $session_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $session = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if (!$session) {
            jsonError('ไม่พบข้อมูล Class Session หรือไม่มีสิทธิ์เข้าถึง', 404);
        }
        
        // จัดรูปแบบข้อมูล
        $session['teacher_fullname'] = trim($session['title'] . $session['name'] . ' ' . $session['lastname']);
        $session['time_range'] = '';
        if ($session['start_time'] && $session['end_time']) {
            $session['time_range'] = substr($session['start_time'], 0, 5) . '-' . substr($session['end_time'], 0, 5);
        }
        $session['formatted_session_date'] = formatThaiDatePHP($session['session_date']);
        
        jsonSuccess('ดึงข้อมูลสำเร็จ', $session);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->close();
        }
        jsonError('เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage(), 500);
    }
}

/**
 * อัพเดท Class Session
 */
function updateSession() {
    global $user_id;
    
    $session_id = $_POST['session_id'] ?? 0;
    
    if (!$session_id) {
        jsonError('ไม่พบ Session ID');
    }
    
    // ตรวจสอบข้อมูลที่จำเป็น
    $required_fields = ['session_date', 'actual_classroom_id', 'actual_start_time_slot_id', 'actual_end_time_slot_id'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        jsonError('ข้อมูลไม่ครบถ้วน: ' . implode(', ', $missing_fields));
    }
    
    try {
        $conn = connectMySQLi();
        $conn->begin_transaction();
        
        // ตรวจสอบว่า Session มีอยู่จริง และผู้ใช้มีสิทธิ์
        $check_query = "
            SELECT cs.*, ts.subject_id, ts.user_id as teacher_id, s.subject_code, s.subject_name,
                   c.room_number, start_slot.start_time, end_slot.end_time
            FROM class_sessions cs
            JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            LEFT JOIN classrooms c ON cs.actual_classroom_id = c.classroom_id
            LEFT JOIN time_slots start_slot ON cs.actual_start_time_slot_id = start_slot.time_slot_id
            LEFT JOIN time_slots end_slot ON cs.actual_end_time_slot_id = end_slot.time_slot_id
            WHERE cs.session_id = ? AND ts.user_id = ?
        ";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $session_id, $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$existing) {
            throw new Exception('ไม่พบข้อมูล Class Session หรือไม่มีสิทธิ์เข้าถึง');
        }
        
        // ตรวจสอบการขัดแย้งของตารางเรียน
        $conflict_query = "
            SELECT COUNT(*) as count 
            FROM class_sessions cs
            JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
            WHERE cs.session_id != ? 
            AND cs.session_date = ?
            AND cs.actual_classroom_id = ?
            AND (
                (cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?) OR
                (cs.actual_start_time_slot_id <= ? AND cs.actual_end_time_slot_id >= ?) OR
                (cs.actual_start_time_slot_id >= ? AND cs.actual_end_time_slot_id <= ?)
            )
        ";
        
        $stmt = $conn->prepare($conflict_query);
        $stmt->bind_param("isiiiiii", 
            $session_id,
            $_POST['session_date'],
            $_POST['actual_classroom_id'],
            $_POST['actual_start_time_slot_id'], $_POST['actual_start_time_slot_id'],
            $_POST['actual_end_time_slot_id'], $_POST['actual_end_time_slot_id'],
            $_POST['actual_start_time_slot_id'], $_POST['actual_end_time_slot_id']
        );
        $stmt->execute();
        $conflict_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($conflict_result['count'] > 0) {
            throw new Exception('มีการขัดแย้งของตารางเรียน กรุณาเลือกเวลาหรือห้องอื่น');
        }
        
        // อัพเดทข้อมูล
        $update_query = "
            UPDATE class_sessions 
            SET session_date = ?,
                actual_classroom_id = ?,
                actual_start_time_slot_id = ?,
                actual_end_time_slot_id = ?,
                attendance_count = ?,
                notes = ?,
                updated_at = NOW()
            WHERE session_id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        $attendance_count = !empty($_POST['attendance_count']) ? (int)$_POST['attendance_count'] : null;
        $notes = $_POST['notes'] ?? '';
        
        $stmt->bind_param("siiiisi", 
            $_POST['session_date'],
            $_POST['actual_classroom_id'],
            $_POST['actual_start_time_slot_id'],
            $_POST['actual_end_time_slot_id'],
            $attendance_count,
            $notes,
            $session_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถอัพเดทข้อมูลได้');
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('ไม่มีการเปลี่ยนแปลงข้อมูล');
        }
        
        $stmt->close();
        
        $conn->commit();
        $conn->close();
        
        // ตรวจสอบว่ามีการเปลี่ยนแปลงที่ต้องอัปเดต Google Calendar หรือไม่
        $google_update_needed = false;
        $google_event_id = $existing['google_event_id'] ?? null;
        
        if (!empty($google_event_id)) {
            // ตรวจสอบการเปลี่ยนแปลงสำคัญ
            if ($existing['session_date'] !== $_POST['session_date'] ||
                $existing['actual_classroom_id'] != $_POST['actual_classroom_id'] ||
                $existing['actual_start_time_slot_id'] != $_POST['actual_start_time_slot_id'] ||
                $existing['actual_end_time_slot_id'] != $_POST['actual_end_time_slot_id']) {
                
                $google_update_needed = true;
            }
        }
        
        // แจ้งเตือนสำหรับ Google Calendar Integration
        if ($google_update_needed) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['pending_google_calendar_update'] = [
                'type' => 'class_session_update',
                'session_id' => $session_id,
                'google_event_id' => $google_event_id,
                'changes_needed' => true,
                'updated_at' => time()
            ];
        }
        
        $message = 'บันทึกการแก้ไขเรียบร้อยแล้ว';
        
        jsonSuccess($message, [
            'session_id' => $session_id,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
            $conn->close();
        }
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ฟังก์ชันช่วยเหลือสำหรับการจัดการ Google Calendar Event
 */
function getGoogleCalendarEventData($session_data) {
    return [
        'title' => "{$session_data['subject_code']} - {$session_data['subject_name']}",
        'description' => "การเรียนการสอน\nรายวิชา: {$session_data['subject_name']}\nอัปเดตเมื่อ: " . date('Y-m-d H:i:s'),
        'location' => "ห้อง {$session_data['room_number']}",
        'start_datetime' => "{$session_data['session_date']}T{$session_data['start_time']}",
        'end_datetime' => "{$session_data['session_date']}T{$session_data['end_time']}",
        'session_id' => $session_data['session_id'],
        'event_type' => 'class_session'
    ];
}
/**
 * ยกเลิก Class Session และสร้าง Compensation Log
 */
function cancelSession() {
    global $user_id;
    
    $session_id = $_POST['session_id'] ?? 0;
    $schedule_id = $_POST['schedule_id'] ?? 0;
    $cancellation_date = $_POST['cancellation_date'] ?? '';
    $cancellation_type = $_POST['cancellation_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $is_makeup_required = isset($_POST['is_makeup_required']) ? 1 : 0;
    
    if (!$session_id || !$schedule_id || !$cancellation_date || !$cancellation_type || !$reason) {
        jsonError('ข้อมูลไม่ครบถ้วน');
    }
    
    try {
        $conn = connectMySQLi();
        $conn->begin_transaction();
        
        // ตรวจสอบว่า Session มีอยู่จริง และดึง Google Event ID
        $check_session_query = "
            SELECT cs.*, ts.user_id as teacher_id, s.subject_code, s.subject_name
            FROM class_sessions cs
            JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            WHERE cs.session_id = ? AND cs.schedule_id = ?
        ";
        $stmt = $conn->prepare($check_session_query);
        $stmt->bind_param("ii", $session_id, $schedule_id);
        $stmt->execute();
        $session_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$session_data) {
            throw new Exception('ไม่พบข้อมูล Class Session');
        }
        
        $google_event_id = $session_data['google_event_id'] ?? null;
        
        // ตรวจสอบว่ามี Compensation Log แล้วหรือไม่
        $check_compensation_query = "
            SELECT cancellation_id 
            FROM compensation_logs 
            WHERE schedule_id = ? AND cancellation_date = ?
        ";
        $stmt = $conn->prepare($check_compensation_query);
        $stmt->bind_param("is", $schedule_id, $cancellation_date);
        $stmt->execute();
        $existing_compensation = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing_compensation) {
            throw new Exception('มีการบันทึกการยกเลิกสำหรับวันนี้แล้ว');
        }
        
        // สร้าง Compensation Log
        $insert_compensation_query = "
            INSERT INTO compensation_logs 
            (schedule_id, cancellation_date, cancellation_type, reason, is_makeup_required, status, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, 'รอดำเนินการ', ?, NOW())
        ";
        
        $stmt = $conn->prepare($insert_compensation_query);
        $stmt->bind_param("isssii", 
            $schedule_id,
            $cancellation_date,
            $cancellation_type,
            $reason,
            $is_makeup_required,
            $user_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถสร้าง Compensation Log ได้');
        }
        
        $compensation_id = $conn->insert_id;
        $stmt->close();
        
        // ลบ Class Session (เนื่องจากยกเลิกแล้ว)
        $delete_session_query = "DELETE FROM class_sessions WHERE session_id = ?";
        $stmt = $conn->prepare($delete_session_query);
        $stmt->bind_param("i", $session_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถลบ Class Session ได้');
        }
        $stmt->close();
        
        $conn->commit();
        $conn->close();
        
        // ลบ Google Calendar Event ถ้ามี (ไม่ใช่ critical operation)
        $google_deletion_success = false;
        if (!empty($google_event_id)) {
            try {
                $google_deletion_success = deleteGoogleCalendarEvent($session_id, $google_event_id);
            } catch (Exception $google_error) {
                error_log("Google Calendar event deletion failed (non-critical): " . $google_error->getMessage());
            }
        }
        
        $message = "ยกเลิกรายวิชาเรียบร้อยแล้ว\n\n";
        $message .= "รายวิชา: {$session_data['subject_code']} - {$session_data['subject_name']}\n";
        $message .= "วันที่ยกเลิก: " . formatThaiDatePHP($cancellation_date) . "\n";
        $message .= "เหตุผล: {$reason}\n";
        
        if (!empty($google_event_id)) {
            if ($google_deletion_success) {
                $message .= "\nลบ Google Calendar Event สำเร็จ";
            } else {
                $message .= "\nไม่สามารถลบ Google Calendar Event ได้ (กรุณาลบเองใน Google Calendar)";
            }
        }
        
        jsonSuccess($message, [
            'compensation_id' => $compensation_id,
            'session_deleted' => true,
            'is_makeup_required' => $is_makeup_required,
            'google_event_deleted' => $google_deletion_success,
            'google_event_id' => $google_event_id
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
            $conn->close();
        }
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ขอยกเลิกการเรียนการสอน
 */
function requestCancellation() {
    global $user_id;
    $session_id = $_POST['session_id'] ?? 0;
    $cancellation_date = $_POST['cancellation_date'] ?? '';
    $cancellation_type = $_POST['cancellation_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $is_makeup_required = isset($_POST['is_makeup_required']) ? 1 : 0;

    if (!$session_id || !$cancellation_date || !$cancellation_type || !$reason) {
        jsonError('ข้อมูลไม่ครบถ้วน: ต้องการ session_id, cancellation_date, cancellation_type และ reason');
    }

    try {
        $conn = connectMySQLi();
        $conn->begin_transaction();

        // ดึงข้อมูล session โดยไม่กรอง user_id
        $session_query = "
            SELECT cs.*, ts.schedule_id, ts.user_id as teacher_id, 
                   s.subject_code, s.subject_name, s.credits,
                   yl.class_year,
                   c.room_number, c.building,
                   start_slot.start_time, end_slot.end_time,
                   start_slot.slot_number as start_slot_number,
                   end_slot.slot_number as end_slot_number
            FROM class_sessions cs
            JOIN teaching_schedules ts ON cs.schedule_id = ts.schedule_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
            LEFT JOIN classrooms c ON cs.actual_classroom_id = c.classroom_id
            LEFT JOIN time_slots start_slot ON cs.actual_start_time_slot_id = start_slot.time_slot_id
            LEFT JOIN time_slots end_slot ON cs.actual_end_time_slot_id = end_slot.time_slot_id
            WHERE cs.session_id = ?
        ";
        
        $stmt = $conn->prepare($session_query);
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $session_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$session_data) {
            throw new Exception('ไม่พบข้อมูล Class Session หรือไม่มีสิทธิ์เข้าถึง');
        }
        
        $schedule_id = $session_data['schedule_id'];
        $google_event_id = $session_data['google_event_id'] ?? null;
        
        // ตรวจสอบว่ามีการขอยกเลิกสำหรับวันนี้แล้วหรือไม่
        $check_existing_query = "
            SELECT cancellation_id, status 
            FROM compensation_logs 
            WHERE schedule_id = ? AND cancellation_date = ? AND user_id = ?
        ";
        $stmt = $conn->prepare($check_existing_query);
        $stmt->bind_param("isi", $schedule_id, $cancellation_date, $user_id);
        $stmt->execute();
        $existing_request = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing_request) {
            $status_text = $existing_request['status'];
            throw new Exception("มีการขอยกเลิกสำหรับวันนี้แล้ว (สถานะ: {$status_text})");
        }
        
        // สร้าง detailed reason
        $detailed_reason = "{$cancellation_type}\n: {$reason}";
        $status = 'รอดำเนินการ';
        
        $insert_compensation_query = "
            INSERT INTO compensation_logs 
            (schedule_id, cancellation_date, cancellation_type, reason, is_makeup_required, 
             status, user_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $stmt = $conn->prepare($insert_compensation_query);
        $stmt->bind_param("isssisi", 
            $schedule_id,
            $cancellation_date,
            $cancellation_type,
            $detailed_reason,
            $is_makeup_required,
            $status,
            $user_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถสร้าง Compensation Log ได้');
        }
        
        $compensation_id = $conn->insert_id;
        $stmt->close();
        
        // บันทึก status history - แก้ไขตรงนี้ด้วย
        $insert_history_query = "
            INSERT INTO compensation_status_history 
            (cancellation_id, old_status, new_status, action_by, action_reason, created_at)
            VALUES (?, NULL, ?, ?, 'การขอยกเลิกการเรียนการสอน', NOW())
        ";
        
        $stmt = $conn->prepare($insert_history_query);
        $stmt->bind_param("isi", $compensation_id, $status, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // ลบ Class Session ทันที (เพราะยกเลิกแล้ว)
        $delete_session_query = "DELETE FROM class_sessions WHERE session_id = ?";
        $stmt = $conn->prepare($delete_session_query);
        $stmt->bind_param("i", $session_id);
        
        if (!$stmt->execute()) {
            throw new Exception('ไม่สามารถลบ Class Session ได้');
        }
        $stmt->close();
        
        $conn->commit();
        $conn->close();
        
        // ลบ Google Calendar Event
        $google_deletion_success = false;
        if (!empty($google_event_id)) {
            try {
                $google_deletion_success = deleteGoogleCalendarEvent($session_id, $google_event_id);
            } catch (Exception $google_error) {
                error_log("Google Calendar event deletion failed (non-critical): " . $google_error->getMessage());
            }
        }
        
        // สร้างข้อความตอบกลับ
        $message = "ยกเลิกการเรียนการสอนเรียบร้อยแล้ว\n\n";
        $message .= "รายวิชา: {$session_data['subject_code']} - {$session_data['subject_name']}\n";
        $message .= "วันที่ยกเลิก: " . formatThaiDatePHP($cancellation_date) . "\n";
        $message .= "ประเภท: {$cancellation_type}\n";
        $message .= "เหตุผล: {$reason}\n";
        $message .= "สถานะ: {$status}\n\n";
        
        // เพิ่มข้อความเกี่ยวกับ Google Calendar
        if (!empty($google_event_id)) {
            if ($google_deletion_success) {
                $message .= "\nลบ Google Calendar Event สำเร็จ";
            } else {
                $message .= "\nไม่สามารถลบ Google Calendar Event ได้ (กรุณาลบเองใน Google Calendar)";
            }
        }
        
        jsonSuccess($message, [
            'compensation_id' => $compensation_id,
            'session_id' => $session_id,
            'session_deleted' => true,
            'status' => $status,
            'is_makeup_required' => $is_makeup_required,
            'cancellation_date' => $cancellation_date,
            'cancellation_type' => $cancellation_type,
            'google_event_deleted' => $google_deletion_success,
            'google_event_id' => $google_event_id,
            'subject_info' => [
                'code' => $session_data['subject_code'],
                'name' => $session_data['subject_name'],
                'class_year' => $session_data['class_year']
            ]
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
            $conn->close();
        }
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ดึงรายการห้องเรียน
 */
function getClassrooms() {
    try {
        $conn = connectMySQLi();
        
        $query = "
            SELECT classroom_id, room_number, building, capacity
            FROM classrooms 
            ORDER BY room_number
        ";
        
        $result = $conn->query($query);
        $classrooms = $result->fetch_all(MYSQLI_ASSOC);
        
        $conn->close();
        
        jsonSuccess('ดึงข้อมูลห้องเรียนสำเร็จ', $classrooms);
        
    } catch (Exception $e) {
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ดึงรายการช่วงเวลา
 */
function getTimeSlots() {
    try {
        $conn = connectMySQLi();
        
        $query = "
            SELECT time_slot_id, slot_number, start_time, end_time
            FROM time_slots 
            ORDER BY slot_number
        ";
        
        $result = $conn->query($query);
        $time_slots = $result->fetch_all(MYSQLI_ASSOC);
        
        $conn->close();
        
        jsonSuccess('ดึงข้อมูลช่วงเวลาสำเร็จ', $time_slots);
        
    } catch (Exception $e) {
        jsonError('เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

/**
 * ฟังก์ชันช่วยจัดรูปแบบวันที่เป็นภาษาไทย
 */
function formatThaiDatePHP($dateString) {
    if (empty($dateString)) return '';
    
    $thai_months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    
    $timestamp = strtotime($dateString);
    if (!$timestamp) return $dateString;
    
    $day = date('j', $timestamp);
    $month = $thai_months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp) + 543;
    
    return "{$day} {$month} {$year}";
}
?>