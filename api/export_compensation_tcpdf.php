<?php
/**
 * Export Compensation Report to PDF using TCPDF
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once '../config/database.php';
require_once '../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$teacher_role = $_GET['teacher_role'] ?? 'main';
$test_mode = isset($_GET['test']);

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
    if (!$conn) throw new Exception('Database connection failed');

    $academic_query = "SELECT * FROM academic_years WHERE academic_year_id = ?";
    $academic = fetchOne($academic_query, [$academic_year_id]);
    if (!$academic) throw new Exception('ไม่พบข้อมูลปีการศึกษา ID: ' . $academic_year_id);

    $teacher_condition = "";
    $teacher_params = [];
    if ($teacher_id && is_numeric($teacher_id)) {
        $teacher_condition = " AND ts.user_id = ?";
        $teacher_params = [$teacher_id];
    }

    $selected_teacher = null;
    if ($teacher_id) {
        $teacher_query = "SELECT title, name, lastname, secname, depname, reason, CONCAT(title, name, ' ', lastname) as teacher_name FROM users WHERE user_id = ?";
        $selected_teacher = fetchOne($teacher_query, [$teacher_id]);
    }
    if (!$selected_teacher) {
        $selected_teacher = getCurrentUser();
    }

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
            ts.is_module_subject, ts.group_id,
            yl.department, yl.class_year, yl.curriculum,
            c.room_number,
            start_slot.start_time, end_slot.end_time,
            makeup_room.room_number as makeup_room_number,
            makeup_start.start_time as makeup_start_time,
            makeup_end.end_time as makeup_end_time,
            proposed_makeup_room.room_number as proposed_makeup_room_number,
            proposed_makeup_start.start_time as proposed_makeup_start_time,
            proposed_makeup_end.end_time as proposed_makeup_end_time,
            approved_user.name as approved_by_name,
            co1.title as co1_title, co1.name as co1_name, co1.lastname as co1_lastname,
            co2.title as co2_title, co2.name as co2_name, co2.lastname as co2_lastname
        FROM compensation_logs cl
        LEFT JOIN teaching_schedules ts ON cl.schedule_id = ts.schedule_id
        LEFT JOIN subjects s ON ts.subject_id = s.subject_id
        LEFT JOIN users u ON ts.user_id = u.user_id
        LEFT JOIN users co1 ON ts.co_user_id = co1.user_id
        LEFT JOIN users co2 ON ts.co_user_id_2 = co2.user_id
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

    // ดึง year_levels_in_group สำหรับแต่ละ compensation ถ้าเป็นโมดูล
    foreach ($compensations as $idx => $comp) {
        $year_levels_in_group = [];
        if (isset($comp['is_module_subject']) && $comp['is_module_subject'] == 1 && !empty($comp['group_id'])) {
            $group_id = $comp['group_id'];
            $yl_query = "SELECT yl.year_level_id, yl.department, yl.class_year, yl.curriculum
                         FROM module_group_year_levels mgyl
                         JOIN year_levels yl ON mgyl.year_level_id = yl.year_level_id
                         WHERE mgyl.group_id = ?";
            $yl_stmt = $conn->prepare($yl_query);
            $yl_stmt->bind_param("i", $group_id);
            $yl_stmt->execute();
            $yl_result = $yl_stmt->get_result();
            while ($row = $yl_result->fetch_assoc()) {
                $year_levels_in_group[] = $row;
            }
            $compensations[$idx]['year_levels_in_group'] = $year_levels_in_group;
        } else {
            $compensations[$idx]['year_levels_in_group'] = [];
        }
    }

    ob_end_clean();

    $pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);

    $pdf->SetCreator('ระบบจัดการตารางเรียน');
    $pdf->SetAuthor('ระบบจัดการตารางเรียน');
    $pdf->SetTitle('ใบสอนชดเชย');
    $pdf->SetSubject('ใบสอนชดเชย');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetMargins(20, 15, 20);

    $pdf->SetFont('thsarabunnew', '', 14);

    $pdf->AddPage();

    $pdf->SetFont('thsarabunnew', '', 13);
    $pdf->Cell(0, 5, 'หน้าที่...1.../...1...', 0, 1, 'R');
    $pdf->Ln(2);

    $pdf->SetFont('thsarabunnew', '', 13);
    $pdf->MultiCell(0, 5, "มหาวิทยาลัยเทคโนโลยีราชมงคลศรีวิชัย\nคณะวิศวกรรมศาสตร์", 0, 'L', 0, 1, '', '', true);
    $pdf->Ln(5);

    $pdf->SetFont('thsarabunnew', 'B', 18);
    $pdf->Cell(0, 10, 'ใบสอนชดเชย', 0, 1, 'C');
    $pdf->Ln(5);

    $months_th_full = [
        "", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];
    $current_month = $months_th_full[(int)date('n')];
    $pdf->SetFont('thsarabunnew', '', 16);
    $pdf->Cell(0, 6, "วันที่..." . date('d') . "... เดือน..." . $current_month . ".... พ.ศ. ..." . (date('Y') + 543) . ".........", 0, 1, 'R');
    $pdf->Ln(3);

    $pdf->SetFont('thsarabunnew', '', 16);
    $pdf->Cell(0, 7, 'เรียน     คณบดีคณะวิศวกรรมศาสตร์', 0, 1, 'L');
    $pdf->Ln(2);

    // ข้อมูลอาจารย์ (เลือกบทบาทตาม teacher_role)
    $teacher_title = '';
    $teacher_name = '';
    $teacher_lastname = '';
    $section_name = 'วิศวกรรมศาสตร์';
    $dep_name = 'วิศวกรรมศาสตร์';
    $fac_name = 'วิศวกรรมศาสตร์';
    $co_teacher_name = '';
    $co_teacher_name_2 = '';

    if (count($compensations) > 0) {
        $first_comp = $compensations[0];
        if ($teacher_role === 'main') {
            $teacher_title = $first_comp['title'] ?? '';
            $teacher_name = $first_comp['name'] ?? '';
            $teacher_lastname = $first_comp['lastname'] ?? '';
        } elseif ($teacher_role === 'co1') {
            $co1_title = $first_comp['co1_title'] ?? '';
            $co1_name = $first_comp['co1_name'] ?? '';
            $co1_lastname = $first_comp['co1_lastname'] ?? '';
        } elseif ($teacher_role === 'co2') {
            $co2_title = $first_comp['co2_title'] ?? '';
            $co2_name = $first_comp['co2_name'] ?? '';
            $co2_lastname = $first_comp['co2_lastname'] ?? '';
        }
    }

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
    if ($teacher_role === 'main') {
        $teacher_info_line1 = "            ข้าพเจ้า {$teacher_title} {$teacher_name} {$teacher_lastname} สังกัดหลักสูตรสาขาวิชา {$section_name} สาขา {$dep_name}";
    } elseif ($teacher_role === 'co1') {
        $teacher_info_line1 = "            ข้าพเจ้า {$co1_title} {$co1_name} {$co1_lastname} สังกัดหลักสูตรสาขาวิชา {$section_name} สาขา {$dep_name}";
    } elseif ($teacher_role === 'co2') {
        $teacher_info_line1 = "            ข้าพเจ้า {$co2_title} {$co2_name} {$co2_lastname} สังกัดหลักสูตรสาขาวิชา {$section_name} สาขา {$dep_name}";
    }
    $teacher_info_line2 = "คณะ {$fac_name} ไม่สามารถทำการสอนได้ทันตามหลักสูตรเนื่องจาก ตรงกับ {$reason}";
    $teacher_info_line3 = "" . ($cancel_date ? "วันที่ {$cancel_date}" : "");

    $pdf->MultiCell(0, 7, $teacher_info_line1, 0, 'L', 0, 1, '', '', true);
    $pdf->MultiCell(0, 7, $teacher_info_line2, 0, 'L', 0, 1, '', '', true);
    $pdf->MultiCell(0, 7, $teacher_info_line3, 0, 'L', 0, 1, '', '', true);
    $pdf->Ln(2);

    $pdf->SetFont('thsarabunnew', '', 16);
    $pdf->Cell(0, 6, 'จึงขออนุญาตทำการสอนชดเชยดังนี้', 0, 1, 'L');
    $pdf->Ln(3);

    if (count($compensations) > 0) {
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
        $html .= '<tbody>';
        foreach ($compensations as $comp) {
            $subject_name = $comp['subject_name'] ?? '';
            $original_time = '';
            if ($comp['start_time'] && $comp['end_time']) {
                $original_time = formatTime($comp['start_time']) . '-' . formatTime($comp['end_time']);
            }
            $makeup_time = '';
            if ($comp['proposed_makeup_start_time'] && $comp['proposed_makeup_end_time']) {
                $makeup_time = formatTime($comp['proposed_makeup_start_time']) . '-' . formatTime($comp['proposed_makeup_end_time']);
            }
            $makeup_date = $comp['proposed_makeup_date'] ?? '';

            // ถ้าเป็นโมดูลและมีหลายชั้นปี ให้สร้างแถวละชั้นปี
            if (!empty($comp['year_levels_in_group'])) {
                foreach ($comp['year_levels_in_group'] as $yl) {
                    $class_year = ($yl['department'] ?? '') . ' ' . ($yl['class_year'] ?? '') . (isset($yl['curriculum']) ? ' ' . $yl['curriculum'] : '');
                    $html .= '<tr>';
                    $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . shortThaiDate($comp['cancellation_date']) . '</td>';
                    $html .= '<td width="34%" style="font-size: 10pt; height:20px;">' . htmlspecialchars($subject_name) . '</td>';
                    $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . ($comp['subject_code'] ?? '') . '</td>';
                    $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . $original_time . '</td>';
                    $html .= '<td width="8%" style="font-size: 10pt; height:20px;text-align:center;">' . ($comp['room_number'] ?? '') . '</td>';
                    $html .= '<td width="8%" style="font-size: 10pt; height:20px;text-align:center;">' . $class_year . '</td>';
                    $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . ($makeup_date ? shortThaiDate($makeup_date) : '') . '</td>';
                    $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . $makeup_time . '</td>';
                    $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . ($comp['proposed_makeup_room_number'] ?? '') . '</td>';
                    $html .= '</tr>';
                }
            }else {
                $class_year = ($comp['department'] ?? '') . ' ' . ($comp['class_year'] ?? '') . (isset($comp['curriculum']) && $comp['curriculum'] ? ' ' . $comp['curriculum'] : '');
                $html .= '<tr>';
                $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . shortThaiDate($comp['cancellation_date']) . '</td>';
                $html .= '<td width="34%" style="font-size: 10pt; height:20px;">' . htmlspecialchars($subject_name) . '</td>';
                $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . ($comp['subject_code'] ?? '') . '</td>';
                $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . $original_time . '</td>';
                $html .= '<td width="8%" style="font-size: 10pt; height:20px;text-align:center;">' . ($comp['room_number'] ?? '') . '</td>';
                $html .= '<td width="8%" style="font-size: 10pt; height:20px;text-align:center;">' . $class_year . '</td>';
                $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . ($makeup_date ? shortThaiDate($makeup_date) : '') . '</td>';
                $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . $makeup_time . '</td>';
                $html .= '<td width="10%" style="font-size: 10pt; height:20px;text-align:center;">' . ($comp['proposed_makeup_room_number'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            
        }
        for ($i = 0; $i < 7; $i++) {
            $html .= '<tr>';
            $html .= '<td width="10%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="34%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="8%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="8%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '<td width="10%" style="font-size: 10pt; height:20px;">&nbsp;</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
        $pdf->SetFont('thsarabunnew', '', 12);
        $pdf->writeHTML($html, true, false, true, false, '');
    } else {
        $pdf->SetFont('thsarabunnew', '', 12);
        $pdf->Cell(0, 20, 'ไม่พบข้อมูลการชดเชยที่ตรงกับเงื่อนไขที่กำหนด', 0, 1, 'C');
    }

    $pdf->Ln(8);
    $pdf->SetFont('thsarabunnew', '', 16);
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 0, 'C');
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 1, 'C');
    $pdf->Cell(90, 6, '          อาจารย์ผู้สอน', 0, 0, 'C');
    $pdf->Cell(90, 6, '          ประธานหลักสูตรสาขาวิชา', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 0, 'C');
    $pdf->Cell(90, 6, 'ลงชื่อ.................................................', 0, 1, 'C');
    $pdf->Cell(90, 6, '          รองคณบดีฝ่ายวิชาการและวิจัย', 0, 0, 'C');
    $pdf->Cell(90, 6, '          คณบดีคณะวิศวกรรมศาสตร์', 0, 1, 'C');
    $pdf->Ln(8);

    $pdf->SetFont('thsarabunnew', '', 16);
    $note_text = "หมายเหตุ     1. ต้องจัดทำล่วงหน้าอย่างน้อย 1 สัปดาห์  เพื่อแนบในบันทึกการสอนภายนอกเวลา\n";
    $note_text .= "                 2. การทำการสอนชดเชยต้องสอนก่อน หรือ หลังการขาดการสอน 1 สัปดาห์";
    $pdf->MultiCell(0, 6, $note_text, 0, 'L', 0, 1, '', '', true);

    // กำหนดชื่อไฟล์
    if ($cancellation_id) {
        $filename = "ใบสอนชดเชย_{$cancellation_id}_" . date('Ymd_His') . ".pdf";
    } else {
        $teacher_label = '';
        if (count($compensations) > 0) {
            $first_comp = $compensations[0];
            if ($teacher_role === 'main') {
                $teacher_label = '_' . preg_replace(
                    '/[^ก-๙a-zA-Z0-9_-]/u',
                    '',
                    ($first_comp['title'] ?? '') . ($first_comp['name'] ?? '') . ($first_comp['lastname'] ?? '')
                );
            } elseif ($teacher_role === 'co1') {
                $teacher_label = '_' . preg_replace(
                    '/[^ก-๙a-zA-Z0-9_-]/u',
                    '',
                    ($first_comp['co_teacher_name'] ?? '')
                );
            } elseif ($teacher_role === 'co2') {
                $teacher_label = '_' . preg_replace(
                    '/[^ก-๙a-zA-Z0-9_-]/u',
                    '',
                    ($first_comp['co_teacher_name_2'] ?? '')
                );
            } elseif ($teacher_role === 'all') {
                $names = [];
                if ($first_comp['title'] ?? false || $first_comp['name'] ?? false || $first_comp['lastname'] ?? false) {
                    $names[] = ($first_comp['title'] ?? '') . ($first_comp['name'] ?? '') . ($first_comp['lastname'] ?? '');
                }
                if ($first_comp['co_teacher_name'] ?? false) $names[] = $first_comp['co_teacher_name'];
                if ($first_comp['co_teacher_name_2'] ?? false) $names[] = $first_comp['co_teacher_name_2'];
                $teacher_label = '_' . preg_replace('/[^ก-๙a-zA-Z0-9_-]/u', '', implode('_', $names));
            }
        } elseif ($selected_teacher) {
            $teacher_label = '_' . preg_replace(
                '/[^ก-๙a-zA-Z0-9_-]/u',
                '',
                ($selected_teacher['title'] ?? '') . ($selected_teacher['name'] ?? '') . ($selected_teacher['lastname'] ?? '')
            );
        }
        $filename = "ใบสอนชดเชย{$teacher_label}_" . date('Ymd_His') . ".pdf";
    }

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