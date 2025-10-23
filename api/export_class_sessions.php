<?php
/**
 * Export Class Sessions Report to CSV/Excel
 * รองรับการกรองข้อมูลที่ยืนยันแล้วเท่านั้น
 */

// Include required files
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Helper function for Thai date formatting
function thaiDate($date) {
    if (!$date) return '';
    
    $months_th = [
        "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
        "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];

    $timestamp = strtotime($date);
    if (!$timestamp) return $date;
    
    $day = date("j", $timestamp);
    $month = (int)date("n", $timestamp);
    $year = (int)date("Y", $timestamp) + 543;

    return "{$day} {$months_th[$month]} {$year}";
}

// Helper function to fetch data
function fetchOne($query, $params = []) {
    global $conn;
    
    if (!$conn) {
        throw new Exception('Database connection not available');
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // assume all string for simplicity
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Helper function to fetch all data
function fetchAll($query, $params = []) {
    global $conn;
    
    if (!$conn) {
        throw new Exception('Database connection not available');
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // assume all string for simplicity
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// รับพารามิเตอร์
$academic_year_id = $_GET['academic_year_id'] ?? 0;
$status_filter = $_GET['status_filter'] ?? 'confirmed_only'; // confirmed_only, all
$teacher_id = $_GET['teacher_id'] ?? null; // กรองตามอาจารย์
$export_format = 'csv'; // ใช้ CSV เสมอ
$export_scope = 'confirmed_with_details'; // ใช้ confirmed_with_details เสมอ
$test_mode = isset($_GET['test']); // สำหรับทดสอบ

// ตรวจสอบพารามิเตอร์
if (!$academic_year_id || !is_numeric($academic_year_id)) {
    if ($test_mode) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'ไม่พบข้อมูลปีการศึกษาที่ถูกต้อง']);
    } else {
        die('ไม่พบข้อมูลปีการศึกษาที่ถูกต้อง');
    }
    exit;
}

// Log การ export
error_log("Export request: academic_year_id={$academic_year_id}, status_filter={$status_filter}, teacher_id={$teacher_id}, format={$export_format}, user_id=" . ($_SESSION['user_id'] ?? 'unknown'));

try {
    // ดึงข้อมูลปีการศึกษา
    $academic_query = "SELECT * FROM academic_years WHERE academic_year_id = ?";
    $academic = fetchOne($academic_query, [$academic_year_id]);

    if (!$academic) {
        throw new Exception('ไม่พบข้อมูลปีการศึกษา ID: ' . $academic_year_id);
    }

    // สร้างเงื่อนไขกรองอาจารย์
    $teacher_condition = "";
    $teacher_params = [];
    if ($teacher_id && is_numeric($teacher_id)) {
        $teacher_condition = " AND ts.user_id = ?";
        $teacher_params = [$teacher_id];
    }

    // ดึงข้อมูลอาจารย์ที่เลือก (ถ้ามี)
    $selected_teacher = null;
    if ($teacher_id) {
        $teacher_query = "SELECT CONCAT(title, name, ' ', lastname) as teacher_name FROM users WHERE user_id = ?";
        $selected_teacher = fetchOne($teacher_query, [$teacher_id]);
    }

    // ดึงข้อมูล Compensation Logs (กรองตามอาจารย์และสถานะ)
    $compensation_where_clause = "WHERE ts.academic_year_id = ?";
    if ($status_filter === 'confirmed_only') {
        $compensation_where_clause .= " AND cl.status = 'ดำเนินการแล้ว'";
    }
    $compensation_where_clause .= $teacher_condition;

    $compensation_query = "
        SELECT 
            cl.*,
            ts.schedule_id,
            s.subject_code, s.subject_name,
            u.title, u.name, u.lastname,
            yl.department, yl.class_year,
            c.room_number,
            start_slot.start_time, end_slot.end_time,
            makeup_room.room_number as makeup_room_number,
            makeup_start.start_time as makeup_start_time,
            makeup_end.end_time as makeup_end_time
        FROM compensation_logs cl
        LEFT JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
        LEFT JOIN subjects s ON ts.subject_id = s.subject_id
        LEFT JOIN users u ON ts.user_id = u.user_id
        LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
        LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
        LEFT JOIN time_slots start_slot ON ts.start_time_slot_id = start_slot.time_slot_id
        LEFT JOIN time_slots end_slot ON ts.end_time_slot_id = end_slot.time_slot_id
        LEFT JOIN classrooms makeup_room ON cl.makeup_classroom_id = makeup_room.classroom_id
        LEFT JOIN time_slots makeup_start ON cl.makeup_start_time_slot_id = makeup_start.time_slot_id
        LEFT JOIN time_slots makeup_end ON cl.makeup_end_time_slot_id = makeup_end.time_slot_id
        $compensation_where_clause
        ORDER BY cl.cancellation_date DESC
    ";
    
    $compensation_params = array_merge([$academic_year_id], $teacher_params);
    $compensations = fetchAll($compensation_query, $compensation_params);

    // กำหนดชื่อไฟล์และ Content-Type ตามรูปแบบ
    $teacher_label = $selected_teacher ? '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $selected_teacher['teacher_name']) : '';
    $status_label = ($status_filter === 'confirmed_only') ? 'confirmed_only' : 'all';
    
    // สำหรับ CSV format
    $filename = "compensation_report{$teacher_label}_{$status_label}_" . $academic['academic_year'] . "_" . $academic['semester'] . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8 (สำคัญสำหรับ Excel)
    fwrite($output, "\xEF\xBB\xBF");

    // Helper function to format time slot
    function formatTimeSlot($start_time, $end_time) {
        if (!$start_time || !$end_time) return '';
        return substr($start_time, 0, 5) . '-' . substr($end_time, 0, 5);
    }

    // Write headers and data

    // 1. Summary Information
    $report_title = ($status_filter === 'confirmed_only') ? 
        'รายงานการชดเชยที่ยืนยันแล้ว + รายละเอียด (Export แบบ CSV)' : 
        'รายงานระบบจัดการตารางเรียนตามวันหยุดปฏิทิน';

    fputcsv($output, [$report_title], ',');
    fputcsv($output, ['ปีการศึกษา ' . $academic['academic_year'] . ' เทอม ' . $academic['semester']], ',');
    fputcsv($output, ['ระหว่างวันที่ ' . thaiDate($academic['start_date']) . ' - ' . thaiDate($academic['end_date'])], ',');
    
    // แสดงข้อมูลอาจารย์ที่เลือก
    if ($selected_teacher) {
        fputcsv($output, ['อาจารย์ที่เลือก: ' . $selected_teacher['teacher_name']], ',');
    } else {
        fputcsv($output, ['อาจารย์: ทั้งหมด'], ',');
    }
    
    fputcsv($output, ['รูปแบบไฟล์: CSV'], ',');
    fputcsv($output, ['สร้างรายงานเมื่อ ' . thaiDate(date('Y-m-d')) . ' เวลา ' . date('H:i:s')], ',');

    if ($status_filter === 'confirmed_only') {
        fputcsv($output, ['*** รายงานนี้แสดงเฉพาะข้อมูลการชดเชยที่ได้รับการยืนยันแล้ว + รายละเอียด ***'], ',');
        if ($selected_teacher) {
            fputcsv($output, ['*** กรองเฉพาะอาจารย์: ' . $selected_teacher['teacher_name'] . ' ***'], ',');
        }
    }

    fputcsv($output, [], ','); // Empty row

    // 2. Statistics
    fputcsv($output, ['=== สถิติโดยสรุป ==='], ',');
    fputcsv($output, ['รายการ', 'จำนวน'], ',');

    if ($status_filter === 'confirmed_only') {
        fputcsv($output, ['การชดเชยที่ยืนยันแล้ว', count($compensations)], ',');
        
        if ($selected_teacher) {
            fputcsv($output, ['อาจารย์ที่เลือก', $selected_teacher['teacher_name']], ',');
        }
        
        // แสดงสถิติเพิ่มเติมสำหรับข้อมูลที่ยืนยัน
        $makeup_required = array_filter($compensations, function($comp) {
            return $comp['is_makeup_required'] == 1;
        });
        fputcsv($output, ['การชดเชยที่ต้องมีการสอนชดเชย', count($makeup_required)], ',');
        
        $makeup_completed = array_filter($compensations, function($comp) {
            return $comp['makeup_date'] !== null && $comp['makeup_date'] !== '';
        });
        fputcsv($output, ['การชดเชยที่กำหนดวันสอนชดเชยแล้ว', count($makeup_completed)], ',');
    }
    fputcsv($output, [], ','); // Empty row

    // 3. Compensation Logs Data
    $compensation_title = ($status_filter === 'confirmed_only') ? 
        '=== บันทึกการชดเชยที่ยืนยันแล้ว' . ($selected_teacher ? ' (อาจารย์: ' . $selected_teacher['teacher_name'] . ')' : '') . ' ===' : 
        '=== บันทึกการยกเลิกและชดเชย ===';

    fputcsv($output, [$compensation_title], ',');
    fputcsv($output, [
        'วันที่ยกเลิก',
        'ประเภทการยกเลิก',
        'เหตุผล',
        'รหัสวิชา',
        'ชื่อวิชา',
        'อาจารย์',
        'ชั้นปี',
        'ห้องเดิม',
        'เวลาเดิม',
        'ต้องชดเชย',
        'วันชดเชย',
        'ห้องชดเชย',
        'เวลาชดเชย',
        'สถานะ',
        'บันทึกเมื่อ'
    ], ',');

    foreach ($compensations as $comp) {
        $teacher_name = ($comp['title'] ?? '') . ($comp['name'] ?? '') . ' ' . ($comp['lastname'] ?? '');
        $class_year = ($comp['department'] ?? '') . ($comp['class_year'] ?? '');
        $original_time = '';
        if ($comp['start_time'] && $comp['end_time']) {
            $original_time = formatTimeSlot($comp['start_time'], $comp['end_time']);
        }
        
        $makeup_time = '';
        if ($comp['makeup_start_time'] && $comp['makeup_end_time']) {
            $makeup_time = formatTimeSlot($comp['makeup_start_time'], $comp['makeup_end_time']);
        }
        
        fputcsv($output, [
            thaiDate($comp['cancellation_date']),
            $comp['cancellation_type'],
            $comp['reason'],
            $comp['subject_code'] ?? '',
            $comp['subject_name'] ?? '',
            $teacher_name,
            $class_year,
            $comp['room_number'] ?? '',
            $original_time,
            $comp['is_makeup_required'] ? 'ต้องชดเชย' : 'ไม่ต้องชดเชย',
            $comp['makeup_date'] ? thaiDate($comp['makeup_date']) : '',
            $comp['makeup_room_number'] ?? '',
            $makeup_time,
            $comp['status'],
            thaiDate($comp['created_at'])
        ], ',');
    }

    fputcsv($output, [], ',');
    fputcsv($output, ['=== จบรายงาน ==='], ',');
    fputcsv($output, ['สร้างโดยระบบจัดการตารางเรียน - Export แบบ CSV พร้อมรายละเอียด'], ',');

    // Close output stream
    fclose($output);

    // Log this export activity
    error_log('Export completed successfully for academic_year_id: ' . $academic_year_id);

} catch (Exception $e) {
    // จัดการข้อผิดพลาด
    error_log("Export error: " . $e->getMessage());
    
    if ($test_mode) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        // ส่งข้อผิดพลาดในรูปแบบ CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="export_error.csv"');
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['เกิดข้อผิดพลาดในการ Export'], ',');
        fputcsv($output, ['ข้อผิดพลาด: ' . $e->getMessage()], ',');
        fputcsv($output, ['กรุณาติดต่อผู้ดูแลระบบ'], ',');
        fclose($output);
    }
}
?>