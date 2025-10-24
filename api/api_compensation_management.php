<?php

/**
 * API หลักสำหรับจัดการการชดเชยการเรียนการสอนพร้อม Workflow ระบบอนุมัติ
 * เวอร์ชัน 2.0 - แยกไฟล์เพื่อง่ายต่อการจัดการ
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

// ตั้งค่า error handler
register_shutdown_function(function () {
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
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once '../config/database.php';
require_once 'compensation/compensation_functions.php';
require_once 'compensation/compensation_workflow.php';
require_once 'compensation/compensation_schedule.php';
require_once 'compensation/compensation_support.php';

// ตรวจสอบการเข้าสู่ระบบ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    jsonError('ไม่ได้รับอนุญาต - กรุณาล็อกอินใหม่', 401);
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    jsonError('ไม่ได้ระบุ action ที่ต้องการ');
}

// บันทึก debug log
error_log("=== Compensation Management API v2.0 ===");
error_log("Action: " . $action);
error_log("User ID: " . $user_id);

// ล้าง buffer ก่อนประมวลผล
ob_clean();

try {
    switch ($action) {
        // === การดึงข้อมูล ===
        case 'get_all_compensations':
            getAllCompensations();
            break;
        case 'get_compensation_details':
            getCompensationDetails();
            break;
        case 'get_teachers_for_auto_schedule':
            getTeachersForAutoSchedule();
            break;

        // === Workflow ขั้นตอน Preview ===
        case 'preview_auto_schedule_single':
            previewAutoScheduleSingle();
            break;

        // === Workflow ขั้นตอนยืนยัน ===
        case 'confirm_auto_schedule_single':
            confirmAutoScheduleSingle();
            break;
        case 'confirm_manual_schedule':
            confirmManualSchedule();
            break;

        // === Workflow การอนุมัติ ===
        case 'approve_compensation_schedule':
            approveCompensationSchedule();
            break;
        case 'reject_compensation_schedule':
            rejectCompensationSchedule();
            break;
        case 'request_date_change':
            requestDateChange();
            break;
        case 'request_revision':
            requestRevision();
            break;

        // === การจัดการทั้งหมด ===
        case 'auto_schedule_all_compensations':
            autoScheduleAllCompensations();
            break;

        // === ข้อมูลสนับสนุน ===
        case 'get_classrooms':
            getClassrooms();
            break;
        case 'get_time_slots':
            getTimeSlots();
            break;
        case 'get_room_availability':
            getRoomAvailability();
            break;
        case 'get_compensation_stats':
            getCompensationStats();
            break;
        case 'get_academic_year_range':
            getAcademicYearRange();
            break;
        case 'get_teachers_with_compensations':
            getTeachersWithCompensations();
            break;
        case 'test_connection':
            jsonSuccess('API ทำงานปกติ', [
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $user_id,
                'workflow_version' => '2.0',
                'php_version' => PHP_VERSION
            ]);
            break;

        case 'test_database':
            testDatabase();
            break;

        // === ข้อมูลความพร้อมของห้องเรียนแบบละเอียด ===
        case 'get_detailed_room_availability':
            getDetailedRoomAvailability();
            break;

        default:
            jsonError('Action ไม่ถูกต้อง: ' . $action);
            break;
    }
} catch (Exception $e) {
    error_log("Exception in compensation management API: " . $e->getMessage());
    jsonError('Exception: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("Fatal Error in compensation management API: " . $e->getMessage());
    jsonError('Fatal Error: ' . $e->getMessage(), 500);
}
?>