<?php
/**
 * Export Compensation Report to PDF using TCPDF
 * รูปแบบใบสอนชดเชยตามมาตรฐาน Eng-Srivijaya FM 02-21
 */

// เพิ่ม output buffering และ error handling
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once '../config/database.php';
require_once '../vendor/autoload.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Helper functions
function thaiDate($date) {
    if (!$date) return '';
    $months_th = [
        "", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];
    $timestamp = strtotime($date);
    if (!$timestamp) return $date;
    $day = date("j", $timestamp);
    $month = (int)date("n", $timestamp);
    $year = (int)date("Y", $timestamp) + 543;
    return "{$day} {$months_th[$month]} {$year}";
}

function shortThaiDate($date) {
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
    $year_short = substr($year, -2);
    return "{$day} {$months_th[$month]} {$year_short}";
}

function formatTime($time) {
    if (!$time) return '';
    return date('H.i', strtotime($time));
}

function fetchOne($query, $params = []) {
    global $conn;
    if (!$conn) throw new Exception('Database connection not available');
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception('Failed to prepare statement: ' . $conn->error);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function fetchAll($query, $params = []) {
    global $conn;
    if (!$conn) throw new Exception('Database connection not available');
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception('Failed to prepare statement: ' . $conn->error);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
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

$academic_year_id = $_GET['academic_year_id'] ?? 0;
$status_filter = $_GET['status_filter'] ?? 'confirmed_only';
$teacher_id = $_GET['teacher_id'] ?? null;
$cancellation_id = $_GET['cancellation_id'] ?? null;
$test_mode = isset($_GET['test']);

// ตรวจสอบพารามิเตอร์
if (!$academic_year_id || !is_numeric($academic_year_id)) {
    ob_end_clean();
    if ($test_mode) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'ไม่พบข้อมูลปีการศึกษาที่ถูกต้อง',
            'received_params' => $_GET
        ]);
    } else {
        die('ไม่พบข้อมูลปีการศึกษาที่ถูกต้อง');
    }
    exit;
}

try {
    // ตรวจสอบการเชื่อมต่อฐานข้อมูล
    if (!$conn) throw new Exception('Database connection failed');

    // ดึงข้อมูลปีการศึกษา
    $academic_query = "SELECT * FROM academic_years WHERE academic_year_id = ?";
    $academic = fetchOne($academic_query, [$academic_year_id]);
    if (!$academic) throw new Exception('ไม่พบข้อมูลปีการศึกษา ID: ' . $academic_year_id);

    // สร้างเงื่อนไขกรองอาจารย์
    $teacher_condition = "";
    $teacher_params = [];
    if ($teacher_id && is_numeric($teacher_id)) {
        $teacher_condition = " AND ts.user_id = ?";
        $teacher_params = [$teacher_id];
    }

    // ดึงข้อมูลอาจารย์ที่เลือก
    $selected_teacher = null;
    if ($teacher_id) {
        $teacher_query = "SELECT title, name, lastname, secname, depname, reason, CONCAT(title, name, ' ', lastname) as teacher_name FROM users WHERE user_id = ?";
        $selected_teacher = fetchOne($teacher_query, [$teacher_id]);
    }
    if (!$selected_teacher) {
        $selected_teacher = getCurrentUser();
    }

    // ดึงข้อมูล Compensation Logs
    $compensation_where_clause = "WHERE ts.academic_year_id = ?";
    if ($status_filter === 'confirmed_only') {
    $compensation_where_clause .= " AND cl.status IN ('ดำเนินการแล้ว', 'รอยืนยัน')";
    }
    $compensation_where_clause .= $teacher_condition;
    $compensation_params = array_merge([$academic_year_id], $teacher_params);
    if ($cancellation_id && is_numeric($cancellation_id)) {
        $compensation_where_clause .= " AND cl.cancellation_id = ?";
        $compensation_params[] = $cancellation_id;
    }

    $compensation_query = "
        SELECT 
            cl.cancellation_id,
            cl.schedule_id,
            cl.cancellation_date,
            cl.reason,
            cl.makeup_date,
            cl.proposed_makeup_date,
            cl.proposed_makeup_classroom_id,
            cl.proposed_makeup_start_time_slot_id,
            cl.proposed_makeup_end_time_slot_id,
            s.subject_code, s.subject_name,
            u.title, u.name, u.lastname, u.secname, u.depname,
            yl.department, yl.class_year,
            c.room_number,
            start_slot.start_time, end_slot.end_time,
            makeup_room.room_number as makeup_room_number,
            makeup_start.start_time as makeup_start_time,
            makeup_end.end_time as makeup_end_time,
            proposed_makeup_room.room_number as proposed_makeup_room_number,
            proposed_makeup_start.start_time as proposed_makeup_start_time,
            proposed_makeup_end.end_time as proposed_makeup_end_time,
            approved_user.name as approved_by_name
        FROM compensation_logs cl
        LEFT JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
        LEFT JOIN subjects s ON ts.subject_id = s.subject_id
        LEFT JOIN users u ON ts.user_id = u.user_id
        LEFT JOIN users approved_user ON cl.approved_by = approved_user.user_id
        LEFT JOIN year_levels yl ON ts.year_level_id = yl.year_level_id
        LEFT JOIN classrooms c ON ts.classroom_id = c.classroom_id
        LEFT JOIN time_slots start_slot ON ts.start_time_slot_id = start_slot.time_slot_id
        LEFT JOIN time_slots end_slot ON ts.end_time_slot_id = end_slot.time_slot_id
        LEFT JOIN classrooms makeup_room ON cl.makeup_classroom_id = makeup_room.classroom_id
        LEFT JOIN time_slots makeup_start ON cl.makeup_start_time_slot_id = makeup_start.time_slot_id
        LEFT JOIN time_slots makeup_end ON cl.makeup_end_time_slot_id = makeup_end.time_slot_id
        LEFT JOIN classrooms proposed_makeup_room ON cl.proposed_makeup_classroom_id = proposed_makeup_room.classroom_id
        LEFT JOIN time_slots proposed_makeup_start ON cl.proposed_makeup_start_time_slot_id = proposed_makeup_start.time_slot_id
        LEFT JOIN time_slots proposed_makeup_end ON cl.proposed_makeup_end_time_slot_id = proposed_makeup_end.time_slot_id
        $compensation_where_clause
        ORDER BY cl.cancellation_date DESC
    ";
    $compensations = fetchAll($compensation_query, $compensation_params);

    // ล้าง output buffer ก่อนสร้าง PDF
    ob_end_clean();

    // สร้าง PDF ด้วย TCPDF - ใช้ portrait orientation
    $pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);

    // ตั้งค่าข้อมูลเอกสาร
    $pdf->SetCreator('ระบบจัดการตารางเรียน');
    $pdf->SetAuthor('ระบบจัดการตารางเรียน');
    $pdf->SetTitle('ใบสอนชดเชย');
    $pdf->SetSubject('ใบสอนชดเชย');

    // ปิด header และ footer อัตโนมัติ
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // ตั้งค่าระยะขอบ
    $pdf->SetMargins(20, 15, 20);

    // ตั้งค่าฟอนต์เริ่มต้น
    $pdf->SetFont('thsarabunnew', '', 14);

    // เพิ่มหน้า
    $pdf->AddPage();

    // ส่วนหัวบนขวา - เลขที่เอกสาร
    $pdf->SetFont('thsarabunnew', '', 13);
    $pdf->Cell(0, 5, 'หน้าที่...1.../...1...', 0, 1, 'R');
    $pdf->Ln(2);

    // ส่วนหัวซ้าย - ชื่อมหาวิทยาลัยและคณะ
    $pdf->SetFont('thsarabunnew', '', 13);
    $pdf->MultiCell(0, 5, "มหาวิทยาลัยเทคโนโลยีราชมงคลศรีวิชัย\nคณะวิศวกรรมศาสตร์", 0, 'L', 0, 1, '', '', true);
    $pdf->Ln(5);

    // หัวข้อเอกสาร
    $pdf->SetFont('thsarabunnew', 'B', 18);
    $pdf->Cell(0, 10, 'ใบสอนชดเชย', 0, 1, 'C');
    $pdf->Ln(5);

    // วันที่พิมพ์เอกสาร
    $months_th_full = [
        "", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];
    $current_month = $months_th_full[(int)date('n')];
    $pdf->SetFont('thsarabunnew', '', 16);
    $pdf->Cell(0, 6, "วันที่..." . date('d') . "... เดือน..." . $current_month . ".... พ.ศ. ..." . (date('Y') + 543) . ".........", 0, 1, 'R');
    $pdf->Ln(3);

    // เรียน คณบดี
    $pdf->SetFont('thsarabunnew', '', 16);
    $pdf->Cell(0, 7, 'เรียน     คณบดีคณะวิศวกรรมศาสตร์', 0, 1, 'L');
    $pdf->Ln(2);

    // ข้อมูลอาจารย์
    if ($selected_teacher) {
        $teacher_title = $selected_teacher['title'] ?? '';
        $teacher_name = $selected_teacher['name'] ?? '';
        $teacher_lastname = $selected_teacher['lastname'] ?? '';
        $section_name = $selected_teacher['secname'] ?? '';
        $dep_name = $selected_teacher['depname'] ?? '';
        $fac_name = 'วิศวกรรมศาสตร์';

        // ดึง reason จาก compensation_logs ตัวแรกที่มีข้อมูล
        $reason = 'ไม่ระบุเหตุผล';
        $cancel_date = '';
        if (count($compensations) > 0) {
            foreach ($compensations as $comp) {
                if (isset($comp['reason']) && trim($comp['reason']) !== '') {
                    $reason = trim($comp['reason']);
                    $cancel_date = $comp['cancellation_date'] ? thaiDate($comp['cancellation_date']) : '';
                    break;
                }
            }
        } elseif (isset($selected_teacher['reason']) && trim($selected_teacher['reason']) !== '') {
            $reason = trim($selected_teacher['reason']);
        }

        $pdf->SetFont('thsarabunnew', '', 16);
        $teacher_info_line1 = "            ข้าพเจ้า {$teacher_title} {$teacher_name} {$teacher_lastname} สังกัดหลักสูตรสาขาวิชา {$section_name}............................................";
        $teacher_info_line2 = "สาขา {$dep_name} คณะ {$fac_name} ไม่สามารถทำการสอนได้ทันตามหลักสูตรเนื่องจาก..................";
        $teacher_info_line3 = "ตรงกับ{$reason}" . ($cancel_date ? " วันที่ {$cancel_date}" : "");
        $teacher_info_line4 = ".......................................................................................................................................................................................";

        $pdf->MultiCell(0, 7, $teacher_info_line1, 0, 'L', 0, 1, '', '', true);
        $pdf->MultiCell(0, 7, $teacher_info_line2, 0, 'L', 0, 1, '', '', true);
        $pdf->MultiCell(0, 7, $teacher_info_line3, 0, 'L', 0, 1, '', '', true);
        $pdf->MultiCell(0, 7, $teacher_info_line4, 0, 'L', 0, 1, '', '', true);

        $pdf->Ln(2);
    } else {
        $pdf->SetFont('thsarabunnew', '', 16);
        $pdf->MultiCell(0, 7, "ไม่พบข้อมูลอาจารย์", 0, 'L', 0, 1, '', '', true);
        $pdf->Ln(2);
    }
    // ข้อความก่อนตาราง
    $pdf->SetFont('thsarabunnew', '', 16);
    $pdf->Cell(0, 6, 'จึงขออนุญาตทำการสอนชดเชยดังนี้', 0, 1, 'L');
    $pdf->Ln(3);

    if (count($compensations) > 0) {
        // สร้างตาราง HTML ตามรูปแบบในภาพ - ตารางสมบูรณ์และเรียบร้อย
        $html = '<style>
            table.compensation {
                border-collapse: collapse;
            }
            table.compensation th, table.compensation td {
                border: 1px solid #000000;
                padding: 5px 3px;
                vertical-align: middle;
            }
            table.compensation th {
                background-color: #d9d9d9;
                font-weight: bold;
                text-align: center;
                font-size: 11pt;
            }
            table.compensation td {
                font-size: 10pt;
            }
            .thick-border-right {
                border-right: 2px solid #000000 !important;
            }
            .header-main {
                border-bottom: 2px solid #000000;
            }
        </style>';

        $html .= '<table class="compensation" cellspacing="0">';
        $html .= '<thead>';
        $html .= '<tr >';
        $html .= '<th width="80%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">รายละเอียดการสอนเดิม</th>';
        $html .= '<th width="30%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">รายละเอียดการสอนชดเชย</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th width="10%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">ว/ด/ป</th>';
        $html .= '<th width="34%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">ชื่อวิชา</th>';
        $html .= '<th width="10%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">รหัสวิชา</th>'; 
        $html .= '<th width="10%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">เวลา</th>';
        $html .= '<th width="8%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">ห้อง</th>';
        $html .= '<th width="8%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">ชั้นปี</th>';
        $html .= '<th width="10%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">ว/ด/ป</th>';
        $html .= '<th width="10%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">เวลา</th>';
        $html .= '<th width="10%" style="padding: 14px 5px; font-size:13pt; height:28px; text-align:center;">ห้อง</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        // ข้อมูลในตาราง
        $html .= '<tbody>';
        foreach ($compensations as $comp) {
            $subject_name = $comp['subject_name'] ?? '';
            $class_year = ($comp['department'] ?? '') . ($comp['class_year'] ?? '');
            $original_time = '';
            if ($comp['start_time'] && $comp['end_time']) {
                $original_time = formatTime($comp['start_time']) . '-' . formatTime($comp['end_time']);
            }
            $makeup_time = '';
            if ($comp['proposed_makeup_start_time'] && $comp['proposed_makeup_end_time']) {
                $makeup_time = formatTime($comp['proposed_makeup_start_time']) . '-' . formatTime($comp['proposed_makeup_end_time']);
            }
            $makeup_date = $comp['proposed_makeup_date'] ?? '';
            $html .= '<tr>';
            $html .= '<td width="10%" style="height:20px;text-align:center;">' . shortThaiDate($comp['cancellation_date']) . '</td>';
            $html .= '<td width="34%" style="height:20px;">' . htmlspecialchars($subject_name) . '</td>';
            $html .= '<td width="10%" style="height:20px;text-align:center;">' . ($comp['subject_code'] ?? '') . '</td>';
            $html .= '<td width="10%" style="height:20px;text-align:center;">' . $original_time . '</td>';
            $html .= '<td width="8%" style="height:20px;text-align:center;">' . ($comp['room_number'] ?? '') . '</td>';
            $html .= '<td width="8%" style="height:20px;text-align:center;">' . $class_year . '</td>';
            $html .= '<td width="10%" style="height:20px;text-align:center;">' . ($makeup_date ? shortThaiDate($makeup_date) : '') . '</td>';
            $html .= '<td width="10%" style="height:20px;text-align:center;">' . $makeup_time . '</td>';
            $html .= '<td width="10%" style="height:20px;text-align:center;">' . ($comp['proposed_makeup_room_number'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        // เพิ่มแถวว่างสำหรับกรอกข้อมูล
        for ($i = 0; $i < 7; $i++) {
            $html .= '<tr>';
            $html .= '<td width="10%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="34%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="8%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="8%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="height:20px;">&nbsp;</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
        // แสดงตาราง
        $pdf->SetFont('thsarabunnew', '', 13);
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->SetFont('thsarabunnew', '', 13);
        $pdf->Cell(0, 20, 'ไม่พบข้อมูลการชดเชยที่ตรงกับเงื่อนไขที่กำหนด', 0, 1, 'C');
    }

    // ส่วนลายเซ็น
    $pdf->Ln(8);
    $pdf->SetFont('thsarabunnew', '', 16);
    // แถวลายเซ็นแรก
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 0, 'C');
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 1, 'C');
    $pdf->Cell(90, 6, '          อาจารย์ผู้สอน', 0, 0, 'C');
    $pdf->Cell(90, 6, '          ประธานหลักสูตรสาขาวิชา', 0, 1, 'C');
    $pdf->Ln(5);
    // แถวลายเซ็นที่สอง
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 0, 'C');
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 1, 'C');
    $pdf->Cell(90, 6, '          รองคณบดีฝ่ายวิชาการและวิจัย', 0, 0, 'C');
    $pdf->Cell(90, 6, '          คณบดีคณะวิศวกรรมศาสตร์', 0, 1, 'C');
    $pdf->Ln(8);

    // หมายเหตุ
    $pdf->SetFont('thsarabunnew', '', 16);
    $note_text = "หมายเหตุ     1. ต้องจัดทำล่วงหน้าอย่างน้อย 1 สัปดาห์  เพื่อแนบในบันทึกการสอนภายนอกเวลา\n";
    $note_text .= "                 2. การทำการสอนชดเชยต้องสอนก่อน หรือ หลังการขาดการสอน 1 สัปดาห์";
    $pdf->MultiCell(0, 6, $note_text, 0, 'L', 0, 1, '', '', true);

    // กำหนดชื่อไฟล์
    if ($cancellation_id) {
        $filename = "ใบสอนชดเชย_{$cancellation_id}_" . date('Ymd_His') . ".pdf";
    } else {
        $teacher_label = $selected_teacher ? '_' . preg_replace('/[^ก-๙a-zA-Z0-9_-]/u', '', $selected_teacher['teacher_name'] ?? '') : '';
        $filename = "ใบสอนชดเชย{$teacher_label}_" . date('Ymd_His') . ".pdf";
    }

    // Output PDF
    $pdf->Output($filename, 'D');

} catch (Exception $e) {
    ob_end_clean();
    if ($test_mode) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        die('เกิดข้อผิดพลาดในการสร้าง PDF: ' . $e->getMessage());
    }
}
?>